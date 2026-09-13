<?php

/**
 * @file plugins/generic/coauthorAlert/CoauthorAlertPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CoauthorAlertPlugin
 *
 * @brief Shows explicit, customizable and multilingual alerts on the
 *  contributors step and on the final review step of the OJS 3.5 submission
 *  wizard, warning authors that supervised undergraduate work requires the
 *  supervisor as a co-author, and requiring an acknowledgement before submit.
 */

namespace APP\plugins\generic\coauthorAlert;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CoauthorAlertPlugin extends GenericPlugin
{
    /** Settings stored as locale => value arrays. */
    public const LOCALIZED_SETTINGS = [
        'contributorsAlertTitle',
        'contributorsAlertText',
        'reviewAlertTitle',
        'reviewAlertText',
        'soloWarning',
        'soloOk',
        'confirmLabel',
        'confirmError',
    ];

    /** Localized settings that may contain (sanitized) HTML. */
    public const HTML_SETTINGS = [
        'contributorsAlertText',
        'reviewAlertText',
        'confirmLabel',
    ];

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.coauthorAlert.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.coauthorAlert.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        // Do not touch the database while the system is being installed or upgraded.
        if (Application::isUnderMaintenance()) {
            return true;
        }

        if ($this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', $this->loadAssets(...));
            Hook::add('Template::SubmissionWizard::Section', $this->addContributorsAlert(...));
            Hook::add('Template::SubmissionWizard::Section::Review', $this->addReviewAlert(...));
        }

        return true;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();

        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, [
                            'verb' => 'settings',
                            'plugin' => $this->getName(),
                            'category' => 'generic',
                        ]),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $form = new CoauthorAlertSettingsForm($this, $context ? $context->getId() : Application::SITE_CONTEXT_ID);

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

                return new JSONMessage(true, $form->fetch($request));
        }

        return parent::manage($args, $request);
    }

    /**
     * Load the plugin stylesheet and script on the submission wizard page only.
     */
    public function loadAssets(string $hookName, array $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        $template = $args[1];

        if ($template !== 'submission/wizard.tpl') {
            return false;
        }

        $request = Application::get()->getRequest();
        $baseUrl = $request->getBaseUrl() . '/' . $this->getPluginPath();

        $templateMgr->addStyleSheet(
            'coauthorAlert',
            $baseUrl . '/styles/coauthorAlert.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'coauthorAlert',
            $baseUrl . '/js/coauthorAlert.js',
            ['contexts' => ['backend']]
        );

        return false;
    }

    /**
     * Add the alert to the contributors step of the submission wizard.
     *
     * The hook is rendered inside the Vue loop over every section of every
     * step, so the markup is limited to the contributors section with v-if.
     */
    public function addContributorsAlert(string $hookName, array $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[1];
        $output = &$args[2];

        $contextId = $this->getCurrentContextId();

        $templateMgr->assign([
            'coauthorAlertTitle' => $this->getText($contextId, 'contributorsAlertTitle'),
            'coauthorAlertText' => $this->getHtml($contextId, 'contributorsAlertText'),
            'coauthorAlertSoloWarning' => $this->getText($contextId, 'soloWarning'),
            'coauthorAlertSoloOk' => $this->getText($contextId, 'soloOk'),
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('wizardContributors.tpl'));

        return false;
    }

    /**
     * Add the final alert and the acknowledgement checkbox to the review step.
     */
    public function addReviewAlert(string $hookName, array $args): bool
    {
        $params = $args[0];
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[1];
        $output = &$args[2];

        // The hook runs once per review panel; attach to the contributors one.
        if (($params['step'] ?? null) !== 'contributors') {
            return false;
        }

        $contextId = $this->getCurrentContextId();

        $templateMgr->assign([
            'coauthorAlertTitle' => $this->getText($contextId, 'reviewAlertTitle'),
            'coauthorAlertText' => $this->getHtml($contextId, 'reviewAlertText'),
            'coauthorAlertSoloWarning' => $this->getText($contextId, 'soloWarning'),
            'coauthorAlertConfirmLabel' => $this->getHtml($contextId, 'confirmLabel'),
            'coauthorAlertConfirmError' => $this->getText($contextId, 'confirmError'),
            'coauthorAlertRequireConfirmation' => $this->getFlag($contextId, 'requireConfirmation', true),
            'coauthorAlertOnlyWhenSingleAuthor' => $this->getFlag($contextId, 'onlyWhenSingleAuthor', false),
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('wizardReview.tpl'));

        return false;
    }

    /**
     * Get a boolean setting, falling back to a default when never configured.
     */
    public function getFlag(?int $contextId, string $name, bool $default): bool
    {
        $value = $this->getSetting($contextId, $name);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Get a localized setting as escaped plain text.
     *
     * The templates print it inside a v-pre element, so Vue never compiles it.
     */
    public function getText(?int $contextId, string $name): string
    {
        return htmlspecialchars(strip_tags($this->getLocalized($contextId, $name)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get a localized setting as sanitized HTML.
     *
     * The templates print it inside a v-pre element, so Vue never compiles it.
     */
    public function getHtml(?int $contextId, string $name): string
    {
        return PKPString::stripUnsafeHtml($this->getLocalized($contextId, $name));
    }

    /**
     * Get a localized setting value for the current locale.
     *
     * Falls back to the translated default shipped with the plugin when the
     * journal left the field empty.
     */
    public function getLocalized(?int $contextId, string $name): string
    {
        $value = static::resolveLocalized(
            $this->getSetting($contextId, $name),
            Locale::getLocale(),
            Locale::getPrimaryLocale()
        );

        return $value ?? __('plugins.generic.coauthorAlert.default.' . $name);
    }

    /**
     * Pick the value to display from a stored localized setting.
     *
     * A locale the journal edits in the settings form is authoritative: if its
     * field was emptied, null is returned and the default text of that same
     * language is used. A locale that is not among the stored ones (a UI-only
     * language) borrows the journal's primary locale instead.
     *
     * @return ?string null when the plugin default should be used
     */
    public static function resolveLocalized(mixed $value, string $locale, string $primaryLocale): ?string
    {
        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $key = array_key_exists($locale, $value) ? $locale : $primaryLocale;
        $picked = trim((string) ($value[$key] ?? ''));

        return $picked !== '' ? (string) $value[$key] : null;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\coauthorAlert\CoauthorAlertPlugin', '\CoauthorAlertPlugin');
}
