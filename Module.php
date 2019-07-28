<?php
/**
 * Blocks Disposition
 *
 * Manage automatic display of features of the modules in the resource pages.
 *
 * @copyright Daniel Berthereau, 2019
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace BlocksDisposition;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Omeka\Settings\SettingsInterface;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.before',
            [$this, 'handleViewShowBeforeItem'],
            -1000
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.browse.before',
            [$this, 'handleViewBrowseBeforeItem'],
            -1000
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.browse.before',
            [$this, 'handleViewBrowseBeforeItemSet'],
            -1000
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.before',
            [$this, 'handleViewShowBeforeItemSet'],
            -1000
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.before',
            [$this, 'handleViewShowBeforeMedia'],
            -1000
        );
    }

    public function rewriteListeners($identifier, $eventName, $site_settings)
    {
        $sharedEventManager = $this->getServiceLocator()->get('SharedEventManager');

        parent::attachListeners($sharedEventManager);

        $listenersByEvent = [];
        $listenersByEvent[$eventName] = $sharedEventManager->getListeners([$identifier], $eventName);

        $services = $this->getServiceLocator();

        $siteSettings = $services->get('Omeka\Settings\Site');
        $currentSiteSlug = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('site-slug');

        $api = $services->get('Omeka\ApiManager');
        $sites = $api->search('sites', ['slug' => $currentSiteSlug])->getContent();

        $siteSettings->setTargetId($sites[0]->id());

        $blocksdisposition_modules = explode(',', $siteSettings->get($site_settings, 'site_settings'));
        $blocksdisposition_modules = json_decode(implode(',', $blocksdisposition_modules));

        $listenersByEventViewShowAfter = [];

        foreach ($listenersByEvent as $listeners) {
            foreach ($listeners as $listener) {
                foreach ($listener as $list) {
                    foreach ($list as $val) {
                        if (is_object($val)) {
                            $module_name = explode('\\', (new \ReflectionClass($val))->getName());
                        }
                    }

                    $sharedEventManager->detach($list, $identifier, $eventName);

                    if (isset($module_name[0])) {
                        $listenersByEventViewShowAfter[$module_name[0]] = $list;
                    }
                }
            }
        }

        if (isset($blocksdisposition_modules)) {
            foreach ($blocksdisposition_modules as $val) {
                if (isset($listenersByEventViewShowAfter[$val][0])) {
                    $sharedEventManager->attach(
                        $identifier,
                        $eventName,
                        [$listenersByEventViewShowAfter[$val][0], $listenersByEventViewShowAfter[$val][1]]
                    );
                }
            }
        }
    }

    public function handleViewShowBeforeItem(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\Item', 'view.show.after', 'blocksdisposition_item_show');
    }

    public function handleViewBrowseBeforeItem(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\Item', 'view.browse.after', 'blocksdisposition_item_browse');
    }

    public function handleViewBrowseBeforeItemSet(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\ItemSet', 'view.browse.after', 'blocksdisposition_item_set_browse');
    }

    public function handleViewShowBeforeItemSet(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\ItemSet', 'view.show.after', 'blocksdisposition_item_set_show');
    }

    public function handleViewShowBeforeMedia(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\Media', 'view.show.after', 'blocksdisposition_media_show');
    }

    public function handleConfigForm(AbstractController $controller)
    {
        if (!parent::handleConfigForm($controller)) {
            return false;
        }

        $this->updateSiteSettings('blocksdisposition_modules');
        return true;
    }

    public function handleSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();

        $settingType = 'config';
        $settings = $services->get('Omeka\Settings');
        $configData = $this->prepareDataToPopulate($settings, $settingType);

        $settingType = 'site_settings';
        $settings = $services->get('Omeka\Settings\Site');

        $data = $this->prepareDataToPopulate($settings, $settingType);

        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$settingType];

        $space = strtolower(__NAMESPACE__);

        $fieldset = new Fieldset();
        $fieldset
            ->setName($space)
            ->setLabel('Blocks Disposition');

        $blocksTitleValue = [
            'For item browse', // @translate
            'For item show', // @translate
            'For item set browse', // @translate
            'For item set show', // @translate
            'For media show', // @translate
        ];

        $blocksTitle = [];
        $i = 0;

        foreach (array_keys($defaultSettings) as $name) {
            $fieldset->add([
                'type' => \Zend\Form\Element\Hidden::class,
                'name' => $name,
                'attributes' => [
                    'value' => 0,
                    'class' => $name,
                ],
            ]);

            $blocksTitle[$name] = $blocksTitleValue[$i];
            ++$i;
        }

        $fieldset
            ->add([
                'type' => \Zend\Form\Element\Hidden::class,
                'name' => 'blocks_title',
                'attributes' => [
                    'value' => json_encode($blocksTitle),
                    'class' => 'blocks_title',
                ],
            ])
            ->add([
                'type' => \Zend\Form\Element\Hidden::class,
                'name' => 'blocksdisposition_modules_from_config',
                'attributes' => [
                    'value' => json_encode($configData['blocksdisposition_modules']),
                    'class' => 'blocksdisposition_modules_from_config',
                ],
            ]);

        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($data);
    }

    /**
     * Update sites settings according to a main setting.
     *
     * @param string $name
     */
    protected function updateSiteSettings($name)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');

        $modules = $settings->get('blocksdisposition_modules', []);

        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $id = $site->id();
            $siteSettings->setTargetId($id);
            $this->initDataToPopulate($siteSettings, 'site_settings', $id);
            $data = $this->prepareDataToPopulate($settings, 'site_settings');
            foreach (array_keys($data) as $name) {
                $moduleBlocks = json_decode($siteSettings->get($name), true) ?: [];
                foreach ($moduleBlocks as $key => $module) {
                    if (!in_array($module, $modules)) {
                        unset($moduleBlocks[$key]);
                    }
                }
                $siteSettings->set($name, json_encode(array_values($moduleBlocks)));
            }
        }
    }
}
