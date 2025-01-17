<?php

declare(strict_types=1);

namespace SoosyzeCore\System\Form;

use SoosyzeCore\FileSystem\Services\File;

class FormThemePublic extends \Soosyze\Components\Form\FormBuilder
{
    public const FAVICON = '';

    public const LOGO = '';

    /**
     * @var array
     */
    protected $values = [
        'favicon' => self::FAVICON,
        'logo'    => self::LOGO
    ];

    /**
     * @var array
     */
    private static $attrGrp = [ 'class' => 'form-group' ];

    /**
     * @var File
     */
    private $file;

    public function __construct(array $attr, File $file)
    {
        parent::__construct($attr);
        $this->file = $file;
    }

    public function makeFields(): self
    {
        return $this->group('theme-fieldset', 'fieldset', function ($form) {
            $form->legend('theme-legend', t('Settings'))
                    ->group('logo-group', 'div', function ($form) {
                        $form->label('logo-label', t('Logo'), [
                            'data-tooltip' => '200ko maximum.',
                            'for'          => 'logo'
                        ]);
                        $this->file->inputFile('logo', $form, $this->values[ 'logo' ]);
                    }, self::$attrGrp)
                    ->group('group-favicon', 'div', function ($form) {
                        $form->label('favicon-label', t('Favicon'), [
                            'data-tooltip' => t('Image to the left of the title of your browser window.'),
                            'for'          => 'favicon'
                        ]);
                        $this->file->inputFile('favicon', $form, $this->values[ 'favicon' ]);
                        $form->html('favicon-info-size', '<p:attr>:content</p>', [
                            ':content' => t('The file must weigh less than 100 KB.')
                        ])->html('favicon-info-dimensions', '<p:attr>:content</p>', [
                            ':content' => t('The width and height min and max: 16px and 310px.')
                        ]);
                    }, self::$attrGrp);
        })
                ->group('submit-group', 'div', function ($form) {
                    $form->token('setting_theme')
                    ->submit('submit', t('Save'), [ 'class' => 'btn btn-success' ])
                    ->html('cancel', '<button:attr>:content</button>', [
                        ':content' => t('Cancel'),
                        'class'    => 'btn btn-danger',
                        'onclick'  => 'javascript:history.back();',
                        'type'     => 'button'
                    ]);
                });
    }
}
