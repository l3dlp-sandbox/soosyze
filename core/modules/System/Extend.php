<?php

declare(strict_types=1);

namespace SoosyzeCore\System;

use Psr\Container\ContainerInterface;
use Queryflatfile\TableBuilder;
use SoosyzeCore\Menu\Enum\Menu;
use SoosyzeCore\System\Form\FormThemeAdmin;
use SoosyzeCore\System\Form\FormThemePublic;
use SoosyzeCore\System\Hook\Config;

/**
 * @phpstan-type AliasEntity array{
 *      id: int,
 *      source: string,
 *      alias: string,
 * }
 */
class Extend extends \SoosyzeCore\System\ExtendModule
{
    public function getDir(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        $translations = [
            'config',
            'config_mailer',
            'humans_time',
            'main',
            'permission',
            'standard',
            'theme',
            'validation'
        ];
        foreach ($translations as $name) {
            $this->loadTranslation('fr', __DIR__ . "/Lang/fr/$name.json");
        }
    }

    public function install(ContainerInterface $ci): void
    {
        $ci->schema()
            ->createTableIfNotExists('module_active', static function (TableBuilder $tb): void {
                $tb->string('title');
                $tb->string('version');
            })
            ->createTableIfNotExists('module_controller', static function (TableBuilder $tb): void {
                $tb->string('title');
                $tb->string('controller');
            })
            ->createTableIfNotExists('module_require', static function (TableBuilder $tb): void {
                $tb->string('title_module');
                $tb->string('title_required');
                $tb->string('version');
            })
            ->createTableIfNotExists('system_alias_url', static function (TableBuilder $tb): void {
                $tb->increments('id');
                $tb->string('source');
                $tb->string('alias');
            })
            ->createTableIfNotExists('migration', static function (TableBuilder $tb): void {
                $tb->string('migration');
                $tb->string('extension');
            });

        $ci->config()
            ->set('settings.maintenance', Config::MAINTENANCE)
            ->set('settings.module_update_time', '')
            ->set('settings.module_update', false)
            ->set('settings.path_no_found', Config::PATH_NOT_FOUND)
            ->set('settings.path_index', Config::PATH_INDEX)
            ->set('settings.path_access_denied', Config::PATH_ACCESS_DENIED)
            ->set('settings.path_maintenance', Config::PATH_MAINTENANCE)
            ->set('settings.meta_title', Config::META_TITLE)
            ->set('settings.meta_description', Config::META_DESCRIPTION)
            ->set('settings.meta_keyboard', Config::META_KEYBOARD)
            ->set('settings.favicon', FormThemePublic::FAVICON)
            ->set('settings.lang', Config::LANG)
            ->set('settings.timezone', Config::TIMEZONE)
            ->set('settings.theme_admin_dark', FormThemeAdmin::THEME_ADMIN_DARK);
    }

    public function seeders(ContainerInterface $ci): void
    {
    }

    public function hookInstall(ContainerInterface $ci): void
    {
        if ($ci->module()->has('Menu')) {
            $this->hookInstallMenu($ci);
        }
        if ($ci->module()->has('User')) {
            $this->hookInstallUser($ci);
        }
    }

    public function hookInstallMenu(ContainerInterface $ci): void
    {
        $ci->query()
            ->insertInto('menu_link', [
                'key', 'icon', 'title_link', 'link', 'menu_id', 'weight', 'parent'
            ])
            ->values([
                'system.module.edit', 'fa fa-th-large', 'Modules', 'admin/modules',
                Menu::ADMIN_MENU, 5, -1
            ])
            ->values([
                'system.theme.index', 'fa fa-paint-brush', 'Themes', 'admin/theme',
                Menu::ADMIN_MENU, 6, -1
            ])
            ->values([
                'system.tool.admin', 'fa fa-tools', 'Tools', 'admin/tool',
                Menu::ADMIN_MENU, 7, -1
            ])
            ->execute();
    }

    public function hookInstallUser(ContainerInterface $ci): void
    {
        $ci->query()
            ->insertInto('role_permission', [ 'role_id', 'permission_id' ])
            ->values([ 3, 'system.module.manage' ])
            ->values([ 3, 'system.theme.manage' ])
            ->values([ 3, 'system.tool.manage' ])
            ->values([ 3, 'system.tool.action' ])
            ->values([ 3, 'system.config.maintenance' ])
            ->execute();
    }

    public function uninstall(ContainerInterface $ci): void
    {
        $tables = [
            'module_required',
            'module_controller',
            'module_active',
            'system_alias_url',
            'migration'
        ];
        foreach ($tables as $table) {
            $ci->schema()->dropTableIfExists($table);
        }
    }

    public function hookUninstall(ContainerInterface $ci): void
    {
        if ($ci->module()->has('Menu')) {
            $this->hookUninstallMenu($ci);
        }
        if ($ci->module()->has('User')) {
            $this->hookUninstallUser($ci);
        }
    }

    public function hookUninstallMenu(ContainerInterface $ci): void
    {
        $ci->menu()->deleteLinks(static function () use ($ci): array {
            return $ci->query()
                    ->from('menu_link')
                    ->where('key', 'like', 'system%')
                    ->fetchAll();
        });
    }

    public function hookUninstallUser(ContainerInterface $ci): void
    {
        $ci->query()
            ->from('role_permission')
            ->delete()
            ->where('permission_id', 'like', 'system.%')
            ->execute();
    }
}
