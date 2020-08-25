<?php

namespace SoosyzeCore\FileManager;

use Psr\Container\ContainerInterface;
use Queryflatfile\TableBuilder;

class Installer extends \SoosyzeCore\System\Migration
{
    public function getDir()
    {
        return __DIR__;
    }
    
    public function boot()
    {
        $this->loadTranslation('fr', __DIR__ . '/Lang/fr/config.json');
        $this->loadTranslation('fr', __DIR__ . '/Lang/fr/main.json');
        $this->loadTranslation('fr', __DIR__ . '/Lang/fr/permission.json');
    }
    
    public function install(ContainerInterface $ci)
    {
        $ci->schema()
            ->createTableIfNotExists('profil_file', function (TableBuilder $table) {
                $table
                ->increments('profil_file_id')
                ->text('folder_show')
                ->boolean('folder_show_sub')->valueDefault(true)
                ->integer('profil_weight')->valueDefault(1)
                ->boolean('folder_store')->valueDefault(true)
                ->boolean('folder_update')->valueDefault(false)
                ->boolean('folder_delete')->valueDefault(true)
                ->integer('folder_size')->valueDefault(10)
                ->boolean('file_store')->valueDefault(true)
                ->boolean('file_update')->valueDefault(false)
                ->boolean('file_delete')->valueDefault(false)
                ->boolean('file_download')->valueDefault(true)
                ->boolean('file_clipboard')->valueDefault(true)
                ->integer('file_size')->valueDefault(1)
                ->boolean('file_extensions_all')->valueDefault(false)
                ->text('file_extensions')->valueDefault('');
            })
            ->createTableIfNotExists('profil_file_role', function (TableBuilder $table) {
                $table
                ->integer('profil_file_id')
                ->integer('role_id');
            });
        $ci->config()->set('settings.replace_file', 1);
    }

    public function seeders(ContainerInterface $ci)
    {
        $ci->query()
            ->insertInto('profil_file', [
                'profil_file_id',
                'folder_show',
                'folder_show_sub',
                'profil_weight',
                'folder_store',
                'folder_update',
                'folder_delete',
                'folder_size',
                'file_store',
                'file_update',
                'file_delete',
                'file_download',
                'file_clipboard',
                'file_size',
                'file_extensions_all',
                'file_extensions'
            ])
            ->values([
                1,
                '/user/:user_id',
                true,
                1,
                true,
                true,
                true,
                20,
                true,
                true,
                true,
                true,
                true,
                1,
                true,
                ''
            ])
            ->values([
                2,
                '/node',
                true,
                2,
                false,
                false,
                false,
                10,
                false,
                false,
                false,
                false,
                false,
                1,
                true,
                ''
            ])
            ->values([
                3,
                '/node/',
                true,
                3,
                true,
                true,
                true,
                0,
                true,
                true,
                false,
                true,
                true,
                0,
                true,
                ''
            ])
            ->values([
                4,
                '/dowload',
                true,
                4,
                false,
                false,
                false,
                10,
                false,
                false,
                false,
                true,
                false,
                1,
                true,
                ''
            ])
            ->values([
                5,
                '/',
                true,
                5,
                true,
                true,
                true,
                10,
                true,
                true,
                true,
                true,
                true,
                1,
                true,
                ''
            ])
            ->execute();

        $ci->query()
            ->insertInto('profil_file_role', [ 'profil_file_id', 'role_id' ])
            ->values([ 1, 2 ])
            ->values([ 2, 3 ])
            ->values([ 3, 2 ])
            ->values([ 4, 1 ])
            ->values([ 5, 3 ])
            ->execute();
    }

    public function hookInstall(ContainerInterface $ci)
    {
        $this->hookInstallUser($ci);
        $this->hookInstallMenu($ci);
    }

    public function hookInstallUser(ContainerInterface $ci)
    {
        if ($ci->module()->has('User')) {
            $ci->query()
                ->insertInto('role_permission', [ 'role_id', 'permission_id' ])
                ->values([ 3, 'filemanager.profil.admin' ])
                ->execute();
        }
    }

    public function hookInstallMenu(ContainerInterface $ci)
    {
        if ($ci->module()->has('Menu')) {
            $ci->query()
                ->insertInto('menu_link', [
                    'key', 'icon', 'title_link', 'link', 'menu', 'weight', 'parent'
                ])
                ->values([
                    'filemanager.admin', 'fa fa-folder', 'File', 'admin/filemanager/show',
                    'menu-admin', 50, -1
                ])
                ->execute();
        }
    }

    public function uninstall(ContainerInterface $ci)
    {
        $ci->schema()->dropTable('profil_file');
        $ci->schema()->dropTable('profil_file_role');
    }

    public function hookUninstall(ContainerInterface $ci)
    {
        $this->hookUninstallMenu($ci);
        $this->hookUninstallUser($ci);
    }

    public function hookUninstallMenu(ContainerInterface $ci)
    {
        if ($ci->module()->has('Menu')) {
            $ci->menu()->deleteLinks(function () use ($ci) {
                return $ci->query()
                        ->from('menu_link')
                        ->where('key', 'like', 'filemanager%')
                        ->fetchAll();
            });
        }
    }

    public function hookUninstallUser(ContainerInterface $ci)
    {
        if ($ci->module()->has('User')) {
            $ci->query()
                ->from('role_permission')
                ->delete()
                ->where('permission_id', 'like', 'filemanager.%')
                ->execute();
        }
    }
}
