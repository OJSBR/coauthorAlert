<?php

/**
 * @file plugins/generic/coauthorAlert/CoauthorAlertSettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CoauthorAlertSettingsForm
 *
 * @brief Settings form with multilingual alert texts for the Coauthor Alert plugin.
 */

namespace APP\plugins\generic\coauthorAlert;

use APP\template\TemplateManager;
use PKP\core\PKPString;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class CoauthorAlertSettingsForm extends Form
{
    public CoauthorAlertPlugin $plugin;

    public ?int $contextId;

    public function __construct(CoauthorAlertPlugin $plugin, ?int $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::getLocaleFieldNames()
     */
    public function getLocaleFieldNames(): array
    {
        return CoauthorAlertPlugin::LOCALIZED_SETTINGS;
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        foreach (CoauthorAlertPlugin::LOCALIZED_SETTINGS as $name) {
            $this->setData($name, $this->getStoredOrDefault($name));
        }

        $this->setData('requireConfirmation', $this->plugin->getFlag($this->contextId, 'requireConfirmation', true));
        $this->setData('onlyWhenSingleAuthor', $this->plugin->getFlag($this->contextId, 'onlyWhenSingleAuthor', false));

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(array_merge(
            CoauthorAlertPlugin::LOCALIZED_SETTINGS,
            ['requireConfirmation', 'onlyWhenSingleAuthor']
        ));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        foreach (CoauthorAlertPlugin::LOCALIZED_SETTINGS as $name) {
            $allowsHtml = in_array($name, CoauthorAlertPlugin::HTML_SETTINGS, true);
            $values = [];

            foreach ((array) $this->getData($name) as $locale => $value) {
                $value = trim((string) $value);
                $values[$locale] = $allowsHtml
                    ? PKPString::stripUnsafeHtml($value)
                    : strip_tags($value);
            }

            $this->plugin->updateSetting($this->contextId, $name, $values, 'object');
        }

        $this->plugin->updateSetting($this->contextId, 'requireConfirmation', (bool) $this->getData('requireConfirmation'), 'bool');
        $this->plugin->updateSetting($this->contextId, 'onlyWhenSingleAuthor', (bool) $this->getData('onlyWhenSingleAuthor'), 'bool');

        parent::execute(...$functionArgs);
    }

    /**
     * Return the stored value of each form locale, or the shipped default of that
     * locale when it was never filled or was emptied.
     *
     * An emptied field is displayed with the default wording again, because that
     * is exactly what authors see in the wizard.
     */
    protected function getStoredOrDefault(string $name): array
    {
        $stored = $this->plugin->getSetting($this->contextId, $name);
        $stored = is_array($stored) ? $stored : [];

        $values = [];
        foreach (array_keys($this->supportedLocales) as $locale) {
            $value = (string) ($stored[$locale] ?? '');
            $values[$locale] = trim($value) !== ''
                ? $value
                : __('plugins.generic.coauthorAlert.default.' . $name, [], $locale);
        }

        return $values;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\coauthorAlert\CoauthorAlertSettingsForm', '\CoauthorAlertSettingsForm');
}
