<?php

namespace SoosyzeCore\Node;

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
            ->createTableIfNotExists('node', function (TableBuilder $table) {
                $table->increments('id')
                ->integer('date_changed')
                ->integer('date_created')
                ->integer('entity_id')->nullable()
                ->string('meta_description')->valueDefault('')
                ->boolean('meta_noarchive')->valueDefault(false)
                ->boolean('meta_nofollow')->valueDefault(false)
                ->boolean('meta_noindex')->valueDefault(false)
                ->string('meta_title')->valueDefault('')
                ->integer('node_status_id')->valueDefault(3)
                ->boolean('sticky')->valueDefault(false)
                ->string('title')
                ->string('type', 32);
            })
            ->createTableIfNotExists('node_type', function (TableBuilder $table) {
                $table->string('node_type')
                ->string('node_type_name')
                ->string('node_type_icon')
                ->text('node_type_description');
            })
            ->createTableIfNotExists('node_status', function (TableBuilder $table) {
                $table->increments('node_status_id')
                ->text('node_status_name');
            })
            ->createTableIfNotExists('field', function (TableBuilder $table) {
                $table->increments('field_id')
                ->string('field_name')
                ->string('field_type');
            })
            /* Table pivot. */
            ->createTableIfNotExists('node_type_field', function (TableBuilder $table) {
                $table->string('node_type')
                ->integer('field_id')
                ->string('field_label')
                ->string('field_rules')
                /* Si la donnée doit-être affichée. */
                ->boolean('field_show')->valueDefault(true)
                /* Si la donnée doit-être affichée dans le formulaire. */
                ->boolean('field_show_form')->valueDefault(true)
                /* Si le label doit-être affiché. */
                ->boolean('field_show_label')->valueDefault(false)
                ->text('field_description')->valueDefault('')
                ->text('field_option')->valueDefault('')
                ->text('field_default_value')->nullable()
                /* Poisition du champ. */
                ->integer('field_weight')->valueDefault(1)
                /* Poisition de la donnée dans l'affichage. */
                ->integer('field_weight_form')->valueDefault(1);
            })
            ->createTableIfNotExists('entity_page', function (TableBuilder $table) {
                $table->increments('page_id')
                ->text('body');
            })
            ->createTableIfNotExists('entity_page_private', function (TableBuilder $table) {
                $table->increments('page_private_id')
                ->text('body');
            });

        $ci->query()->insertInto('node_status', [
                'node_status_id', 'node_status_name'
            ])
            ->values([ 1, 'Published' ])
            ->values([ 2, 'Pending publication' ])
            ->values([ 3, 'Draft' ])
            ->values([ 4, 'Archived' ])
            ->execute();

        $ci->query()->insertInto('node_type', [
                'node_type',
                'node_type_name',
                'node_type_description',
                'node_type_icon'
            ])
            ->values([
                'page',
                'Page',
                'Use the pages for your static content.',
                'fa fa-file'
            ])
            ->values([
                'page_private',
                'Private page',
                'Use private pages for content reserved for your members.',
                'far fa-file'
            ])
            ->execute();

        $ci->query()->insertInto('field', [
                'field_name', 'field_type'
            ])
            ->values([ 'body', 'textarea' ])
            ->values([ 'image', 'image' ])
            ->values([ 'summary', 'textarea' ])
            ->values([ 'reading_time', 'number' ])
            ->values([ 'weight', 'number' ])
            ->execute();

        $ci->query()
            ->insertInto('node_type_field', [
                'node_type', 'field_id', 'field_label', 'field_weight', 'field_rules',
                'field_option'
            ])
            ->values([ 'page', 1, 'Body', 2, '!required|string', '' ])
            ->values([ 'page_private', 1, 'Body', 2, '!required|string', '' ])
            ->execute();

        $ci->config()
            ->set('settings.node_default_url', ':node_type/:node_title')
            ->set('settings.node_url_page_private', 'page/:node_title')
            ->set('settings.node_cron', false);
    }

    public function seeders(ContainerInterface $ci)
    {
    }

    public function hookInstall(ContainerInterface $ci)
    {
        $this->hookInstallMenu($ci);
        $this->hookInstallUser($ci);
    }

    public function hookInstallUser(ContainerInterface $ci)
    {
        if ($ci->module()->has('User')) {
            $ci->query()
                ->insertInto('role_permission', [ 'role_id', 'permission_id' ])
                ->values([ 3, 'node.administer' ])
                ->values([ 2, 'node.show.published.page_private' ])
                ->values([ 2, 'node.show.published.page' ])
                ->values([ 1, 'node.show.published.page' ])
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
                    'node.admin', 'fa fa-file', 'Contents', 'admin/node', 'menu-admin',
                    2, -1
                ])
                ->values([
                    'node.show', null, 'Home', '/', 'menu-main', 1, -1
                ])
                ->execute();

            $ci->schema()
                ->createTableIfNotExists('node_menu_link', function (TableBuilder $table) {
                    $table->integer('node_id')
                    ->integer('menu_link_id');
                });
        }
    }

    public function uninstall(ContainerInterface $ci)
    {
        $types = $ci->query()->from('node_type')->lists('node_type');
        foreach ($types as $type) {
            $ci->schema()->dropTableIfExists('entity_' . $type);
        }
        $ci->schema()->dropTableIfExists('node_type_field');
        $ci->schema()->dropTableIfExists('field');
        $ci->schema()->dropTableIfExists('node_type');
        $ci->schema()->dropTableIfExists('node');
        $ci->schema()->dropTableIfExists('node_status');
        
        $ci->query()->from('system_alias_url')
            ->delete()
            ->where('source', 'like', 'node%')
            ->execute();
    }

    public function hookUninstall(ContainerInterface $ci)
    {
        $this->hookUninstallMenu($ci);
        $this->hookUninstallUser($ci);
    }

    public function hookUninstallMenu(ContainerInterface $ci)
    {
        $ci->schema()->dropTableIfExists('node_menu_link');

        if ($ci->module()->has('Menu')) {
            $ci->menu()->deleteLinks(function () use ($ci) {
                return $ci->query()
                        ->from('menu_link')
                        ->where('key', 'like', 'node%')
                        ->orWhere('key', 'like', 'entity%')
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
                ->where('permission_id', 'like', 'node%')
                ->execute();
        }
    }
}
