<?php

declare(strict_types=1);

namespace SoosyzeCore\FileManager\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Soosyze\Components\Form\FormBuilder;
use Soosyze\Components\Validator\Validator;

/**
 * @method \SoosyzeCore\FileManager\Services\FileProfil fileprofil()
 * @method \SoosyzeCore\QueryBuilder\Services\Query     query()
 * @method \SoosyzeCore\Template\Services\Templating    template()
 * @method \SoosyzeCore\User\Services\User              user()
 *
 * @phpstan-import-type ProfilFileEntity from \SoosyzeCore\FileManager\Extend
 */
class FilePermissionManager extends \Soosyze\Controller
{
    public function __construct()
    {
        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function admin(): ResponseInterface
    {
        /** @phpstan-var array<ProfilFileEntity> $values */
        $values = self::query()->from('profil_file')->orderBy('profil_weight')->fetchAll();

        $this->container->callHook('filemanager.permission.admin.form.data', [ &$values ]);

        $form = new FormBuilder([
            'action' => self::router()->generateUrl('filemanager.permission.admin.check'),
            'class'  => 'form-api',
            'method' => 'patch'
        ]);

        foreach ($values as &$profil) {
            $profil[ 'roles' ] = self::fileprofil()->getRolesUserByProfil($profil[ 'profil_file_id' ]);

            $form->group("profil_{$profil[ 'profil_file_id' ]}-group", 'div', function ($form) use ($profil) {
                $form->group('profil_weight-flex', 'div', function ($form) use ($profil) {
                    $form->number("profil_weight-{$profil[ 'profil_file_id' ]}", [
                        ':actions' => 1,
                        'class'    => 'form-control',
                        'max'      => 50,
                        'min'      => 1,
                        'value'    => $profil[ 'profil_weight' ]
                    ]);
                }, [ 'class' => 'form-group-flex' ]);
            });
        }
        unset($profil);
        $form
            ->group('submit-group', 'div', function ($form) {
                $form->token('token_profil_form')
                ->submit('submit', t('Save'), [ 'class' => 'btn btn-success' ]);
            });

        $this->container->callHook('filemanager.permission.admin.form', [
            &$form, $values
        ]);

        return self::template()
                ->getTheme('theme_admin')
                ->view('page', [
                    'icon'       => '<i class="fa fa-folder" aria-hidden="true"></i>',
                    'title_main' => t('Administer file permissions')
                ])
                ->view('page.submenu', self::user()->getUserManagerSubmenu('filemanager.permission.admin'))
                ->make('page.content', 'filemanager/content-file_permission_manager-admin.php', $this->pathViews, [
                    'form'     => $form,
                    'link_add' => self::router()->generateUrl('filemanager.permission.create'),
                    'profils'  => $values,
                    'router'   => self::router()
                ]);
    }

    public function adminCheck(ServerRequestInterface $req): ResponseInterface
    {
        /** @phpstan-var array<ProfilFileEntity> $profils */
        $profils = self::query()->from('profil_file')->fetchAll();

        $validator = (new Validator())
            ->addRule('token_profil_form', 'token')
            ->setInputs((array) $req->getParsedBody());

        foreach ($profils as $profil) {
            $validator
                ->addRule("profil_weight-{$profil[ 'profil_file_id' ]}", 'required|numeric|between:1,50');
        }

        $this->container->callHook('filemanager.permission.admin.check.validator', [
            &$validator
        ]);

        if ($validator->isValid()) {
            foreach ($profils as $profil) {
                $data = [
                    'profil_weight' => $validator->getInputInt("profil_weight-{$profil[ 'profil_file_id' ]}")
                ];

                $this->container->callHook('filemanager.permission.admin.check.before', [
                    $validator, &$data
                ]);

                self::query()
                    ->update('profil_file', $data)
                    ->where('profil_file_id', '=', $profil[ 'profil_file_id' ])
                    ->execute();

                $this->container->callHook('filemanager.permission.admin.check.after', [
                    &$validator
                ]);
            }

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
}
