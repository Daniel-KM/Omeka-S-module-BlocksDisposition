<?php declare(strict_types=1);

namespace BlocksDispositionTest;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\EventManager\Event;

require_once __DIR__ . '/Fixtures.php';

/**
 * Enable, disable and reorder the listeners of the modules in resource pages.
 */
class RewriteListenersTest extends AbstractHttpControllerTestCase
{
    const IDENTIFIER = 'Omeka\Controller\Site\Item';

    const EVENT = 'view.show.after';

    protected $site;

    protected $fixtures;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAs('admin@example.com');
        $this->site = $this->api()->create('sites', [
            'o:slug' => 'disposition-test',
            'o:title' => 'Disposition test',
            'o:theme' => $this->themeWithoutBlocks(),
            'o:is_public' => true,
        ])->getContent();
        $services = $this->getApplicationServiceLocator();
        $services->get('ControllerPluginManager')->get('currentSite')->setSite($this->site);
        $services->get('Omeka\Settings\Site')->setTargetId($this->site->id());

        // The fake modules A, B and C are managed, but not the module "Head".
        $config = $services->get('Config');
        foreach (['item_show', 'item_browse'] as $view) {
            $config['blocksdisposition']['views'][$view] = array_merge(
                $config['blocksdisposition']['views'][$view],
                ['FixtureA', 'FixtureB', 'FixtureC']
            );
        }
        $services->setAllowOverride(true);
        $services->setService('Config', $config);

