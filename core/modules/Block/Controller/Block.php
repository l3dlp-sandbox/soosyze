<?php

namespace SoosyzeCore\Block\Controller;

use Soosyze\Components\Form\FormBuilder;
use Soosyze\Components\Validator\Validator;

class Block extends \Soosyze\Controller
{
    protected $blocks = [
        'button'  => [ 'title' => 'Text avec boutton', 'path' => 'block-button.php' ],
        'card_ui' => [ 'title' => 'Card UI simple', 'path' => 'block-card_ui.php' ],
        'code'    => [ 'title' => 'Code', 'path' => 'block-code.php' ],
        'contact' => [ 'title' => 'Contact', 'path' => 'block-contact.php' ],
        'gallery' => [ 'title' => 'Gallerie d\'image', 'path' => 'block-gallery.php' ],
        'img'     => [ 'title' => 'Image et texte', 'path' => 'block-img.php' ],
        'map'     => [ 'title' => 'Carte', 'path' => 'block-map.php' ],
        'video'   => [ 'title' => 'Vidéo', 'path' => 'block-peertube.php'],
        'social'  => [ 'title' => 'Réseaux sociaux', 'path' => 'block-social.php' ],
        'table'   => [ 'title' => 'Table', 'path' => 'block-table.php' ],
        'text'    => [ 'title' => 'Text simple', 'path' => 'block-text.php' ],
        'three'   => [ 'title' => '3 colonnes', 'path' => 'block-three.php' ],
    ];

    public function __construct()
    {
        $this->pathServices = dirname(__DIR__) . '/Config/service.json';
        $this->pathRoutes   = dirname(__DIR__) . '/Config/routing.json';
        $this->pathViews    = dirname(__DIR__) . '/Views/';
    }

    public function show($id, $req)
    {
        if (!($block = self::query()->from('block')->where('block_id', '==', $id)->fetch())) {
            return $this->get404($req);
        }

        $block[ 'link_edit' ]   = self::router()->getRoute('block.edit', [ ':id' => $id ]);
        $block[ 'link_delete' ] = self::router()->getRoute('block.delete', [ ':id' => $id ]);
        $block[ 'link_update' ] = self::router()->getRoute('block.update', [ ':id' => $id ]);

        return self::template()
                ->createBlock('block-show.php', $this->pathViews)
                ->addVars([ 'block' => $block ]);
    }

    public function create($section)
    {
        $form = new FormBuilder([
            'method' => 'POST',
            'action' => self::router()->getRoute('block.store', [ ':section' => $section ])
        ]);
        foreach ($this->blocks as $key => &$block) {
            $form->group('radio-' . $key, 'div', function ($form) use ($key, $block) {
                $form->radio('type_block', [
                        'id' => "type_block-$key",
                        'value' => $key
                    ])
                    ->html($key, '<div:attr:css>:_content</div>', [
                        'class'    => 'block-content',
                        '_content' => (string) self::template()
                        ->createBlock($block[ 'path' ], $this->pathViews . 'blocks/')
                        ->addVars([
                            'src_image' => self::core()->getPath('modules') . '/Block/Assets/static.svg'
                        ])
                ]);
            });
        }
        $form->token($section)
            ->submit('submit', 'Ajouter', [ 'class' => 'btn btn-success' ]);

        return self::template()
                ->createBlock('block-create.php', $this->pathViews)
                ->addVars([
                    'section' => $section,
                    'blocks'  => $this->blocks,
                    'form'    => $form
        ]);
    }

    public function store($section, $req)
    {
        $validator = (new Validator())
            ->setRules([
                'type_block' => 'required|string|max:255',
                $section     => 'token'
            ])
            ->setInputs($req->getParsedBody());

        if ($validator->isValid()) {
            $type    = $validator->getInput('type_block');
            $content = (string) self::template()
                    ->createBlock($this->blocks[ $type ][ 'path' ], $this->pathViews . 'blocks/')
                    ->addVars([
                        'src_image' => self::core()->getPath('modules') . '/Block/Assets/static.svg'
            ]);
            $value   = [
                'section'          => $section,
                'title'            => 'Titre bloc',
                'content'          => $content,
                'weight'           => 1,
                'visibility_roles' => true,
                'roles'            => '1,2'
            ];
            self::query()
                ->insertInto('block', array_keys($value))
                ->values($value)
                ->execute();
        }
        $route = self::router()->getRoute('section.admin', [ ':theme' => 'theme' ]);

        return new \Soosyze\Components\Http\Redirect($route);
    }

