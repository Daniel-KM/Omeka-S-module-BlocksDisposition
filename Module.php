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

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteSettingsFilters']
        );
    }

    /**
     * Enable, disable and reorder modules according to the sie settings.
     *
     * @param string $identifier Event identifier
     * @param string $eventName
     * @param string $siteSettingName
     */
    protected function rewriteListeners($identifier, $eventName, $siteSettingName)
    {
        $services = $this->getServiceLocator();
        $sharedEventManager = $services->get('SharedEventManager');

        parent::attachListeners($sharedEventManager);

        // The current site is automatically set.
        $moduleNames = $services->get('Omeka\Settings\Site')->get($siteSettingName, []);
        $moduleListeners = [];

        // Detach all listeners for the event.
        $listeners = $sharedEventManager->getListeners([$identifier], $eventName);
        foreach ($listeners as $listener) {
            foreach ($listener as $callable) {
                // Only object/method with the namespace of the module are
                // managed, not closures. Nevertheless, closures are kept.
                if (is_array($callable) && is_object($callable[0])) {
                    $moduleName = strtok(get_class($callable[0]), '\\');
                    $moduleListeners[$moduleName] = $callable;
                    $sharedEventManager->detach($callable, $identifier, $eventName);
                }
            }
        }

        // Attach listed and ordered modules to the event.
        $moduleNames = array_intersect($moduleNames, array_keys($moduleListeners));
        foreach ($moduleNames as $moduleName) {
            $sharedEventManager->attach($identifier, $eventName, $moduleListeners[$moduleName]);
        }
    }

    public function handleViewShowBeforeItem(Event $event)
    {
        $this->rewriteListeners('Omeka\Controller\Site\Item', 'view.show.after', 'blocksdisposition_item_show');
    }

    public function handleViewBrowseBeforeItem(Event $event)
    {
        $this->rewriteListeners( 'Omeka\Controller\Site\Item', 'view.browse.after', 'blocksdisposition_item_browse');
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
        $space = strtolower(__NAMESPACE__);

        $settings = $services->get('Omeka\Settings');
        $modules = $settings->get('blocksdisposition_modules', []);

        $settingType = 'site_settings';
        $settings = $services->get('Omeka\Settings\Site');
        $data = $this->prepareDataToPopulate($settings, $settingType);


        $translator = $services->get('MvcTranslator');
        $blockTitles = [
            'blocksdisposition_item_browse' => $translator->translate('For item browse'), // @translate
            'blocksdisposition_item_show' => $translator->translate('For item show'), // @translate
            'blocksdisposition_item_set_browse' => $translator->translate('For item set browse'), // @translate
            'blocksdisposition_item_set_show' => $translator->translate('For item set show'), // @translate
            'blocksdisposition_media_show' => $translator->translate('For media show'), // @translate
        ];

        $fieldset = new Fieldset();
        $fieldset
            ->setName($space)
            ->setLabel('Blocks Disposition')
            ->setAttribute('id', $space)
            ->setAttribute('data-block-titles', json_encode($blockTitles))
            ->setAttribute('data-modules', json_encode($modules));

        // Hidden doesn't support multiple ordered values in Zend, so a
        // multicheckbox is added. The hidden values are not saved, because Zend
        // wraps the name with the fieldset name.
        // TODO Finalize the js (sort checkbox + enable/disable) so the hidden inputs won't be needed anymore.
        $dataToPopulate = [];
        foreach ($data as $name => $value) {
            $fieldset->add([
                'name' => $name . '-hide[]',
                'type' => \Zend\Form\Element\Hidden::class,
                'attributes' => [
                    'id' => $name,
                    'value' => '',
                ],
            ]);

            $value = is_array($value) ? $value : explode(',', $value);
            $value = array_values(array_unique($value));
            $dataToPopulate[$name . '-hide[]'] = json_encode($value);
            $dataToPopulate[$name] = $value;
            $valueOptions = array_combine($value, $value) + array_combine($modules, $modules);

            $fieldset->add([
                'name' => $name,
                'type' => \Zend\Form\Element\MultiCheckbox::class,
                'options' => [
                    'label' => $blockTitles[$name],
                    // Set initial order, even if js does it.
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    // No id: it works only on the first, and it's hidden.
                    'class' => 'module-sort',
                ],
            ]);
        }

        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($dataToPopulate);
    }

    public function handleSiteSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('blocksdisposition')
            ->add([
                'name' => 'blocksdisposition_item_browse',
                'required' => false,
            ])
            ->add([
                'name' => 'blocksdisposition_item_show',
                'required' => false,
            ])
            ->add([
                'name' => 'blocksdisposition_item_set_browse',
                'required' => false,
            ])
            ->add([
                'name' => 'blocksdisposition_item_set_show',
                'required' => false,
            ])
            ->add([
                'name' => 'blocksdisposition_media_show',
                'required' => false,
            ]);
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

        $modules = $settings->get('blocksdisposition_modules') ?: [];

        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $id = $site->id();
            $siteSettings->setTargetId($id);
            $this->initDataToPopulate($siteSettings, 'site_settings', $id);
            $data = $this->prepareDataToPopulate($siteSettings, 'site_settings');
            // Don't update empty values: nothing can be removed.
            $data = array_filter($data);
            foreach (array_keys($data) as $name) {
                $moduleBlocks = $siteSettings->get($name) ?: [];
                $moduleBlocks = array_unique(array_intersect($moduleBlocks, $modules));
                $siteSettings->set($name, array_values($moduleBlocks));
            }
        }
    }
}
