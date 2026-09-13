<?php

/**
 * @file plugins/generic/coauthorAlert/tests/TemplateSafetyTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateSafetyTest
 *
 * @brief The wizard templates are compiled by Vue from the page itself.
 *
 * The browser decodes HTML entities before Vue reads the template, so escaping
 * "{{" on the server does not stop a text typed in the settings from being
 * evaluated as a Vue expression (a title "{{ 7*7 }}" rendered as "49" in the
 * first build). Only v-pre does. These tests keep that from regressing.
 */

namespace APP\plugins\generic\coauthorAlert\tests;

class TemplateSafetyTest extends TestCase
{
    protected function template(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/templates/' . $name);
    }

    public function testEveryJournalTextIsPrintedInsideAVPreElement(): void
    {
        foreach (['wizardContributors.tpl', 'wizardReview.tpl'] as $name) {
            $printed = 0;
            foreach (explode("\n", $this->template($name)) as $number => $line) {
                if (!preg_match('/\{\$coauthorAlert\w+\}/', $line)) {
                    continue;
                }
                $printed++;
                $this->assertStringContainsString(' v-pre', $line, sprintf('%s:%d prints a setting outside v-pre.', $name, $number + 1));
            }
            $this->assertTrue($printed > 0, "{$name} prints no setting at all.");
        }
    }

    public function testThePluginDoesNotRelyOnEscapingBraces(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/CoauthorAlertPlugin.php');
        $this->assertStringNotContainsString('&#123;', $source);
    }

    public function testContributorsNoticeIsLimitedToTheContributorsSection(): void
    {
        // The hook output is repeated by Vue for every section of every step.
        $this->assertStringContainsString(
            '<div v-if="section.id === \'contributors\'" class="coauthorAlert"',
            $this->template('wizardContributors.tpl')
        );
    }

    public function testReviewMarkupCarriesTheAttributesTheScriptLooksFor(): void
    {
        $template = $this->template('wizardReview.tpl');
        $script = (string) file_get_contents(dirname(__DIR__) . '/js/coauthorAlert.js');

        foreach (['data-coauthor-alert', 'data-coauthor-alert-confirm', 'data-coauthor-alert-error'] as $attribute) {
            $this->assertStringContainsString($attribute, $template);
            $this->assertStringContainsString('[' . $attribute . ']', $script);
        }
        $this->assertStringContainsString("'.submissionWizard__footer'", $script);
    }

    public function testAssetsReferencedByThePluginExist(): void
    {
        $this->assertTrue(is_file(dirname(__DIR__) . '/js/coauthorAlert.js'));
        $this->assertTrue(is_file(dirname(__DIR__) . '/styles/coauthorAlert.css'));
    }
}
