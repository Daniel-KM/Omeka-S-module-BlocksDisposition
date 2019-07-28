<?php
namespace BlocksDisposition\Service\Form;

use BlocksDisposition\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $activeModules = $container->get('Omeka\ModuleManager')
            ->getModulesByState(\Omeka\Module\Manager::STATE_ACTIVE);
        unset($activeModules['BlocksDisposition']);

        $activeModules = array_combine(array_keys($activeModules), array_map(function($v) {
            return $v->getName();
        }, $activeModules));

        $form = new ConfigForm(null, $options);
        return $form
            ->setModules($activeModules);
    }
}
