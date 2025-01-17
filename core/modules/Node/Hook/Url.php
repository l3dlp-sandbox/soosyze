<?php

declare(strict_types=1);

namespace SoosyzeCore\Node\Hook;

use Soosyze\Components\Form\FormGroupBuilder;
use Soosyze\Components\Util\Util;
use Soosyze\Components\Validator\Validator;
use Soosyze\Config;
use SoosyzeCore\QueryBuilder\Services\Query;
use SoosyzeCore\QueryBuilder\Services\Schema;
use SoosyzeCore\System\Services\Alias;

class Url
{
    /**
     * @var Alias
     */
    private $alias;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var Schema
     */
    private $schema;

    public function __construct(Alias $alias, Config $config, Query $query, Schema $schema)
    {
        $this->alias  = $alias;
        $this->config = $config;
        $this->query  = $query;
        $this->schema = $schema;
    }

    public function hookCreateFormData(array &$data): void
    {
        $data[ 'meta_url' ] = '';
    }

    public function hookEditFormData(array &$data, int $idNode): void
    {
        $data[ 'meta_url' ] = $this->alias->getAlias('node/' . $idNode, '');
    }

    public function hookCreateForm(FormGroupBuilder $form, array $data): void
    {
        $form->before('seo-fieldset', function ($form) use ($data) {
            $form->group('url-fieldset', 'fieldset', function ($form) use ($data) {
                $form->legend('meta_url', t('URL alias'))
                    ->group('meta_url-group', 'div', function ($form) use ($data) {
                        $form->label('meta_url-label', t('Url'), [
                            'data-tooltip' => t('Leave blank to automatically generate your URL')
                        ])->text('meta_url', [
                            'class'       => 'form-control',
                            'placeholder' => 'page/titre-de-mon-contenu',
                            'value'       => $data[ 'meta_url' ]
                        ]);
                    }, [ 'class' => 'form-group' ]);
            }, [
                'class' => 'tab-pane fade',
                'id'    => 'url-fieldset'
            ]);
        });
    }

    public function hookStoreValidator(Validator $validator): void
    {
        /* Caractère : pour les variables autorisées. */
        $validator->addRule('meta_url', '!required|string|max:255|regex:/^[-:\w\d_\/]+$/')
            ->addLabel('meta_url', t('Url'))
            ->addMessage('meta_url', [
                'regex' => [
                    'must' => t('The: label field must contain allowed variables, alphanumeric characters, slashes (/), hyphens (-) or underscores (_).')
                ]
            ]);
    }

    public function hookStoreAfter(Validator $validator): void
    {
        if (($alias = $this->makeAlias($validator)) === '') {
            return;
        }
        $id = $this->schema->getIncrement('node');
        $this->query
            ->insertInto('system_alias_url', [ 'source', 'alias' ])
            ->values([ 'node/' . $id, $alias ])
            ->execute();
    }

    public function hookUpdateValid(Validator $validator, int $idNode): void
    {
        if (!($alias = $this->makeAlias($validator))) {
            $this->query
                ->delete()
                ->from('system_alias_url')
                ->where('alias', '=', 'node/' . $idNode)
                ->execute();
        } elseif ($link = $this->alias->getAlias('node/' . $idNode)) {
            $this->query
                ->update('system_alias_url', [ 'alias' => $alias ])
                ->where('source', '=', 'node/' . $idNode)
                ->execute();
        } else {
            $this->query
                ->insertInto('system_alias_url', [ 'source', 'alias' ])
                ->values([ 'node/' . $idNode, $alias ])
                ->execute();
        }
    }

    public function hookDeleteValid(Validator $validator, int $idNode): void
    {
        $this->query->from('system_alias_url')
            ->where('source', '=', 'node/' . $idNode)
            ->delete()
            ->execute();
    }

    private function tryDateTime(string $datetime): \DateTimeInterface
    {
        $date = (new \DateTime($datetime))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        if ($date == false) {
            throw new \InvalidArgumentException();
        }

        return $date;
    }

    private function makeAlias(Validator $validator): string
    {
        $metaUrl = $validator->getInput('meta_url') !== ''
            ? $validator->getInput('meta_url')
            : $this->config->get('settings.node_url_' . $validator->getInput('type'));

        /** @phpstan-var string $urlDefault */
        $urlDefault = empty($metaUrl)
            ? $this->config->get('settings.node_default_url', '')
            : $metaUrl;

        if ($urlDefault === '') {
            return '';
        }

        $dateTime = self::tryDateTime($validator->getInputString('date_created'));

        $alias = str_replace(
            [
                ':date_created_year',
                ':date_created_month',
                ':date_created_day',
                ':node_title',
                ':node_type'
            ],
            [
                $dateTime->format('Y'),
                $dateTime->format('m'),
                $dateTime->format('d'),
                $validator->getInput('title'),
                $validator->getInput('type')
            ],
            $urlDefault
        );

        return Util::strSlug($alias, '-', '\/');
    }
}
