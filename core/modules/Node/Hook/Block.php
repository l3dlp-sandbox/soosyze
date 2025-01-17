<?php

declare(strict_types=1);

namespace SoosyzeCore\Node\Hook;

use Soosyze\Components\Form\FormGroupBuilder;
use Soosyze\Components\Router\Router;
use Soosyze\Components\Validator\Validator;
use SoosyzeCore\Node\Services\Node;
use SoosyzeCore\QueryBuilder\Services\Query;
use SoosyzeCore\System\Services\Alias;
use SoosyzeCore\Template\Services\Block as ServiceBlock;

/**
 * @phpstan-import-type NodeEntity from \SoosyzeCore\Node\Extend
 * @phpstan-import-type NodeTypeEntity from \SoosyzeCore\Node\Extend
 */
class Block implements \SoosyzeCore\Block\BlockInterface
{
    private const DISPLAY_DEFAULT = 'meta-title';

    private const NEXT_TEXT_DEFAULT = 'Next :node_type_name';

    private const PREVIOUS_TEXT_DEFAULT = 'Previous :node_type_name';

    private const TYPE_DEFAULT = 'page';

    /**
     * @var Alias
     */
    private $alias;

    /**
     * @var Node
     */
    private $node;

    /**
     * @var string
     */
    private $pathViews;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var Router
     */
    private $router;

    public function __construct(Alias $alias, Node $node, Query $query, Router $router)
    {
        $this->alias  = $alias;
        $this->node   = $node;
        $this->query  = $query;
        $this->router = $router;

        $this->pathViews = dirname(__DIR__) . '/Views/';
    }

    public function hookBlockCreateFormData(array &$blocks): void
    {
        $blocks[ 'node.next_previous' ] = [
            'description' => t('Next/previous buttons for content types.'),
            'hook'        => 'node.next_previous',
            'icon'        => 'fas fa-exchange-alt',
            'no_content'  => t('Buttons are displayed when the user browses the content type'),
            'options'     => [
                'display'       => self::DISPLAY_DEFAULT,
                'next_text'     => self::NEXT_TEXT_DEFAULT,
                'previous_text' => self::PREVIOUS_TEXT_DEFAULT,
                'type'          => self::TYPE_DEFAULT
            ],
            'path'        => $this->pathViews,
            'title'       => t('Next/previous navigation'),
            'tpl'         => 'components/block/node-next_previous.php'
        ];
    }

    public function hookBlockNextPrevious(ServiceBlock $tpl, array $options): ServiceBlock
    {
        $node = $this->node->getCurrentNode();
        if ($node === null || $node[ 'type' ] !== $options[ 'type' ]) {
            return $tpl->addVars(['next' => null, 'previous' => null]);
        }

        return $tpl->addVars([
                'display'        => $options[ 'display' ],
                'next'           => $this->getNextNode($node),
                'next_text'      => $options[ 'next_text' ],
                'node_type_name' => $this->getNodeTypeName($options[ 'type' ]),
                'previous'       => $this->getPreviousNode($node),
                'previous_text'  => $options[ 'previous_text' ]
        ]);
    }

