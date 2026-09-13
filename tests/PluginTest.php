<?php

/**
 * @file plugins/generic/coauthorAlert/tests/PluginTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginTest
 *
 * @brief The plugin and settings form classes, compiled against the PKP classes
 *        of the installation they run in.
 */

namespace APP\plugins\generic\coauthorAlert\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\coauthorAlert\CoauthorAlertPlugin;
use APP\plugins\generic\coauthorAlert\CoauthorAlertSettingsForm;
use ReflectionClass;
use ReflectionNamedType;

class PluginTest extends TestCase
{
    public function testOverriddenMethodsDeclareTheReturnTypesOfThisPkpVersion(): void
    {
        // The first build declared getLocaleFieldNames() without ": array".
        // php -l accepts it; loading the class next to PKP 3.5 is a fatal error,
        // and the settings modal answered with an empty 500.
        foreach ([CoauthorAlertPlugin::class, CoauthorAlertSettingsForm::class] as $class) {
            $reflection = new ReflectionClass($class);
            $parent = $reflection->getParentClass();
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || !$parent->hasMethod($method->getName())) {
                    continue;
                }
                $parentType = $parent->getMethod($method->getName())->getReturnType();
                if ($parentType === null) {
                    continue;
                }
                $type = $method->getReturnType();
                $this->assertTrue(
                    $type instanceof ReflectionNamedType && $type->getName() === (string) $parentType,
                    sprintf('%s::%s() must declare the return type %s.', $reflection->getShortName(), $method->getName(), $parentType)
                );
            }
        }
    }

    public function testCurrentLocaleValueIsUsed(): void
    {
        $this->assertSame('Olá', CoauthorAlertPlugin::resolveLocalized(['pt_BR' => 'Olá', 'en' => 'Hello'], 'pt_BR', 'en'));
    }

    public function testEmptiedFieldFallsBackToTheDefaultOfThatLanguage(): void
    {
        // Not to another language's custom text: the reader would get a notice
        // in a language they did not choose.
        $this->assertSame(null, CoauthorAlertPlugin::resolveLocalized(['pt_BR' => 'Olá', 'en' => '  '], 'en', 'pt_BR'));
    }

    public function testUiOnlyLanguageBorrowsThePrimaryLocale(): void
    {
        $this->assertSame('Olá', CoauthorAlertPlugin::resolveLocalized(['pt_BR' => 'Olá', 'en' => 'Hello'], 'fr', 'pt_BR'));
        $this->assertSame(null, CoauthorAlertPlugin::resolveLocalized(['pt_BR' => '', 'en' => 'Hello'], 'fr', 'pt_BR'));
    }

    public function testNeverConfiguredSettingUsesTheDefault(): void
    {
        $this->assertSame(null, CoauthorAlertPlugin::resolveLocalized(null, 'pt_BR', 'pt_BR'));
        $this->assertSame('Legacy', CoauthorAlertPlugin::resolveLocalized('Legacy', 'pt_BR', 'pt_BR'));
    }

    public function testPlainTextSettingIsStrippedAndEscaped(): void
    {
        $plugin = $this->pluginReturning('<b>Title</b> & "quotes"');
        $this->assertSame('Title &amp; &quot;quotes&quot;', $plugin->getText(1, 'soloWarning'));
    }

    public function testHtmlSettingLosesScriptsAndEventHandlers(): void
    {
        $plugin = $this->pluginReturning('<p onclick="steal()">Keep <strong>this</strong></p><script>alert(1)</script>');
        $html = $plugin->getHtml(1, 'contributorsAlertText');

        $this->assertStringContainsString('<p>Keep <strong>this</strong></p>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function testFlagsFallBackToTheirDefaultsUntilSaved(): void
    {
        $plugin = new class () extends CoauthorAlertPlugin {
            public array $stored = [];

            public function getSetting($contextId, $name)
            {
                return $this->stored[$name] ?? null;
            }
        };

        $this->assertSame(true, $plugin->getFlag(1, 'requireConfirmation', true));
        $this->assertSame(false, $plugin->getFlag(1, 'onlyWhenSingleAuthor', false));

        $plugin->stored = ['requireConfirmation' => false, 'onlyWhenSingleAuthor' => true];
        $this->assertSame(false, $plugin->getFlag(1, 'requireConfirmation', true));
        $this->assertSame(true, $plugin->getFlag(1, 'onlyWhenSingleAuthor', false));
    }

    public function testSettingsFormSanitizesWhatItSaves(): void
    {
        $plugin = new class () extends CoauthorAlertPlugin {
            public array $saved = [];

            public function getSetting($contextId, $name)
            {
                return null;
            }

            public function updateSetting($contextId, $name, $value, $type = null)
            {
                $this->saved[$name] = [$value, $type];
            }
        };

        $this->ensureRouter();
        $form = new CoauthorAlertSettingsForm($plugin, 1);
        $form->setData('contributorsAlertTitle', ['en' => '  <em>Heading</em>  ']);
        $form->setData('contributorsAlertText', ['en' => '<p>Text</p><script>alert(1)</script>']);
        $form->setData('requireConfirmation', '');
        $form->setData('onlyWhenSingleAuthor', '1');
        $form->execute();

        $this->assertSame([['en' => 'Heading'], 'object'], $plugin->saved['contributorsAlertTitle']);
        $this->assertSame([['en' => '<p>Text</p>'], 'object'], $plugin->saved['contributorsAlertText']);
        $this->assertSame([false, 'bool'], $plugin->saved['requireConfirmation']);
        $this->assertSame([true, 'bool'], $plugin->saved['onlyWhenSingleAuthor']);
        $this->assertCount(10, $plugin->saved);
    }

    /**
     * A command line request has no router, and PKP forms ask it for the
     * context. The page router answers "no context", the site level.
     */
    protected function ensureRouter(): void
    {
        $request = Application::get()->getRequest();
        if (!$request->getRouter()) {
            $router = new PageRouter();
            $router->setApplication(Application::get());
            $request->setRouter($router);
        }
    }

    protected function pluginReturning(string $value): CoauthorAlertPlugin
    {
        return new class ($value) extends CoauthorAlertPlugin {
            public function __construct(private string $value)
            {
                parent::__construct();
            }

            public function getLocalized(?int $contextId, string $name): string
            {
                return $this->value;
            }
        };
    }
}
