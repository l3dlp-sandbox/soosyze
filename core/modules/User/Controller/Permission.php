<?php

declare(strict_types=1);

namespace SoosyzeCore\User\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @method \SoosyzeCore\QueryBuilder\Services\Query  query()
 * @method \SoosyzeCore\Template\Services\Templating template()
 * @method \SoosyzeCore\User\Services\User           user()
 *
 * @phpstan-import-type PermissionsEntity from \SoosyzeCore\User\UserInterface
 * @phpstan-import-type UserRoleEntity from \SoosyzeCore\User\Extend
 */
class Permission extends \Soosyze\Controller
{
    public function __construct()
    {
        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function admin(ServerRequestInterface $req): ResponseInterface
    {
        /* Récupère toutes les permissions par module. */
        /** @phpstan-var PermissionsEntity $modules */
        $modules = [];
        $this->container->callHook('user.permission.module', [ &$modules ]);
        ksort($modules);

        /* Récupére les pérmissions et role en base de données. */
        $permissionsByRole = self::query()
            ->select('permission_id', 'role_id')
            ->from('role')
            ->leftJoin('role_permission', 'role_id', '=', 'role_permission.role_id')
            ->fetchAll();

        /** @phpstan-var array<UserRoleEntity> $fetchRoles */
        $fetchRoles = self::query()->from('role')->orderBy('role_weight')->fetchAll();

        /** @phpstan-var array<int, UserRoleEntity> $roles */
        $roles = array_combine(array_column($fetchRoles, 'role_id'), $fetchRoles);

        /* Simplifie les permissions par roles. */
        $tmp = [];
        foreach ($permissionsByRole as $value) {
            $tmp[ $value[ 'permission_id' ] ][ $value[ 'role_id' ] ] = '';
        }
        $permissionsByRole = $tmp;

        /* Met en forme les droit utilisateurs. */
        $output = [];
        $count  = 0;
        foreach ($modules as $keyModule => $module) {
            foreach ($module as $keyPermission => $permission) {
                $output[ $keyModule ][ $keyPermission ][ 'name' ] = is_string($permission)
                    ? $permission
                    : t($permission[ 'name' ], $permission['attr'] ?? []);
                foreach ($roles as $role) {
                    $output[ $keyModule ][ $keyPermission ][ 'roles' ][ $role[ 'role_id' ] ] =
                        isset($permissionsByRole[ $keyPermission ][ $role[ 'role_id' ] ])
                            ? 'checked'
                            : '';
                }
                ++$count;
            }
        }

        return self::template()
                ->getTheme('theme_admin')
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Administer permissions')
                ])
                ->view('page.submenu', self::user()->getUserManagerSubmenu('user.permission.admin'))
                ->make('page.content', 'user/content-permission-admin.php', $this->pathViews, [
                    'count'       => $count,
                    'link_update' => self::router()->generateUrl('user.permission.update'),
                    'modules'     => $output,
                    'roles'       => $roles
                ]);
    }

    public function udpate(ServerRequestInterface $req): ResponseInterface
    {
        $data = (array) $req->getParsedBody();

        /** @phpstan-var array<int> $rolesId */
        $rolesId           = self::query()->from('role')->lists('role_id');
        $permissionsByRole = self::query()->from('role_permission')->fetchAll();

        foreach ($rolesId as $id) {
            $perm[ $id ] = [];
            if (empty($data[ $id ])) {
                $data[ $id ] = [];
            }
            foreach ($permissionsByRole as $permission) {
                $perm[ $permission[ 'role_id' ] ][ $permission[ 'permission_id' ] ] = $permission[ 'permission_id' ];
            }
            $this->storePermission($id, $perm[ $id ], $data[ $id ]);
            $this->deletePermission($id, $perm[ $id ], $data[ $id ]);
        }
        $_SESSION[ 'messages' ][ 'success' ][] = t('Saved configuration');

        return $this->json(200, [
                'redirect' => self::router()->generateUrl('user.permission.admin')
        ]);
    }

    private function storePermission(
        int $idRole,
        array $permission,
        array $newPermission
    ): void {
        if (!($diffCreate = array_diff_key($newPermission, $permission))) {
            return;
        }

        self::query()->insertInto('role_permission', [ 'role_id', 'permission_id' ]);
        foreach ($diffCreate as $create) {
            if (self::user()->hasPermission($create)) {
                self::query()->values([ $idRole, $create ]);
            }
        }
        self::query()->execute();
    }

    private function deletePermission(
        int $idRole,
        array $permission,
        array $newPermission
    ): void {
        if (!($diffDelete = array_diff_key($permission, $newPermission))) {
            return;
        }

        self::query()->from('role_permission')->delete()
            ->where('role_id', '=', $idRole)
            ->whereGroup(static function ($query) use ($diffDelete) {
                foreach ($diffDelete as $delete) {
                    $query->orWhere('permission_id', '==', $delete);
                }
            })
            ->execute();
    }
}
