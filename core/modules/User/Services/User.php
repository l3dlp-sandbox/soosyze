<?php

declare(strict_types=1);

namespace SoosyzeCore\User\Services;

use Core;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Soosyze\Components\Http\Response;
use Soosyze\Components\Http\Stream;
use Soosyze\Components\Router\Route;
use Soosyze\Components\Router\Router;
use Soosyze\Config;
use SoosyzeCore\QueryBuilder\Services\Query;
use SoosyzeCore\Template\Services\Templating;
use SoosyzeCore\User\Hook\Config as HookConfig;

/**
 * @phpstan-import-type PermissionsEntity from \SoosyzeCore\User\UserInterface
 * @phpstan-type Submenu array<
 *      array{
 *          key: string,
 *          link?: string,
 *          request: RequestInterface,
 *          title_link: string
 *      }
 *  >
 */
class User
{
    /**
     * Les données utilisateur courant ou false.
     *
     * @var null|array
     */
    private $connect = null;

    /**
     * @var Core
     */
    private $core;

    /**
     * @var Config
     */
    private $config;

    /**
     * La liste des permissions pour l'utilisateur courant.
     *
     * @var array
     */
    private $granted     = [];

    /**
     * La liste des permissions.
     *
     * @var array
     */
    private $permissions = [];

    /**
     * @var Query
     */
    private $query;

    /**
     * @var Router
     */
    private $router;

    public function __construct(Core $core, Config $config, Query $query, Router $router)
    {
        $this->core   = $core;
        $this->config = $config;
        $this->query  = $query;
        $this->router = $router;
    }

    public function find(int $id): ?array
    {
        return $this->query
                ->from('user')
                ->where('user_id', '=', $id)
                ->fetch();
    }

    public function findActived(int $id, bool $actived = true): ?array
    {
        return $this->query
                ->from('user')
                ->where('user_id', '=', $id)
                ->where('actived', '=', $actived)
                ->fetch();
    }

    public function getUser(string $email): ?array
    {
        return $this->query
                ->from('user')
                ->where('email', '=', $email)
                ->fetch();
    }

    public function getUserByUsername(string $username): ?array
    {
        return $this->query
                ->from('user')
                ->where('username', '=', $username)
                ->fetch();
    }

    public function getUserActived(string $email, bool $actived = true): ?array
    {
        return $this->query
                ->from('user')
                ->where('email', '=', $email)
                ->where('actived', '=', $actived)
                ->fetch();
    }

    public function getUserActivedToken(string $token, bool $actived = true): ?array
    {
        return $this->query
                ->from('user')
                ->where('token_connected', '=', $token)
                ->where('actived', '=', $actived)
                ->fetch();
    }

    public function getUsers(): array
    {
        return $this->query->from('user')->fetchAll();
    }

    public function getRolesUser(int $idUser): array
    {
        return $this->query
                ->from('user_role')
                ->leftJoin('role', 'role_id', '=', 'role.role_id')
                ->where('user_id', '=', $idUser)
                ->fetchAll();
    }

    public function getRoles(): array
    {
        return $this->query->from('role')->fetchAll();
    }

    public function getRolesAttribuable(): array
    {
        return $this->query
            ->from('role')
            ->where('role_id', '>', 2)
            ->orderBy('role_weight')
            ->fetchAll();
    }

    public function getIdRolesUser(int $idUser): array
    {
        $data = $this->getRolesUser($idUser);

        $out  = [];
        foreach ($data as $value) {
            $out[ $value[ 'role_id' ] ] = $value[ 'role_label' ];
        }

        return $out;
    }

    public function getUserSubmenu(string $keyRoute, int $id): array
    {
        /** @phpstan-var Submenu $menu */
        $menu = [
            [
                'key'        => 'user.show',
                'request'    => $this->router->generateRequest('user.show', [
                    'id' => $id
                ]),
                'title_link' => t('View')
            ], [
                'key'        => 'user.edit',
                'request'    => $this->router->generateRequest('user.edit', [
                    'id' => $id
                ]),
                'title_link' => t('Edit')
            ], [
                'key'        => 'user.remove',
                'request'    => $this->router->generateRequest('user.remove', [
                    'id' => $id
                ]),
                'title_link' => t('Delete')
            ]
        ];

        $this->core->callHook('user.submenu', [ &$menu, $id ]);

        foreach ($menu as $key => &$link) {
            if (!$this->isGrantedRequest($link[ 'request' ])) {
                unset($menu[ $key ]);

                continue;
            }
            $link[ 'link' ] = $link[ 'request' ]->getUri();
        }
        unset($link);

        return [
            'key_route' => $keyRoute,
            'menu'      => count($menu) === 1
                ? []
                : $menu
        ];
    }

    public function getUserManagerSubmenu(string $keyRoute): array
    {
        /** @phpstan-var Submenu $menu */
        $menu = [
            [
                'key'        => 'user.admin',
                'request'    => $this->router->generateRequest('user.admin'),
                'title_link' => t('Users')
            ], [
                'key'        => 'user.role.admin',
                'request'    => $this->router->generateRequest('user.role.admin'),
                'title_link' => t('Roles')
            ], [
                'key'        => 'user.permission.admin',
                'request'    => $this->router->generateRequest('user.permission.admin'),
                'title_link' => t('Permissions')
            ]
        ];

        $this->core->callHook('user.manager.submenu', [ &$menu ]);

        foreach ($menu as $key => &$link) {
            if (!$this->isGrantedRequest($link[ 'request' ])) {
                unset($menu[ $key ]);

                continue;
            }
            $link[ 'link' ] = $link[ 'request' ]->getUri();
        }
        unset($link);

        return [ 'key_route' => $keyRoute, 'menu' => $menu ];
    }

