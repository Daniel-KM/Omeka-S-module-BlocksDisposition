<?php
namespace BlocksDisposition\Form;

use Zend\Form\Form;
use Zend\Form\Element;

class ConfigForm extends Form
{
    protected $modules = [];

    public function init()
    {
        $this->add([
                'name' => 'blocksdisposition_modules_settings',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'BlocksDisposition modules for view', // @translate
                    'info' => 'Read the doc.', // @translate
                    'documentation' => '',
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
