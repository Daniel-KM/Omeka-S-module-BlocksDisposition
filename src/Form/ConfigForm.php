<?php
namespace BlocksDisposition\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    protected $modules = [];

    public function init()
    {
        $this->add([
                'name' => 'blocksdisposition_modules_settings',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Modules used in resource views', // @translate
                    'info' => 'List all the module that used the trigger "view.show.after" and that may be displayed and ordered.', // @translate
                    'documentation' => 'https://github.com/Daniel-KM/Omeka-S-module-BlocksDisposition',
                    'value_options' => $this->getModules(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'blocksdisposition_modules_settings',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select modules…', // @translate
                ],
            ]);
    }

    /**
     * @param array $modules
     */
    public function setModules(array $modules)
    {
        $this->modules = $modules;
        return $this;
    }

    /**
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }
}
