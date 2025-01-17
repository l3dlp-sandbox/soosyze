<?php

declare(strict_types=1);

namespace SoosyzeCore\Block\Hook;

use Core;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Soosyze\Components\Router\Router;
use SoosyzeCore\Block\Services\Block;
use SoosyzeCore\QueryBuilder\Services\Query;
use SoosyzeCore\Template\Services\Templating;
use SoosyzeCore\User\Services\User;

/**
 * @phpstan-import-type BlockEntity from \SoosyzeCore\Block\Extend
 */
class App
{
    /**
     * @var Core
     */
    private $core;

    /**
     * @var Block
     */
    private $block;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var string
     */
    private $pathViews;

    /**
     * @var array
     */
    private $roles = [];

    /**
     * @var Router
     */
    private $router;

    /**
     * @var Templating
     */
    private $tpl;

    /**
     * Données de l'utilisateur courant.
     *
     * @var array|null
     */
    private $userCurrent;

    public function __construct(Core $core, Block $block, Query $query, Router $router, Templating $template, User $user)
    {
        $this->core   = $core;
        $this->block  = $block;
        $this->query  = $query;
        $this->router = $router;
        $this->tpl    = $template;
        $this->block  = $block;

        $this->pathViews = dirname(__DIR__) . '/Views/';

        $this->userCurrent = $user->isConnected();

        if ($this->userCurrent) {
            $this->roles   = $user->getRolesUser($this->userCurrent[ 'user_id' ]);
            $this->roles[] = [ 'role_id' => 2 ];
        }
    }

    public function hookResponseAfter(RequestInterface $request, ResponseInterface &$response): void
    {
        if (!($response instanceof Templating) || $response->getStatusCode() !== 200) {
            return;
        }

        $theme   = $this->getNameTheme();
        $isAdmin = $this->isAdmin();

        $blocks = $this->getBlocks($theme, $isAdmin);

        $sections = $this->tpl->getSections();

        foreach ($sections as $section) {
            $response->make('page.' . $section, 'section.php', $this->pathViews, [
                'section_id'  => $section,
                'content'     => $blocks[ $section ] ?? [],
                'is_admin'    => $isAdmin,
                'link_create' => ($isAdmin && ($section !== 'main_menu' || ($section === 'main_menu' && empty($blocks['main_menu']))))
                    ? $this->router->generateUrl('block.create.list', [
                        'theme'   => $theme,
                        'section' => $section
                    ])
                    : null
            ]);
        }
    }

    private function isAdmin(): bool
    {
        return in_array($this->router->getPathFromRequest(), [
                '/admin/theme/public/section',
                '/admin/theme/admin/section'
            ]) && $this->core->callHook('app.granted', [ 'block.administer' ]);
    }

    private function getNameTheme(): string
    {
        return $this->tpl->isTheme(Templating::THEME_PUBLIC)
            ? 'public'
            : 'admin';
    }

    private function getBlocks(string $theme, bool $isAdmin): array
    {
        /** @phpstan-var BlockEntity[] $blocks */
        $blocks = $this->query
            ->from('block')
            ->where('theme', '=', $theme)
            ->orderBy('weight')
            ->fetchAll();

        $listBlock = $this->block->getBlocks();

        $out = [];
        foreach ($blocks as $block) {
            if (!$isAdmin && (!$this->isVisibilityPages($block) || !$this->isVisibilityRoles($block))) {
                continue;
            }
            if (!empty($block[ 'key_block' ])) {
                $tplBlock = $this->tpl->createBlock(
                    $listBlock[ $block[ 'key_block' ] ][ 'tpl' ],
                    $listBlock[ $block[ 'key_block' ] ][ 'path' ]
                );

                /* Construit les options avec les option présentent dans le bloc et les données en base. */
                $options = array_merge(
                    $listBlock[ $block[ 'key_block' ] ][ 'options' ] ?? [],
                    $this->block->decodeOptions($block[ 'options' ])
                );

                /** @var string|object $content */
                $content = $this->core->callHook(
                    "block.{$block[ 'hook' ]}",
                    [ $tplBlock, $options ]
                );
                $block[ 'content' ] .= (string) $content;
            }
            if ($isAdmin) {
                $params = [
                    'theme' => $theme,
                    'id'    => $block[ 'block_id' ]
                ];

                $block[ 'link_edit' ]   = $this->router->generateUrl('block.edit', $params);
                $block[ 'link_remove' ] = $this->router->generateUrl('block.remove', $params);
                $block[ 'link_update' ] = $this->router->generateUrl('block.section.update', $params);
                $block[ 'title_admin' ] = empty($block[ 'key_block' ])
                    ? ''
                    : $listBlock[ $block[ 'key_block' ] ][ 'title' ] ?? '';
            }
            $out[ $block[ 'section' ] ][] = $block;
        }

        return $out;
    }

    private function isVisibilityPages(array $block): bool
    {
        $path = $this->router->getPathFromRequest();

        $visibility = $block[ 'visibility_pages' ];
        $pages      = explode(PHP_EOL, $block[ 'pages' ]);

        foreach ($pages as $page) {
            $page = trim($page);
            if ($page === $path) {
                return $visibility;
            }
            $str     = preg_quote($page, '/');
            $pattern = strtr($str, [ '%' => '.*' ]);
            if (preg_match("/^$pattern$/", $path)) {
                return $visibility;
            }
        }

        return !$visibility;
    }

    private function isVisibilityRoles(array $block): bool
    {
        $rolesBlock  = explode(',', $block[ 'roles' ]);
        $visibility  = $block[ 'visibility_roles' ];

        /* S'il n'y a pas d'utilisateur et que l'on demande de suivre les utilisateurs non connectés. */
        if (!$this->userCurrent && in_array(1, $rolesBlock)) {
            return $visibility;
        }

        foreach ($rolesBlock as $analyticsRole) {
            foreach ($this->roles as $role) {
                if ($analyticsRole == $role[ 'role_id' ]) {
                    return $visibility;
                }
            }
        }

        return !$visibility;
    }
}
