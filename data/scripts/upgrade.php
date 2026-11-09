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
    if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.96')) {
        $message = new Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            'Common', '3.4.96'
        );
        $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
    }
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    // The module is now enabled by site. It was applied to all sites before, a
    // site without list hiding all the modules, so enable it for the sites
    // whose setting was never saved to keep the same display. The first upgrade
    // to 3.4.7 enabled only the sites with a list of modules.
    $siteIds = $connection->executeQuery(
        <<<'SQL'
        SELECT `site`.`id`
        FROM `site`
        LEFT JOIN `site_setting`
            ON `site_setting`.`site_id` = `site`.`id`
            AND `site_setting`.`id` = "blocksdisposition_enabled"
        WHERE `site_setting`.`id` IS NULL
        SQL
    )->fetchFirstColumn();
    $siteSettings = $services->get('Omeka\Settings\Site');
    foreach ($siteIds as $siteId) {
        $siteSettings->set('blocksdisposition_enabled', true, (int) $siteId);
    }

    $message = new Message(
        $translate('The module manages only the modules that display something in the resource pages, so the other features of the modules are kept, for example the "noindex" of the core or the canonical url. It is now enabled by site, and it was enabled for all sites (%d) to keep the same display. A site that does not enable it is not modified, nor the resource pages of a site whose theme manages the resource page blocks.'), // @translate
        count($siteIds)
    );
    $messenger->addSuccess($message);
}
