<?php

namespace BlocksDisposition;

use BlocksDisposition\Form\ConfigFormSettings;
use Omeka\Module\AbstractModule;
use Omeka\Settings\SettingsInterface;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    protected $listenersByEventViewShowAfter = [];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->manageAnySettings($serviceLocator->get('Omeka\Settings'), 'config', 'install', '');
        $this->manageSiteSettings('install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->manageAnySettings($serviceLocator->get('Omeka\Settings'), 'config', 'uninstall', '');
        $this->manageSiteSettings('uninstall');
    }

    protected function manageSiteSettings($process, $setValue = null)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            $this->manageAnySettings($settings, 'site_settings', $process, $setValue[$site->id()]);
        }
    }

    /**
     * Set or delete all settings of a specific type.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param string $process
     * @param array $setValue
     */
    protected function manageAnySettings(SettingsInterface $settings, $settingsType, $process, $setValue)
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$settingsType];

        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
                case 'update':
                    $settings->set($name, $setValue[$name]);
                    break;
            }
        }
    }

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

        $listenersByEvent[$eventName] = $sharedEventManager->getListeners([$identifier], $eventName);

        $serviceLocator = $this->getServiceLocator();

        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $currentSiteSlug = $serviceLocator->get('Application')->getMvcEvent()->getRouteMatch()->getParam('site-slug');

        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites', ['slug' => $currentSiteSlug])->getContent();

        $siteSettings->setTargetId($sites[0]->id());

        $blocksdisposition_modules = explode(',', $siteSettings->get($site_settings, 'site_settings'));

        $blocksdisposition_modules = json_decode(implode(',', $blocksdisposition_modules));

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

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $settings = $services->get('Omeka\Settings');
        $data = $this->prepareDataToPopulate($settings, 'config');

        $view = $renderer;
        $html = '<p>';
        $html .= '</p>';
        $html .= '<p>'
            . $view->translate('Configure modules settings for display.') // @translate
            . '</p>';

        $form = $services->get('FormElementManager')->get(ConfigFormSettings::class);
        $form->init();
        $form->setData($data);
        $html .= $renderer->formCollection($form);

        return $html;
    }

    /**
     * @param array $params
     * @return array
     */
    public function updateSiteSettingWhenChangeModuleConfig($params)
    {
        $serviceLocator = $this->getServiceLocator();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $settingType = 'site_settings';

        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();

        $site_settings_update = [];

        foreach ($sites as $site_data) {
            $siteSettings->setTargetId($site_data->id());

            $data = $this->prepareDataToPopulate($siteSettings, $settingType);

            foreach ($data as $site_setting_param => $value) {
                $settingType = 'site_settings';
                $blocksdisposition_modules = explode(',', $siteSettings->get($site_setting_param, $settingType));

                foreach ($blocksdisposition_modules as $key => $val) {
                    if (!in_array($val, $params)) {
                        unset($blocksdisposition_modules[$key]);
                    }
                }

                $site_settings_update[$site_data->id()][$site_setting_param] = implode(',', $blocksdisposition_modules);
            }
        }

        return $site_settings_update;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $config = include __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);

        $services = $this->getServiceLocator();

        $params = $controller->getRequest()->getPost();

        $form = $services->get('FormElementManager')->get(ConfigFormSettings::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);

        $this->manageSiteSettings('update', $this->updateSiteSettingWhenChangeModuleConfig($params['blocksdisposition_modules_settings']));

        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        return true;
    }

    public function handleSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();

        $settingType = 'config';
        $settings = $services->get('Omeka\Settings');
        $config_data = $this->prepareDataToPopulate($settings, $settingType);

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

        $blocks_title_value = [
            'For item browse', // @translate
            'For item show', // @translate
            'For item set browse', // @translate
            'For item set show', // @translate
            'For media show', // @translate
        ];

        $blocks_title = [];
        $i = 0;

        foreach ($defaultSettings as $name => $value) {
            $fieldset->add([
                'type' => 'hidden',
                'name' => $name,
                'attributes' => [
                    'value' => 0,
                    'class' => $name,
                ],
            ]);

            $blocks_title[$name] = $blocks_title_value[$i];
            ++$i;
        }

        $fieldset
            ->add([
                'type' => 'hidden',
                'name' => 'blocks_title',
                'attributes' => [
                    'value' => json_encode($blocks_title),
                    'class' => 'blocks_title',
                ],
            ])
            ->add([
                'type' => 'hidden',
                'name' => 'blocksdisposition_modules_from_config',
                'attributes' => [
                    'value' => json_encode($config_data['blocksdisposition_modules_settings']),
                    'class' => 'blocksdisposition_modules_from_config',
                ],
            ]);

        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($data);
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $config = include __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return [];
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, $value);
            $data[$name] = $val;
        }

        return $data;
    }
}
