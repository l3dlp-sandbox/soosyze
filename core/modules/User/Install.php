<?php

namespace User;

use Queryflatfile\TableBuilder;

class Install
{
    public function install($container)
    {
        $container->schema()->createTableIfNotExists('user', function (TableBuilder $table) {
            $table->increments('user_id')
                ->string('email')
                ->text('password')
                ->text('salt')
                ->string('firstname')
                ->string('name')
                ->boolean('actived')
                ->text('forget_pass')
                ->string('time_reset')
                ->string('time_installed')
                ->text('timezone');
        });
        $container->schema()->createTableIfNotExists('role', function (TableBuilder $table) {
            $table->increments('role_id')
                ->string('role_name')
                ->string('role_label');
        });
        $container->schema()->createTableIfNotExists('permission', function (TableBuilder $table) {
            $table->string('permission_id')
                ->string('permission_label');
        });

        $container->schema()->createTableIfNotExists('user_role', function (TableBuilder $table) {
            $table->integer('user_id')
                ->integer('role_id');
        });
        $container->schema()->createTableIfNotExists('role_permission', function (TableBuilder $table) {
            $table->integer('role_id')
                ->string('permission_id');
        });

        $container->query()->insertInto('role', [ 'role_name', 'role_label' ])
            ->values([ 'user_anonyme', 'Utilisateur non connecté' ])
            ->values([ 'user_connected', 'Utilisateur connecté' ])
            ->values([ 'admin', 'Administrateur' ])
            ->execute();

        $container->query()->insertInto('permission', [ 'permission_id', 'permission_label' ])
            ->values([ 'user.show', 'Voir les utilisateurs' ])
            ->values([ 'user.edit', 'Voir l’édition les utilisateurs' ])
            ->values([ 'user.update', 'Éditer les utilisateurs' ])
            ->values([ 'user.login', 'Voir le formulaire de connexion' ])
            ->values([ 'user.login.check', 'Se connecter' ])
            ->values([ 'user.logout', 'Se déconnecter' ])
            ->values([ 'user.relogin', 'Voir le formulaire de demande d’un nouveau mot de passe' ])
            ->values([ 'user.relogin.check', 'Demander un nouveau mot de passe' ])
            ->execute();

        $container->query()->insertInto('role_permission', [ 'role_id', 'permission_id' ])
            ->values([ 3, 'user.show' ])
            ->values([ 3, 'user.edit' ])
            ->values([ 3, 'user.update' ])
            ->values([ 2, 'user.logout' ])
            ->values([ 1, 'user.login' ])
            ->values([ 1, 'user.login.check' ])
            ->values([ 1, 'user.relogin' ])
            ->values([ 1, 'user.relogin.check' ])
            ->execute();
    }

    public function hookInstall($container)
    {
        $this->hookInstallMenu($container);
    }

    public function hookInstallMenu($container)
    {
        if ($container->schema()->hasTable('menu')) {
            $container->query()->insertInto('menu_link', [ 'key', 'title_link', 'link',
                    'menu', 'weight', 'parent' ])
                ->values([
                    'user.edit',
                    '<span class="glyphicon glyphicon-user" aria-hidden="true"></span> Utilisateur',
                    'user/1/edit',
                    'admin-menu',
                    4,
                    -1
                ])
                ->values([
                    'user.account',
                    'Mon compte',
                    'user',
                    'user-menu',
                    1,
                    -1
                ])
                ->values([
                    'user.login',
                    'Connexion',
                    'user/login',
                    'user-menu',
                    2,
                    -1
                ])
                ->values([
                    'user.logout',
                    '<span class="glyphicon glyphicon-off" aria-hidden="true"></span> Déconnexion',
                    'user/logout',
                    'user-menu',
                    3,
                    -1
                ])
                ->execute();
        }
    }

    public function uninstall($container)
    {
        if ($container->schema()->hasTable('menu')) {
            $container->query()
                ->from('menu_link')
                ->delete()
                ->regex('link', '/^user/')
                ->execute();
        }
        // Table pivot
        $container->schema()->dropTable('user_role');
        $container->schema()->dropTable('role_permission');
        // Table référentes
        $container->schema()->dropTable('user');
        $container->schema()->dropTable('role');
        $container->schema()->dropTable('permission');
    }
}