<?php

return [
    'user' => [
        'class' => 'SoosyzeCore\User\Services\User',
        'arguments' => ['@core', '@query', '@router'],
        'hooks' => [
            'app.granted' => 'isGranted',
            'app.granted.route' => 'isGrantedRoute',
            'app.response.before' => 'hookResponseBefore',
            'app.response.after' => 'hookResponseAfter'
        ]
    ],
    'auth' => [
        'class' => 'SoosyzeCore\User\Services\Auth',
        'arguments' => ['@query']
    ],
    'user.extend' => [
        'class' => 'SoosyzeCore\User\Extend',
        'hooks' => [
            'install.menu' => 'hookInstallMenu'
        ]
    ],
    'user.hook.api.route' => [
        'class' => 'SoosyzeCore\User\Hook\ApiRoute',
        'arguments' => ['@config', '@router'],
        'hooks' => [
            'api.route' => 'hookApiRoute'
        ]
    ],
    'user.hook.user' => [
        'class' => 'SoosyzeCore\User\Hook\User',
        'arguments' => ['@config', '@user'],
        'hooks' => [
            'user.permission.module' => 'hookPermission',
            'route.user.permission.admin' => 'hookPermissionAdminister',
            'route.user.permission.update' => 'hookPermissionAdminister',
            'route.user.admin' => 'hookPeopleAdminister',
            'route.user.filter' => 'hookPeopleAdminister',
            'route.user.role.admin' => 'hookPeopleAdminister',
            'route.user.role.create' => 'hookPeopleAdminister',
            'route.user.role.store' => 'hookPeopleAdminister',
            'route.user.role.edit' => 'hookPeopleAdminister',
            'route.user.role.update' => 'hookPeopleAdminister',
            'route.user.role.remove' => 'hookPeopleAdminister',
            'route.user.role.delete' => 'hookPeopleAdminister',
            'route.user.account' => 'hookLogout',
            'route.user.show' => 'hookUserShow',
            'route.user.create' => 'hookPeopleAdminister',
            'route.user.store' => 'hookPeopleAdminister',
            'route.user.edit' => 'hookUserEdited',
            'route.user.update' => 'hookUserEdited',
            'route.user.remove' => 'hookUserDeleted',
            'route.user.delete' => 'hookUserDeleted',
            'route.user.register.create' => 'hookRegister',
            'route.user.register.store' => 'hookRegister',
            'route.user.activate' => 'hookActivate',
            'route.user.login' => 'hookLogin',
            'route.user.login.check' => 'hookLoginCheck',
            'route.user.logout' => 'hookLogout',
            'route.user.relogin' => 'hookRelogin',
            'route.user.relogin.check' => 'hookRelogin'
        ]
    ],
    'user.hook.config' => [
        'class' => 'SoosyzeCore\User\Hook\Config',
        'arguments' => ['@router'],
        'hooks' => [
            'config.edit.menu' => 'menu'
        ]
    ]
];