        $this->fixtures = [
            'a' => new \FixtureA\Module(),
            'b' => new \FixtureB\Module(),
            'c' => new \FixtureC\Module(),
            'head' => new \FixtureHead\Module(),
        ];
        $shared = $services->get('SharedEventManager');
        $shared->attach(self::IDENTIFIER, self::EVENT, [$this->fixtures['a'], 'first']);
        $shared->attach(self::IDENTIFIER, self::EVENT, [$this->fixtures['c'], 'show']);
        $shared->attach(self::IDENTIFIER, self::EVENT, [$this->fixtures['head'], 'head'], 5);
        $shared->attach(self::IDENTIFIER, self::EVENT, [$this->fixtures['a'], 'second']);
        $shared->attach(self::IDENTIFIER, self::EVENT, [$this->fixtures['b'], 'show']);
        $shared->attach(self::IDENTIFIER, self::EVENT, fn () => null);
    }

    public function tearDown(): void
    {
        $this->loginAs('admin@example.com');
        $this->api()->delete('sites', $this->site->id());
        parent::tearDown();
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get a registered theme without resource page blocks.
     */
    protected function themeWithoutBlocks(): string
    {
        foreach ($this->getApplicationServiceLocator()->get('Omeka\Site\ThemeManager')->getThemes() as $name => $theme) {
            if (!$theme->isConfigurableResourcePageBlocks()) {
                return $name;
            }
        }
        $this->markTestSkipped('A theme without resource page blocks is required.');
    }

    /**
     * Get the listeners of the event as "priority:class::method", in order.
     */
    protected function listeners(): array
    {
        $shared = $this->getApplicationServiceLocator()->get('SharedEventManager');
        $identifiers = (fn () => $this->identifiers)->call($shared);
        $byPriority = $identifiers[self::IDENTIFIER][self::EVENT] ?? [];
        krsort($byPriority);
        $result = [];
        foreach ($byPriority as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $result[] = $priority . ':' . ($listener instanceof \Closure
                    ? 'closure'
                    : get_class($listener[0]) . '::' . $listener[1]);
            }
        }
        return $result;
    }

    protected function rewrite(): void
    {
        $module = $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('BlocksDisposition');
        (fn () => $this->rewriteListeners(RewriteListenersTest::IDENTIFIER, RewriteListenersTest::EVENT, 'blocksdisposition_item_show'))
            ->call($module);
    }

    protected function siteSettings(): \Omeka\Settings\SiteSettings
    {
        return $this->getApplicationServiceLocator()->get('Omeka\Settings\Site');
    }

    /**
     * Keep only the listeners of the fake modules, the core and the closures.
     */
    protected function tested(array $listeners): array
    {
        return array_values(array_filter($listeners, fn ($v) => preg_match('~Fixture|closure|Omeka\\\\Module~', $v)));
    }

    public function testNothingIsChangedWhenNotEnabled(): void
    {
        $this->siteSettings()->set('blocksdisposition_item_show', ['FixtureB']);
        $before = $this->listeners();
        $this->rewrite();
        $this->assertSame($before, $this->listeners());
    }

    public function testNothingIsChangedWhenTheThemeManagesTheBlocks(): void
    {
        $this->siteSettings()->set('blocksdisposition_enabled', true);
        $this->siteSettings()->set('blocksdisposition_item_show', ['FixtureB']);
        $this->api()->update('sites', $this->site->id(), ['o:theme' => 'default'], [], ['isPartial' => true]);
        $site = $this->api()->read('sites', $this->site->id())->getContent();
        $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get('currentSite')->setSite($site);
        $before = $this->listeners();
        $this->rewrite();
        $this->assertSame($before, $this->listeners());
    }

    public function testListsAreManagedWhenTheThemeManagesTheBlocks(): void
    {
        $this->siteSettings()->set('blocksdisposition_enabled', true);
        $this->siteSettings()->set('blocksdisposition_item_browse', ['FixtureB']);
        $this->api()->update('sites', $this->site->id(), ['o:theme' => 'default'], [], ['isPartial' => true]);
        $site = $this->api()->read('sites', $this->site->id())->getContent();
        $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get('currentSite')->setSite($site);

        $shared = $this->getApplicationServiceLocator()->get('SharedEventManager');
        $shared->attach(self::IDENTIFIER, 'view.browse.after', [$this->fixtures['a'], 'first']);
        $shared->attach(self::IDENTIFIER, 'view.browse.after', [$this->fixtures['b'], 'show']);
        $module = $this->getApplicationServiceLocator()->get('ModuleManager')->getModule('BlocksDisposition');
        (fn () => $this->rewriteListeners(RewriteListenersTest::IDENTIFIER, 'view.browse.after', 'blocksdisposition_item_browse'))
            ->call($module);

        $identifiers = (fn () => $this->identifiers)->call($shared);
        $listeners = [];
        foreach ($identifiers[self::IDENTIFIER]['view.browse.after'] ?? [] as $list) {
            foreach ($list as $listener) {
                if (is_array($listener) && strpos(get_class($listener[0]), 'Fixture') === 0) {
                    $listeners[] = get_class($listener[0]);
                }
            }
        }
        // The unchecked module A is removed, even if the theme manages blocks.
        $this->assertSame(['FixtureB\Module'], $listeners);
    }

    public function testCheckedModulesAreOrderedAndOthersAreKept(): void
    {
        $this->siteSettings()->set('blocksdisposition_enabled', true);
        $this->siteSettings()->set('blocksdisposition_item_show', ['FixtureB', 'FixtureA']);
        $this->rewrite();
        $listeners = $this->tested($this->listeners());

        // The unmanaged listeners keep their priority and their order: the
        // module "Head", the noindex of the core and the closure.
        $this->assertSame('5:FixtureHead\Module::head', $listeners[0]);
        $this->assertContains('1:Omeka\Module::noindexItem', $listeners);
        $this->assertContains('1:closure', $listeners);

        // The checked modules are attached in the order of the site settings,
        // with all their listeners, and the unchecked module is removed.
        $managed = array_values(array_filter($listeners, fn ($v) => preg_match('~Fixture[ABC]~', $v)));
        $this->assertSame([
            '1:FixtureB\Module::show',
            '1:FixtureA\Module::first',
            '1:FixtureA\Module::second',
        ], $managed);
    }

    public function testEmptyListRemovesAllManagedModules(): void
    {
        $this->siteSettings()->set('blocksdisposition_enabled', true);
        // The form saves an empty string when nothing is checked.
        $this->siteSettings()->set('blocksdisposition_item_show', '');
        $this->rewrite();
        $listeners = $this->tested($this->listeners());
        $this->assertSame([], array_values(array_filter($listeners, fn ($v) => preg_match('~Fixture[ABC]~', $v))));
        $this->assertContains('5:FixtureHead\Module::head', $listeners);
        $this->assertContains('1:Omeka\Module::noindexItem', $listeners);
    }

    /**
     * Run the upgrade script from a version.
     */
    protected function upgradeFrom(string $oldVersion): void
    {
        $services = $this->getApplicationServiceLocator();
        $module = $services->get('ModuleManager')->getModule('BlocksDisposition');
        $include = function (string $oldVersion, string $newVersion, $services): void {
            require dirname(__DIR__, 2) . '/data/scripts/upgrade.php';
        };
        \Closure::bind($include, $module, \BlocksDisposition\Module::class)($oldVersion, '3.4.8', $services);
    }

    /**
     * Get the stored value of the setting "enabled" of a site.
     *
     * @return string|false
     */
    protected function storedEnabled(int $siteId)
    {
        return $this->getApplicationServiceLocator()->get('Omeka\Connection')->fetchOne(
            'SELECT `value` FROM `site_setting` WHERE `id` = "blocksdisposition_enabled" AND `site_id` = ?',
            [$siteId]
        );
    }

    public function testUpgradeEnablesAllSites(): void
    {
        $other = $this->api()->create('sites', [
            'o:slug' => 'disposition-other',
            'o:title' => 'Disposition other',
            'o:theme' => 'default',
        ])->getContent();
        try {
            $siteSettings = $this->siteSettings();
            $siteSettings->set('blocksdisposition_item_show', ['FixtureB'], $this->site->id());
            $siteSettings->delete('blocksdisposition_item_show', $other->id());
            $siteSettings->delete('blocksdisposition_enabled', $this->site->id());
            $siteSettings->delete('blocksdisposition_enabled', $other->id());

            $this->upgradeFrom('3.4.6');

            $this->assertSame('true', $this->storedEnabled($this->site->id()));
            $this->assertSame('true', $this->storedEnabled($other->id()));
        } finally {
            $this->api()->delete('sites', $other->id());
        }
    }

    public function testUpgradeFrom347EnablesTheSitesNeverSaved(): void
    {
        $other = $this->api()->create('sites', [
            'o:slug' => 'disposition-other',
            'o:title' => 'Disposition other',
            'o:theme' => 'default',
        ])->getContent();
        try {
            $siteSettings = $this->siteSettings();
            $siteSettings->delete('blocksdisposition_enabled', $this->site->id());
            $siteSettings->set('blocksdisposition_enabled', false, $other->id());

            $this->upgradeFrom('3.4.7');

            $this->assertSame('true', $this->storedEnabled($this->site->id()));
            $this->assertSame('false', $this->storedEnabled($other->id()));
        } finally {
            $this->api()->delete('sites', $other->id());
        }
    }

    public function testSiteSettingsForm(): void
    {
        $this->dispatch('/admin/site/s/disposition-test/edit');
        $this->assertResponseStatusCode(200);
        $html = $this->getResponse()->getContent();
        $this->assertStringContainsString('name="blocksdisposition_enabled"', $html);
        // An empty value allows to save a list without checked module.
        $this->assertStringContainsString('<input type="hidden" name="blocksdisposition_item_show" value="">', $html);
        $this->assertStringNotContainsString('blocksdisposition_note', $html);
    }

    public function testSiteSettingsFormWhenTheThemeManagesTheBlocks(): void
    {
        $this->siteSettings()->set('blocksdisposition_enabled', true);
        $this->siteSettings()->set('blocksdisposition_item_show', ['Coins']);
        $this->api()->update('sites', $this->site->id(), ['o:theme' => 'default'], [], ['isPartial' => true]);
        $this->dispatch('/admin/site/s/disposition-test/edit');
        $this->assertResponseStatusCode(200);
        $html = $this->getResponse()->getContent();
        // The settings of the resource pages are displayed, but not editable,
        // so they are kept. The lists are still managed.
        $this->assertStringNotContainsString('name="blocksdisposition_item_show"', $html);
        $this->assertStringContainsString('manages the resource page blocks', $html);
        $this->assertStringContainsString('Coins', $html);
        $this->assertStringContainsString('name="blocksdisposition_enabled"', $html);
        $this->assertStringContainsString('name="blocksdisposition_item_browse"', $html);
        // The note is just before the setting to enable the module, after which
        // the script displays the buttons to order the modules of the lists.
        $note = strpos($html, 'id="blocksdisposition_note"');
        $enabled = strpos($html, '<div class="field checkbox" id="blocksdisposition_enabled_field"');
        $this->assertNotFalse($note);
        $this->assertGreaterThan($note, $enabled);
        $this->assertSame(0, substr_count(substr($html, $note, $enabled - $note), '<div class="field'));
    }

    public function testModuleBrowseInvitesToDisableTheModule(): void
    {
        $this->dispatch('/admin/module');
        $this->assertResponseStatusCode(200);
        $this->assertStringNotContainsString('is used only for the lists of resources', $this->getResponse()->getContent());

        $this->api()->update('sites', $this->site->id(), ['o:theme' => 'default'], [], ['isPartial' => true]);
        $this->dispatch('/admin/module');
        $html = $this->getResponse()->getContent();
        // Other sites of the test database may use an old theme.
        $themes = $this->getApplicationServiceLocator()->get('Omeka\Connection')
            ->executeQuery('SELECT DISTINCT `theme` FROM `site`')->fetchFirstColumn();
        if ($themes === ['default']) {
            $this->assertStringContainsString('is used only for the lists of resources', $html);
        }
    }
}
