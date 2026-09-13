<?php

/**
 * @file plugins/generic/coauthorAlert/tests/LocaleFilesTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFilesTest
 *
 * @brief Translations. OJS 3.5 has no locale fallback: a key missing from a
 *        locale is rendered as ##key##, so an incomplete file is worse than none.
 */

namespace APP\plugins\generic\coauthorAlert\tests;

class LocaleFilesTest extends TestCase
{
    /** Locale codes shipped by the plugin, using the OJS 3.5 (Weblate) codes. */
    public const LOCALES = [
        'ar', 'az', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en', 'es', 'eu', 'fa', 'fi', 'fr', 'fr_CA',
        'gl', 'hu', 'hy', 'id', 'it', 'ja', 'ka', 'mk', 'ms', 'nb_NO', 'nl', 'pl', 'pt', 'pt_BR',
        'ro', 'ru', 'sl', 'sr_Latn', 'sv', 'tr', 'uk', 'vi', 'zh_Hans',
    ];

    protected function localeDir(): string
    {
        return dirname(__DIR__) . '/locale';
    }

    /** @return array<string, PoFile> */
    protected function files(): array
    {
        $files = [];
        foreach (self::LOCALES as $locale) {
            $path = $this->localeDir() . "/{$locale}/locale.po";
            if (is_file($path)) {
                $files[$locale] = new PoFile($path);
            }
        }
        return $files;
    }

    public function testShipsExactlyTheSupportedLocaleCodes(): void
    {
        $dirs = array_map('basename', glob($this->localeDir() . '/*', GLOB_ONLYDIR) ?: []);
        sort($dirs);
        $expected = self::LOCALES;
        sort($expected);

        // Legacy codes such as fr_FR or pt_PT do not exist in OJS 3.5 and would
        // silently never load.
        $this->assertSame($expected, $dirs);
    }

    public function testEveryLocaleHasExactlyTheKeysOfTheEnglishMaster(): void
    {
        $files = $this->files();
        $master = array_keys($files['en']->entries);
        $this->assertCount(24, $master);

        foreach ($files as $locale => $file) {
            $this->assertSame($master, array_keys($file->entries), "Keys of {$locale} differ from en.");
        }
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                $this->assertNotEmpty(trim($value), "Empty translation for {$key} in {$locale}.");
            }
        }
    }

    public function testHeaderDeclaresTheDirectoryLocale(): void
    {
        foreach ($this->files() as $locale => $file) {
            $this->assertStringContainsString("Language: {$locale}\n", $file->header, "Wrong Language header in {$locale}.");
        }
    }

    public function testDefaultTextsKeepTheHtmlStructureOfTheMaster(): void
    {
        $files = $this->files();
        $tags = fn (string $html): array => preg_match_all('#</?[a-z]+#i', $html, $m) ? $m[0] : [];

        foreach (['contributorsAlertText', 'reviewAlertText', 'confirmLabel'] as $name) {
            $key = 'plugins.generic.coauthorAlert.default.' . $name;
            $expected = $tags($files['en']->entries[$key]);
            foreach ($files as $locale => $file) {
                $this->assertSame($expected, $tags($file->entries[$key]), "HTML structure of {$name} differs in {$locale}.");
            }
        }
    }

    public function testPlainTextDefaultsCarryNoMarkup(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach (['contributorsAlertTitle', 'reviewAlertTitle', 'soloWarning', 'soloOk', 'confirmError'] as $name) {
                $value = $file->entries['plugins.generic.coauthorAlert.default.' . $name];
                $this->assertSame(strip_tags($value), $value, "Markup in the plain-text default {$name} ({$locale}).");
            }
        }
    }
}