    public function edit($id, $req)
    {
        $data = self::query()->from('block')->where('block_id', '==', $id)->fetch();

        $action = self::router()->getRoute('block.update', [ ':id' => $data[ 'block_id' ] ]);
        $form   = (new FormBuilder([ 'method' => 'post', 'action' => $action ]))
            ->group('menu-link-fieldset', 'fieldset', function ($form) use ($data) {
                $form->legend('menu-link-legend', 'Éditer le bloc')
                ->group('title-group', 'div', function ($form) use ($data) {
                    $form->text('title', [
                        'class'       => 'form-control',
                        'maxlength'   => 255,
                        'placeholder' => 'Titre',
                        'required'    => 1,
                        'value'       => $data[ 'title' ]
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('content-group', 'div', function ($form) use ($data) {
                    $form->textarea('content', $data[ 'content' ], [
                        'class'       => 'form-control editor',
                        'placeholder' => '<p>Hello World!</p>',
                        'required'    => 1,
                        'rows'        => 8
                    ]);
                }, [ 'class' => 'form-group' ]);
            })
            ->group('page-fieldset', 'fieldset', function ($form) use ($data) {
                $form->legend('page-legend', 'Visibilité par pages')
                ->group('visibility-group', 'div', function ($form) use ($data) {
                    $form->radio('visibility_pages', [
                        'checked'  => !$data[ 'visibility_pages' ],
                        'id'       => 'visibility1',
                        'required' => 1,
                        'value'    => 0
                    ])->label('visibility_pages-label', 'Cacher le bloc aux pages listées', [
                        'for' => 'visibility1'
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('visibility1-group', 'div', function ($form) use ($data) {
                    $form->radio('visibility_pages', [
                        'checked'  => $data[ 'visibility_pages' ],
                        'id'       => 'visibility2',
                        'required' => 1,
                        'value'    => 1
                    ])->label('visibility_pages-label', 'Afficher le bloc aux pages listées', [
                        'for' => 'visibility2'
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('url-group', 'div', function ($form) use ($data) {
                    $form->label('url-label', 'Liste des pages', [
                        'data-tooltip' => 'Saisir un chemin par ligne. Le caractère «%» est un caractère de remplacement spécifiant tous les caractères.'
                    ])
                    ->textarea('pages', $data[ 'pages' ], [
                        'class'       => 'form-control',
                        'placeholder' => 'admin' . PHP_EOL . 'admin/*',
                        'rows'        => 5
                    ]);
                }, [ 'class' => 'form-group' ]);
            })
            ->group('roles-fieldset', 'fieldset', function ($form) use ($data) {
                $form->legend('role-legend', 'Visibilité par rôles')
                ->group('visibility-group', 'div', function ($form) use ($data) {
                    $form->radio('visibility_roles', [
                        'checked'  => !$data[ 'visibility_roles' ],
                        'id'       => 'visibility3',
                        'required' => 1,
                        'value'    => 0
                    ])->label('visibility_roles-label', 'Cacher le bloc aux rôles sélectionnés', [
                        'for' => 'visibility3'
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('visibility1-group', 'div', function ($form) use ($data) {
                    $form->radio('visibility_roles', [
                        'checked'  => $data[ 'visibility_roles' ],
                        'id'       => 'visibility4',
                        'required' => 1,
                        'value'    => 1
                    ])->label('visibility_roles-label', 'Afficher le bloc aux rôles sélectionnés', [
                        'for' => 'visibility4'
                    ]);
                }, [ 'class' => 'form-group' ]);
                $data[ 'roles' ] = explode(',', $data[ 'roles' ]);
                foreach (self::user()->getRoles() as $role) {
                    $form->group("roles-{$role[ 'role_id' ]}-group", 'div', function ($form) use ($data, $role) {
                        $form->checkbox("roles-{$role[ 'role_id' ]}", [
                            'value'   => $role[ 'role_id' ],
                            'checked' => \in_array($role[ 'role_id' ], $data[ 'roles' ])
                        ])
                        ->label('roles-label', '<i class="ui" aria-hidden="true"></i>' . $role[ 'role_label' ], [
                            'for' => "roles-{$role[ 'role_id' ]}"
                        ]);
                    }, [ 'class' => 'form-group' ]);
                }
            })
            ->token('token_link_create')
            ->submit('submit_save', 'Enregistrer', [ 'class' => 'btn btn-success' ])
            ->submit('submit_cancel', 'Cancel', [ 'class' => 'btn btn-default' ]);

        return self::template()
                ->createBlock('block-form.php', $this->pathViews)
                ->addVars([
                    'form'      => $form,
                    'link_show' => self::router()->getRoute('block.show', [ ':id' => $data[ 'block_id' ] ])
        ]);
    }

    public function update($id, $req)
    {
        if (!self::query()->from('block')->where('block_id', '==', $id)->fetch()) {
            return $this->get404($req);
        }

        $validator = (new Validator())
            ->setRules([
                'title'            => '!required|string|max:255',
                'content'          => '!required|string|max:5000',
                'visibility_pages' => 'bool',
                'pages'            => '!required|string|htmlsc',
                'visibility_roles' => 'bool'
            ])
            ->setInputs($req->getParsedBody());
        foreach (self::user()->getRoles() as $role) {
            $validator->addRule("roles-{$role[ 'role_id' ]}", 'string');
        }

        if ($validator->isValid()) {
            $roles = [];
            foreach (self::user()->getRoles() as $role) {
                if ($validator->getInput("roles-{$role[ 'role_id' ]}")) {
                    $roles[] = $role[ 'role_id' ];
                }
            }
            $value = [
                'title'            => $validator->getInput('title'),
                'content'          => $validator->getInput('content'),
                'visibility_pages' => (bool) $validator->getInput('visibility_pages'),
                'pages'            => $validator->getInput('pages'),
                'visibility_roles' => (bool) $validator->getInput('visibility_roles'),
                'roles'            => implode(',', $roles)
            ];

            self::query()
                ->update('block', $value)
                ->where('block_id', '==', $id)
                ->execute();
        } else {
            return $this->edit($id, $req);
        }

        return $this->show($id, $req);
    }

    public function delete($id, $req)
    {
        if (!self::query()->from('block')->where('block_id', '==', $id)->fetch()) {
            return $this->get404($req);
        }

        self::query()->from('block')->where('block_id', '==', $id)->delete()->execute();
    }
}
