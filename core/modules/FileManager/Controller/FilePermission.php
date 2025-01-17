<?php

declare(strict_types=1);

namespace SoosyzeCore\FileManager\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soosyze\Components\Form\FormBuilder;
use Soosyze\Components\Validator\Validator;
use SoosyzeCore\FileManager\Form\FormPermission;
use SoosyzeCore\FileManager\Services\FileManager;

/**
 * @method \SoosyzeCore\FileManager\Services\FileProfil fileprofil()
 * @method \SoosyzeCore\QueryBuilder\Services\Query     query()
 * @method \SoosyzeCore\QueryBuilder\Services\Schema    schema()
 * @method \SoosyzeCore\Template\Services\Templating    template()
 * @method \SoosyzeCore\User\Services\User              user()
 *
 * @phpstan-type Submenu array<
 *      array{
 *          key: string,
 *          request: \Psr\Http\Message\RequestInterface,
 *          title_link: string
 *      }
 *  >
 */
class FilePermission extends \Soosyze\Controller
{
    public function __construct()
    {
        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function create(ServerRequestInterface $req): ResponseInterface
    {
        $values = [];
        $this->container->callHook('filemanager.permission.create.form.data', [ &$values ]);

        $action = self::router()->generateUrl('filemanager.permission.store');

        $form = (new FormPermission([ 'action' => $action, 'method' => 'post' ]))
            ->setValues($values)
            ->setRoles(self::query()->from('role')->fetchAll())
            ->makeFields();

        $this->container->callHook('filemanager.permission.create.form', [
            &$form, $values
        ]);

        return self::template()
                ->getTheme('theme_admin')
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Add a files permission')
                ])
                ->make('page.content', 'filemanager/content-file_permission-form.php', $this->pathViews, [
                    'form' => $form
        ]);
    }

    public function store(ServerRequestInterface $req): ResponseInterface
    {
        $validator = $this->getValidator($req);

        $this->container->callHook('filemanager.permission.store.validator', [ &$validator ]);

        $validatorExtension = $this->getValidatorExtensionList($validator);

        $isValid = $validator->isValid() && $validatorExtension->isValid();

        if ($isValid) {
            $data = $this->getData($validator);

            $this->container->callHook('filemanager.permission.store.before', [
                $validator, &$data
            ]);

            self::query()
                ->insertInto('profil_file', array_keys($data))
                ->values($data)
                ->execute();

            $permissionFileId = self::schema()->getIncrement('profil_file');
            $this->storeProfilRole($validator, $permissionFileId);

            $this->container->callHook('filemanager.permission.store.after', [ $validator ]);

            $_SESSION[ 'messages' ][ 'success' ][] = t('Saved configuration');

            return $this->json(201, [
                    'redirect' => self::router()->generateUrl('filemanager.permission.admin')
            ]);
        }

        return $this->json(400, [
                'messages'    => [ 'errors' => $validator->getKeyErrors() ],
                'errors_keys' => $validator->getKeyInputErrors()
        ]);
    }

    public function edit(int $id, ServerRequestInterface $req): ResponseInterface
    {
        if (!($values = self::fileprofil()->find($id))) {
            return $this->get404($req);
        }

        $values[ 'file_extensions' ] = explode(',', $values[ 'file_extensions' ]);

        $values[ 'roles' ] = self::fileprofil()->getIdRolesUser($id);

        $this->container->callHook('filemanager.permission.edit.form.data', [ &$values ]);

        $action = self::router()->generateUrl('filemanager.permission.update', [ 'id' => $id ]);

        $form = (new FormPermission([ 'action' => $action, 'method' => 'put' ]))
            ->setRoles(self::query()->from('role')->fetchAll())
            ->setValues($values)
            ->makeFields();

        $this->container->callHook('filemanager.permission.edit.form', [
            &$form, $values
        ]);

        return self::template()
                ->getTheme('theme_admin')
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Edit the files permission')
                ])
                ->view('page.submenu', $this->getPermissionSubmenu('filemanager.permission.edit', $id))
                ->make('page.content', 'filemanager/content-file_permission-form.php', $this->pathViews, [
                    'form' => $form
                ]);
    }

    public function update(int $id, ServerRequestInterface $req): ResponseInterface
    {
        if (!self::fileprofil()->find($id)) {
            return $this->json(404, [
                    'messages' => [ 'errors' => [ t('The requested resource does not exist.') ] ]
            ]);
        }

        $validator = $this->getValidator($req);

        $this->container->callHook('filemanager.permission.update.validator', [
            &$validator, $id
        ]);

        $validatorExtension = $this->getValidatorExtensionList($validator);

        $isValid = $validator->isValid() && $validatorExtension->isValid();

        if ($isValid) {
            $data = $this->getData($validator);

            $this->container->callHook('filemanager.permission.update.before', [
                $validator, &$data, $id
            ]);
            self::query()
                ->update('profil_file', $data)
                ->where('profil_file_id', '=', $id)
                ->execute();
            $this->updateProfilRole($validator, $id);
            $this->container->callHook('filemanager.permission.update.after', [
                $validator, $id
            ]);

            $_SESSION[ 'messages' ][ 'success' ][] = t('Saved configuration');

            return $this->json(200, [
                    'redirect' => self::router()->generateUrl('filemanager.permission.admin')
            ]);
        }

        return $this->json(400, [
                'messages'    => [ 'errors' => $validator->getKeyErrors() ],
                'errors_keys' => $validator->getKeyInputErrors()
        ]);
    }

    public function remove(int $id, ServerRequestInterface $req): ResponseInterface
    {
        if (!self::fileprofil()->find($id)) {
            $this->get404($req);
        }

        $action = self::router()->generateUrl('filemanager.permission.delete', [
            'id' => $id
        ]);

        $form = (new FormBuilder([ 'action' => $action, 'class' => 'form-api', 'method' => 'delete' ]))
            ->group('profil-fieldset', 'fieldset', function ($form) {
                $form->legend('profil-legend', t('Delete files permission'))
                ->group('info-group', 'div', function ($form) {
                    $form->html('info', '<p:attr>:content</p>', [
                        ':content' => t('Warning ! The deletion of the files permission is final.')
                    ]);
                }, [ 'class' => 'alert alert-warning' ]);
            })
            ->group('submit-group', 'div', function ($form) {
                $form->token('token_file_permission')
                ->submit('submit', t('Delete'), [ 'class' => 'btn btn-danger' ])
                ->html('cancel', '<button:attr>:content</button>', [
                    ':content' => t('Cancel'),
                    'class'    => 'btn btn-default',
                    'onclick'  => 'javascript:history.back();',
                    'type'     => 'button'
                ]);
            });

        return self::template()
                ->getTheme('theme_admin')
                ->view('page', [
                    'icon'       => '<i class="fa fa-user" aria-hidden="true"></i>',
                    'title_main' => t('Delete files permission')
                ])
                ->view('page.submenu', $this->getPermissionSubmenu('filemanager.permission.remove', $id))
                ->make('page.content', 'filemanager/content-file_permission-form.php', $this->pathViews, [
                    'form' => $form
                ]);
    }

    public function delete(int $id, ServerRequestInterface $req): ResponseInterface
    {
        if (!self::fileprofil()->find($id)) {
            return $this->json(404, [
                    'messages' => [ 'errors' => [ t('The requested resource does not exist.') ] ]
            ]);
        }

        $validator = (new Validator())
            ->addRule('token_file_permission', 'token')
            ->setInputs((array) $req->getParsedBody());

        $this->container->callHook('filemanager.permission.delete.validator', [
            &$validator, $id
        ]);

        if ($validator->isValid()) {
            $this->container->callHook('filemanager.permission.delete.before', [
                $validator, $id
            ]);
            self::query()
                ->from('profil_file_role')
                ->delete()
                ->where('profil_file_id', '=', $id)
                ->execute();
            self::query()
                ->from('profil_file')
                ->delete()
                ->where('profil_file_id', '=', $id)
                ->execute();
            $this->container->callHook('filemanager.permission.delete.after', [
                $validator, $id
            ]);

            $_SESSION[ 'messages' ][ 'success' ][] = t('Saved configuration');

            return $this->json(200, [
                    'redirect' => self::router()->generateUrl('filemanager.permission.admin')
            ]);
        }

        return $this->json(400, [
                'messages'    => [ 'errors' => $validator->getKeyErrors() ],
                'errors_keys' => $validator->getKeyInputErrors()
        ]);
    }

    private function storeProfilRole(Validator $validator, int $profilFileId): void
    {
        $rolesId = array_keys($validator->getInputArray('roles'));

        self::query()->insertInto('profil_file_role', [ 'profil_file_id', 'role_id' ]);
        foreach ($rolesId as $roleId) {
            self::query()->values([ $profilFileId, $roleId ]);
        }
        self::query()->execute();
    }

    private function updateProfilRole(Validator $validator, int $profilFileId): void
    {
        self::query()
            ->from('profil_file_role')
            ->where('profil_file_id', '=', $profilFileId)
            ->delete()
            ->execute();

        $rolesId = array_keys($validator->getInputArray('roles'));

        self::query()->insertInto('profil_file_role', [ 'profil_file_id', 'role_id' ]);
        foreach ($rolesId as $roleId) {
            self::query()->values([ $profilFileId, $roleId ]);
        }
        self::query()->execute();
    }

    private function getValidator(ServerRequestInterface $req): Validator
    {
        $validator = (new Validator())
            ->setRules([
                'folder_show'           => 'required|string|regex:/^\/([\-\:\w]+\/?)*$/',
                'folder_show_sub'       => 'bool',
                'profil_weight'         => 'required|between_numeric:1,50',
                'roles'                 => '!required|array',
                'folder_store'          => 'bool',
                'folder_update'         => 'bool',
                'folder_delete'         => 'bool',
                'folder_download'       => 'bool',
                'folder_size'           => '!required|numeric|min_numeric:0',
                'file_store'            => 'bool',
                'file_update'           => 'bool',
                'file_delete'           => 'bool',
                'file_download'         => 'bool',
                'file_clipboard'        => 'bool',
                'file_copy'             => 'bool',
                'file_size'             => '!required|numeric|min_numeric:0',
                'file_extensions_all'   => 'bool',
                'file_extensions'       => '!required|array',
                'token_file_permission' => 'token'
            ])
            ->setMessages([
                'folder_show' => [
                    'regex' => [
                        'must' => t('The :label field should represent a tree structure of your files.')
                        . '<br>'
                        . t('It must start with a `\\` and may consist of alphanumeric characters `[a-z0-9]`, slashes `\\`, hyphens `-` and underscores `_`')
                    ]
                ]
            ])
            ->setLabels([
                'folder_show'     => t('Directory path'),
                'folder_show_sub' => t('Sub directories included'),
                'profil_weight'   => t('Weight'),
                'roles'           => t('User Roles'),
                'folder_size'     => t('Size limit by directory'),
                'file_size'       => t('Size limit by file'),
                'file_extensions' => t('File extensions'),
            ])
            ->setInputs((array) $req->getParsedBody());

        if (!$validator->getInput('roles')) {
            $validator->addInput('roles', []);
        }
        if (!$validator->getInput('file_extensions')) {
            $validator->addInput('file_extensions', []);
        }

        return $validator;
    }

    private function getValidatorExtensionList(Validator $validator): Validator
    {
        $validatorExtension  = new Validator();
        $fileExtensionsInput = $validator->getInputArray('file_extensions');

        $listExtension = implode(',', FileManager::getExtAllowed());
        foreach ($fileExtensionsInput as $key => $extension) {
            $validatorExtension
                ->addRule($key, 'inarray:' . $listExtension)
                ->addLabel($key, $extension)
                ->addInput($key, $key);
        }

        return $validatorExtension;
    }

    private function getData(Validator $validator): array
    {
        return [
            'folder_show'         => $validator->getInput('folder_show'),
            'folder_show_sub'     => (bool) $validator->getInput('folder_show_sub'),
            'profil_weight'       => $validator->getInputInt('profil_weight'),
            'folder_store'        => (bool) $validator->getInput('folder_store'),
            'folder_update'       => (bool) $validator->getInput('folder_update'),
            'folder_delete'       => (bool) $validator->getInput('folder_delete'),
            'folder_download'     => (bool) $validator->getInput('folder_download'),
            'folder_size'         => $validator->getInputInt('folder_size'),
            'file_store'          => (bool) $validator->getInput('file_store'),
            'file_update'         => (bool) $validator->getInput('file_update'),
            'file_delete'         => (bool) $validator->getInput('file_delete'),
            'file_download'       => (bool) $validator->getInput('file_download'),
            'file_clipboard'      => (bool) $validator->getInput('file_clipboard'),
            'file_copy'           => (bool) $validator->getInput('file_copy'),
            'file_size'           => $validator->getInputInt('file_size'),
            'file_extensions_all' => (bool) $validator->getInput('file_extensions_all'),
            'file_extensions'     => $validator->getInput('file_extensions_all')
                ? ''
                : implode(',', $validator->getInputArray('file_extensions'))
        ];
    }

    private function getPermissionSubmenu(string $keyRoute, int $idPermission): array
    {
        /** @phpstan-var Submenu $menu */
        $menu = [
            [
                'key'        => 'filemanager.permission.edit',
                'request'    => self::router()->generateRequest('filemanager.permission.edit', [
                    'id' => $idPermission
                ]),
                'title_link' => t('Edit')
            ], [
                'key'        => 'filemanager.permission.remove',
                'request'    => self::router()->generateRequest('filemanager.permission.remove', [
                    'id' => $idPermission
                ]),
                'title_link' => t('Delete')
            ]
        ];

        $this->container->callHook('filemanager.permission.submenu', [ &$menu ]);

        foreach ($menu as $key => &$link) {
            if (!self::user()->isGrantedRequest($link[ 'request' ])) {
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
}