    public function hasPermission(?string $idPermission): bool
    {
        if (!empty($this->permissions)) {
            return isset($this->permissions[ $idPermission ]);
        }

        /** @phpstan-var PermissionsEntity $permission */
        $permission = [];
        $this->core->callHook('user.permission.module', [ &$permission ]);
        foreach ($permission as $value) {
            $this->permissions += $value;
        }

        return isset($this->permissions[ $idPermission ]);
    }

    public function getGranted(array $user, string $idPermission): bool
    {
        if (!empty($this->granted)) {
            return in_array($idPermission, $this->granted);
        }

        $this->granted = $this->query
            ->from('user_role')
            ->leftJoin('role', 'role_id', '=', 'role.role_id')
            ->leftJoin('role_permission', 'role_id', '=', 'role_permission.role_id')
            ->where('user_id', '=', $user[ 'user_id' ])
            ->lists('permission_id');
        $this->granted = array_merge($this->granted, $this->query
            ->from('role_permission')
            ->where('role_id', '=', 2)
            ->lists('permission_id'));

        return in_array($idPermission, $this->granted);
    }

    public function getGrantedAnonymous(string $idPermission): bool
    {
        if (!empty($this->granted)) {
            return in_array($idPermission, $this->granted);
        }

        $this->granted = $this->query->from('role_permission')
            ->where('role_id', '=', 1)
            ->lists('permission_id');

        return in_array($idPermission, $this->granted);
    }

    /**
     * Si la session existe renvoie l'utilisateur,
     * sinon s'il y a correspondance dans les autres cas renvoie faux.
     *
     * @return null|array
     */
    public function isConnected(): ?array
    {
        if ($this->connect) {
            return $this->connect;
        }
        if (!empty($_SESSION[ 'token_connected' ])) {
            if (!($user = $this->getUserActivedToken($_SESSION[ 'token_connected' ]))) {
                return null;
            }

            $this->connect = $_SESSION[ 'token_connected' ] == $user[ 'token_connected' ]
                ? $user
                : null;

            return $this->connect;
        }

        return null;
    }

    public function isConnectUrl(string $url): bool
    {
        $connectUrl = $this->config->get('settings.connect_url', HookConfig::CONNECT_URL);

        return !empty($connectUrl) && $url !== '/' . $connectUrl;
    }

    public function passwordPolicy(): string
    {
        /** @phpstan-var numeric $length */
        $length = $this->config->get('settings.password_length', HookConfig::PASSWORD_LENGTH);
        if ($length < 8) {
            $length = 8;
        }
        /** @phpstan-var numeric $upper */
        $upper = $this->config->get('settings.password_upper', HookConfig::PASSWORD_UPPER);
        if ($upper < 1) {
            $upper = 1;
        }
        /** @phpstan-var numeric $digit */
        $digit = $this->config->get('settings.password_digit', HookConfig::PASSWORD_DIGIT);
        if ($digit < 1) {
            $digit = 1;
        }
        /** @phpstan-var numeric $special */
        $special = $this->config->get('settings.password_special', HookConfig::PASSWORD_SPECIAL);
        if ($special < 1) {
            $special = 1;
        }

        return '/(?=.*\d){' . $digit . ',}(?=.*[a-z])(?=.*\W){' . $special . ',}(?=.*[A-Z]){' . $upper . ',}.{' . $length . ',}/';
    }

    /**
     * Vérifie les droits d'accès aux contrôleurs.
     */
    public function isGranted(string $key, bool &$grant = false): bool
    {
        /* Si la permission n'existe pas. */
        if (!$this->hasPermission($key)) {
            $grant = false;
        }
        /* Si l'utilisateur et connecté. */
        elseif ($user = $this->isConnected()) {
            $grant = (bool) $this->getGranted($user, $key);
        }
        /* Si l'utilisateur annonyme peut voir la route. */
        else {
            $grant = (bool) $this->getGrantedAnonymous($key);
        }

        return $grant;
    }

    public function isGrantedRequest(RequestInterface $request): bool
    {
        $route = $this->router->parse($request);

        if (!$route instanceof Route) {
            return false;
        }
        /* Si la permission n'existe pas. */
        if ($this->hasPermission($route->getKey())) {
            return $this->isGranted($route->getKey());
        }

        if ($route->getWiths() !== null) {
            $withs = $this->router->parseWiths($route, $request);
        }

        $withs[]     = $request;
        $withs[]     = $this->isConnected();
        /** @phpstan-var array|bool|string|null $permissions */
        $permissions = $this->core->callHook('route.' . $route->getKey(), $withs);

        return $this->isGrantedPermission($permissions);
    }

    /**
     * @param bool|null|string|array $permissions
     */
    public function isGrantedPermission($permissions): bool
    {
        if (\is_bool($permissions)) {
            return $permissions;
        }
        if ($permissions === null) {
            return false;
        }
        if (\is_string($permissions)) {
            return $this->isGranted($permissions);
        }
        foreach ($permissions as $permission) {
            if ($this->isGranted($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fonctionnement par défaut de l'application.
     * Défini les règles du déclenchement d'un retour 403 à l'aide des hooks.
     */
    public function hookResponseBefore(RequestInterface &$request, ResponseInterface &$response): void
    {
        if (!$this->isGrantedRequest($request)) {
            $response = new Response(403, new Stream('Error HTTP 403 Forbidden'));
        }
    }

    public function hookResponseAfter(RequestInterface $request, ResponseInterface &$response): void
    {
        if (!($response instanceof Templating)) {
            return;
        }

        $vendor = $this->core->getPath('modules', 'modules/core', false);

        $response->addScript('user', "$vendor/User/Assets/js/user.js");
    }
}
