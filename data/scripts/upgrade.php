<?php declare(strict_types=1);

namespace BlocksDisposition;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.2.0', '<')) {
    $settings->delete('blocksdisposition_modules');
}

if (version_compare($oldVersion, '3.4.3', '<')) {
    if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.51')) {
        $message = new Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            'Common', '3.4.51'
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
    }
}
