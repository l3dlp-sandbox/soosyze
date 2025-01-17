<?php

use Soosyze\Components\Router\RouteCollection;
use Soosyze\Components\Router\RouteGroup;

define('BLOCK_WITHS_THEME', [
    'theme' => 'public|admin'
]);

RouteCollection::setNamespace('SoosyzeCore\Block\Controller')->name('block.')->group(function (RouteGroup $r): void {
    $r->setNamespace('\Section')->name('section.')->prefix('/admin')->withs(BLOCK_WITHS_THEME)->group(function (RouteGroup $r): void {
        $r->get('admin', '/theme/{theme}/section', '@admin');
        $r->post('update', '/section/{id}/edit', '@update')->whereDigits('id');
    });
    $r->setNamespace('\Block')->prefix('/block')->withs(BLOCK_WITHS_THEME)->group(function (RouteGroup $r): void {
        $r->get('create.list', '/{theme}/create/{section}', '@createList')->whereWords('section');
        $r->get('create.show', '/create/{id}', '@createShow', [ 'id' => '[\w\.\-]+' ]);
        $r->post('create.form', '/{theme}/create/form', '@createForm');

        $r->post('store', '/{theme}', '@store');
        $r->get('edit', '/{theme}/{id}/edit', '@edit')->whereDigits('id');
        $r->put('update', '/{theme}/{id}', '@update')->whereDigits('id');
        $r->get('remove', '/{theme}/{id}/delete', '@remove')->whereDigits('id');
        $r->delete('delete', '/{theme}/{id}', '@delete')->whereDigits('id');
    });
});
