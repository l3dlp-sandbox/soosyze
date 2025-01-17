<?php

use Soosyze\Components\Router\RouteCollection;
use Soosyze\Components\Router\RouteGroup;

RouteCollection::setNamespace('SoosyzeCore\Dashboard\Controller\Dashboard')->name('dashboard.')->prefix('/admin/dashboard')->group(function (RouteGroup $r): void {
    $r->get('index', '/', '@index');
    $r->get('info', '/info', '@info');
});
