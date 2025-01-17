<?php

return [
    'filemanager' => [
        'class' => 'SoosyzeCore\FileManager\Services\FileManager'
    ],
    'fileprofil' => [
        'class' => 'SoosyzeCore\FileManager\Services\FileProfil'
    ],
    'filemanager.filter.iterator' => [
        'class' => 'SoosyzeCore\FileManager\Services\FilterManagerIterator'
    ],
    'filemanager.extend' => [
        'class' => 'SoosyzeCore\FileManager\Extend',
        'hooks' => [
            'install.user' => 'hookInstallUser',
            'install.menu' => 'hookInstallMenu'
        ]
    ],
    'filemanager.hook.api.route' => [
        'class' => 'SoosyzeCore\FileManager\Hook\ApiRoute',
        'hooks' => [
            'api.route' => 'apiRoute'
        ]
    ],
    'filemanager.hook.app' => [
        'class' => 'SoosyzeCore\FileManager\Hook\App',
        'hooks' => [
            'app.response.after' => 'hookResponseAfter'
        ]
    ],
    'filemanager.hook.config' => [
        'class' => 'SoosyzeCore\FileManager\Hook\Config',
        'hooks' => [
            'config.edit.menu' => 'menu'
        ]
    ],
    'filemanager.hook.menu' => [
        'class' => 'SoosyzeCore\FileManager\Hook\Menu',
        'hooks' => [
            'user.submenu' => 'hookUsersMenu',
            'user.manager.submenu' => 'hookUserManagerSubmenu'
        ]
    ],
    'filemanager.hook.user' => [
        'class' => 'SoosyzeCore\FileManager\Hook\User',
        'hooks' => [
            'user.permission.module' => 'hookUserPermissionModule',
            'route.filemanager.permission.admin' => 'hookFileAdmin',
            'route.filemanager.permission.admin.check' => 'hookFileAdmin',
            'route.filemanager.permission.create' => 'hookFileAdmin',
            'route.filemanager.permission.store' => 'hookFileAdmin',
            'route.filemanager.permission.edit' => 'hookFileAdmin',
            'route.filemanager.permission.update' => 'hookFileAdmin',
            'route.filemanager.permission.remove' => 'hookFileAdmin',
            'route.filemanager.permission.delete' => 'hookFileAdmin',
            'route.filemanager.admin' => 'hookFolderAdmin',
            'route.filemanager.public' => 'hookFolderShow',
            'route.filemanager.show' => 'hookFolderShow',
            'route.filemanager.filter' => 'hookFolderShow',
            'route.filemanager.file.show' => 'hookFileShow',
            'route.filemanager.file.create' => 'hookFileStore',
            'route.filemanager.file.store' => 'hookFileStore',
            'route.filemanager.file.edit' => 'hookFileUpdate',
            'route.filemanager.file.update' => 'hookFileUpdate',
            'route.filemanager.file.remove' => 'hookFileDelete',
            'route.filemanager.file.delete' => 'hookFileDelete',
            'route.filemanager.file.download' => 'hookFileDownlod',
            'route.filemanager.copy.admin' => 'hookFileAdmin',
            'route.filemanager.copy.update' => 'hookFileCopy',
            'route.filemanager.copy.show' => 'hookFolderShow',
            'route.filemanager.folder.create' => 'hookFolderStore',
            'route.filemanager.folder.store' => 'hookFolderStore',
            'route.filemanager.folder.edit' => 'hookFolderUpdate',
            'route.filemanager.folder.update' => 'hookFolderUpdate',
            'route.filemanager.folder.remove' => 'hookFolderDelete',
            'route.filemanager.folder.delete' => 'hookFolderDelete',
            'route.filemanager.folder.download' => 'hookFolderDownload'
        ]
    ],
    'filemanager.hook.role' => [
        'class' => 'SoosyzeCore\FileManager\Hook\Role',
        'hooks' => [
            'role.delete.before' => 'hookRoleDeleteBefore'
        ]
    ]
];
