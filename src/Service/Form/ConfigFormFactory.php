<?php
namespace BlocksDisposition\Service\Form;

use BlocksDisposition\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Omeka\Module\Manager as ModuleManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $activeModules = $container->get('Omeka\ModuleManager')
            ->getModulesByState(ModuleManager::STATE_ACTIVE);

        $activeModulesArray = [];

        foreach ($activeModules as $key => $val) {
            if ($key !== 'BlocksDisposition') {
                $activeModulesArray[$key] = $key;
            }
        }

        $form = new ConfigForm(null, $options);
        return $form
            ->setModules($activeModulesArray);
    }
}
