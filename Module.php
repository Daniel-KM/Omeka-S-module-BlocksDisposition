<?php declare(strict_types=1);
/**
 * Blocks Disposition
 *
 * Manage automatic display of features of the modules in the resource pages.
 *
 * @copyright Daniel Berthereau, 2019-2023
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
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Fieldset;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
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
            'Omeka\Controller\SiteAdmin\Index',
            'view.layout',
            [$this, 'handleSiteSettingsHeader']
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

    public function handleViewShowBeforeItem(Event $event): void
    {
        $this->rewriteListeners('Omeka\Controller\Site\Item', 'view.show.after', 'blocksdisposition_item_show');
    }

    public function handleViewBrowseBeforeItem(Event $event): void
    {
        $this->rewriteListeners('Omeka\Controller\Site\Item', 'view.browse.after', 'blocksdisposition_item_browse');
    }

    public function handleViewBrowseBeforeItemSet(Event $event): void
    {
        $this->rewriteListeners('Omeka\Controller\Site\ItemSet', 'view.browse.after', 'blocksdisposition_item_set_browse');
    }

    public function handleViewShowBeforeItemSet(Event $event): void
    {
        $this->rewriteListeners('Omeka\Controller\Site\ItemSet', 'view.show.after', 'blocksdisposition_item_set_show');
    }

    public function handleViewShowBeforeMedia(Event $event): void
    {
        $this->rewriteListeners('Omeka\Controller\Site\Media', 'view.show.after', 'blocksdisposition_media_show');
    }

    /**
     * Enable, disable and reorder modules according to the site settings.
     *
     * @param string $identifier Event identifier
     * @param string $eventName
     * @param string $siteSettingName
     */
    protected function rewriteListeners($identifier, $eventName, $siteSettingName): void
    {
        $services = $this->getServiceLocator();
        $sharedEventManager = $services->get('SharedEventManager');

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

    public function handleSiteSettingsHeader(Event $event): void
    {
        $view = $event->getTarget();

        $params = $view->params()->fromRoute();
        if (empty($params['action']) || $params['action'] !== 'edit') {
            return;
        }

        $assetUrl = $view->getHelperPluginManager()->get('assetUrl');
        $view->headLink()->appendStylesheet($assetUrl('css/blocks-disposition.css', 'BlocksDisposition'));
        $view->headScript()->appendFile($assetUrl('js/blocks-disposition.js', 'BlocksDisposition'), 'text/javascript', ['defer' => 'defer']);
    }

    public function handleSiteSettings(Event $event): void
    {
        $services = $this->getServiceLocator();

        $modulesByView = $this->listModulesByView();

        $settingType = 'site_settings';
        $settings = $services->get('Omeka\Settings\Site');
        $data = $this->prepareDataToPopulate($settings, $settingType);

        $translator = $services->get('MvcTranslator');
        $blockTitles = [
            'blocksdisposition_item_set_show' => $translator->translate('For item set show'), // @translate
            'blocksdisposition_item_show' => $translator->translate('For item show'), // @translate
            'blocksdisposition_media_show' => $translator->translate('For media show'), // @translate
            'blocksdisposition_item_set_browse' => $translator->translate('For item set browse'), // @translate
            'blocksdisposition_item_browse' => $translator->translate('For item browse'), // @translate
        ];

        $fieldset = new Fieldset();
        $fieldset
            ->setName('blocksdisposition')
            ->setLabel('Blocks Disposition')
            ->setAttribute('id', 'blocksdisposition')
            ->setAttribute('data-block-titles', json_encode($blockTitles))
            ->setAttribute('data-modules-by-view', json_encode($modulesByView));

        if (!array_filter($modulesByView)) {
            $fieldset
                ->setLabel('Blocks Disposition (module config missing)');
        }

        $isV4 = version_compare(\Omeka\Module::VERSION, '4', '>=');

        // Hidden doesn't support multiple ordered values in Zend, so a
        // multicheckbox is added. The hidden values are not saved, because Zend
        // wraps the name with the fieldset name.
        // TODO Finalize the js (sort checkbox + enable/disable) so the hidden inputs won't be needed anymore.
        $dataToPopulate = [];
        foreach ($data as $name => $value) {
            $values = is_array($value) ? $value : explode(',', $value);
            $values = array_values(array_unique($values));
            $dataToPopulate[$name . '-hide[]'] = json_encode($values);
            $dataToPopulate[$name] = $values;
            $valueOptions = array_combine($values, $values)
                + array_combine($modulesByView[substr($name, 18)], $modulesByView[substr($name, 18)]);

            $fieldset
                ->add([
                    'name' => $name . '-hide[]',
                    'type' => \Laminas\Form\Element\Hidden::class,
                    'options' => [
                        'element_group' => 'blocksdisposition',
                    ],
                    'attributes' => [
                        'id' => $name,
                        'value' => $isV4 ? json_encode($values, 320) : '',
                    ],
                ]);

            $fieldset
                ->add([
                    'name' => $name,
                    'type' => \Laminas\Form\Element\MultiCheckbox::class,
                    'options' => [
                        'element_group' => 'blocksdisposition',
                        'label' => $blockTitles[$name],
                        // Set initial order, even if js does it.
                        'value_options' => $valueOptions,
                    ],
                    'attributes' => [
                        // No id: it works only on the first, and it's hidden.
                        'class' => 'module-sort',
                    ],
                ]);

            // In v4, the fieldsets are skipped and the form cannot have data,
            // so use first hidden input to store data
            if ($name === 'blocksdisposition_item_browse') {
                $fieldset
                    ->get('blocksdisposition_item_browse-hide[]')
                    ->setAttribute('data-block-titles', $fieldset->getAttribute('data-block-titles'))
                    ->setAttribute('data-modules-by-view', $fieldset->getAttribute('data-modules-by-view'));
            }
        }

        $form = $event->getTarget();
        if (!$isV4) {
            $form->add($fieldset);
            $form->get('blocksdisposition')->populateValues($dataToPopulate);
        } else {
            $fieldsetElementGroups = ['blocksdisposition' => 'Blocks disposition'];
            $form->setOption('element_groups', array_merge($form->getOption('element_groups') ?: [], $fieldsetElementGroups));
            foreach ($fieldset->getFieldsets() as $subFieldset) {
                $form->add($subFieldset);
            }
            foreach ($fieldset->getElements() as $element) {
                $form->add($element);
            }
            $form->populateValues($data);
        }
    }

    public function handleSiteSettingsFilters(Event $event): void
    {
        $inputFilter = version_compare(\Omeka\Module::VERSION, '4', '<')
            ? $event->getParam('inputFilter')->get('blocksdisposition')
            : $event->getParam('inputFilter');
        $inputFilter
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

    protected function listModulesByView()
    {
        $services = $this->getServiceLocator();
        $activeModules = $services->get('Omeka\ModuleManager')
            ->getModulesByState(\Omeka\Module\Manager::STATE_ACTIVE);
        unset($activeModules['BlocksDisposition']);

        $activeModules = array_combine(array_keys($activeModules), array_map(function ($v) {
            return $v->getName();
        }, $activeModules));

        $modulesByView = $services->get('Config')['blocksdisposition']['views'];
        foreach ($modulesByView as &$modules) {
            $modules = array_unique(array_intersect($modules, array_keys($activeModules)));
            natcasesort($modules);
            // Clean array keys because json_encode() doesn't keep order with numeric keys.
            $modules = array_values($modules);
        }
        unset($modules);

        return $modulesByView;
    }
}