    public function hookNodeNextPreviousEditForm(FormGroupBuilder &$form, array $data): void
    {
        $form->group('node-fieldset', 'fieldset', function ($form) use ($data) {
            $form->legend('node-legend', t('Settings'))
                ->group('node-group', 'div', function ($form) use ($data) {
                    $form->label('type-label', t('Content type'))
                    ->select('type', $this->getOptionsType(), [
                        ':selected' => $data[ 'options' ][ 'type' ],
                        'class'     => 'form-control'
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('display-group', 'div', function ($form) use ($data) {
                    $form->label('display-label', t('Display type'))
                    ->select('display', $this->getOptionsDisplay(), [
                        ':selected' => $data[ 'options' ][ 'display' ],
                        'class'     => 'form-control'
                    ]);
                }, [ 'class' => 'form-group' ])
                ->group('previous_text-group', 'div', function ($form) use ($data) {
                    $form->label('previous_text-label', t('Previous text'))
                    ->text('previous_text', [
                        'class'    => 'form-control',
                        'required' => 1,
                        'value'    => t($data[ 'options' ][ 'previous_text' ])
                    ]);
                }, [ 'class' => 'form-group' ])
                ->html('previous_text-info', '<p>:content</p>', [
                    ':content' => t('Variables allowed') .
                    ' <code>:node_type_name</code>'
                ])
                ->group('next_text-group', 'div', function ($form) use ($data) {
                    $form->label('next_text-label', t('Next text'))
                    ->text('next_text', [
                        'class'    => 'form-control',
                        'required' => 1,
                        'value'    => t($data[ 'options' ][ 'next_text' ])
                    ]);
                }, [ 'class' => 'form-group' ])
                ->html('next_text-info', '<p>:content</p>', [
                    ':content' => t('Variables allowed') .
                    ' <code>:node_type_name</code>'
            ]);
        });
    }

    public function hookNodeNextPreviousUpdateValidator(Validator &$validator, int $id): void
    {
        $validator
            ->addRule('display', 'required|inarray:' . $this->getListNameOptionsDisplay())
            ->addRule('next_text', 'required|string|max:255')
            ->addRule('previous_text', 'required|string|max:255')
            ->addRule('type', 'required|inarray:' . implode(',', $this->getListNameOptionsType()));
        $validator
            ->addLabel('display', t('Display type'))
            ->addLabel('next_text', t('Next text'))
            ->addLabel('previous_text', t('Previous text'))
            ->addLabel('type', t('Content type'));
    }

    public function hookNodeNextPreviousUpdateBefore(Validator $validator, array &$values, int $id): void
    {
        $values[ 'options' ] = json_encode([
            'display'       => $validator->getInput('display'),
            'next_text'     => $validator->getInput('next_text'),
            'previous_text' => $validator->getInput('previous_text'),
            'type'          => $validator->getInput('type')
        ]);
    }

    private function getNodeTypeName(string $type): string
    {
        /** @phpstan-var NodeTypeEntity|null $nodeType */
        $nodeType = $this->query
            ->from('node_type')
            ->where('node_type', '=', $type)
            ->fetch();

        return $nodeType[ 'node_type' ] ?? '';
    }

    private function getNextNode(array $node): ?array
    {
        /** @phpstan-var NodeEntity|null $next */
        $next = $this->query
            ->from('node')
            ->whereGroup(static function ($query) use ($node) {
                return $query
                    ->where('date_created', '>=', $node[ 'date_created' ])
                    ->where('id', '>', $node[ 'id' ]);
            })
            ->where('type', '=', $node[ 'type' ])
            ->where('id', '!=', $node[ 'id' ])
            ->where('node_status_id', '=', 1)
            ->orderBy('date_created')
            ->fetch();

        if ($next !== null) {
            /** @var string $linkNext */
            $linkNext = $this->alias->getAlias('node/' . $next[ 'id' ], 'node/' . $next[ 'id' ]);

            $next[ 'link' ] = $this->router->makeUrl($linkNext);
        }

        return $next;
    }

    private function getPreviousNode(array $node): ?array
    {
        /** @phpstan-var NodeEntity|null $previous */
        $previous = $this->query
            ->from('node')
            ->whereGroup(static function ($query) use ($node) {
                return $query
                    ->where('date_created', '<=', $node[ 'date_created' ])
                    ->where('id', '<', $node[ 'id' ]);
            })
            ->where('type', '=', $node[ 'type' ])
            ->where('id', '!=', $node[ 'id' ])
            ->where('node_status_id', '=', 1)
            ->orderBy('date_created', SORT_DESC)
            ->fetch();

        if ($previous !== null) {
            /** @var string $linkPrevious */
            $linkPrevious = $this->alias->getAlias('node/' . $previous[ 'id' ], 'node/' . $previous[ 'id' ]);

            $previous[ 'link' ] = $this->router->makeUrl($linkPrevious);
        }

        return $previous;
    }

    private function getOptionsType(): array
    {
        /** @phpstan-var array<NodeTypeEntity> $nodeTypes */
        $nodeTypes = $this->query
            ->from('node_type')
            ->orderBy('node_type_name')
            ->fetchAll();

        $out = [];
        foreach ($nodeTypes as $type) {
            $out[] = [
                'label' => t($type[ 'node_type_name' ]),
                'value' => $type[ 'node_type' ]
            ];
        }

        return $out;
    }

    private function getOptionsDisplay(): array
    {
        return [
            [
                'label' => t('Buttons next/previous'),
                'value' => 'meta'
            ], [
                'label' => t('Content names'),
                'value' => 'title'
            ], [
                'label' => t('Names of contents and next/previous buttons'),
                'value' => 'meta-title'
            ],
        ];
    }

    private function getListNameOptionsType(): array
    {
        $nodeTypes = $this->query
            ->from('node_type')
            ->fetchAll();

        return $nodeTypes === []
            ? []
            : array_column($nodeTypes, 'node_type');
    }

    private function getListNameOptionsDisplay(): string
    {
        return 'meta,title,meta-title';
    }
}
