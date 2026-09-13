{**
 * plugins/generic/coauthorAlert/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Coauthor Alert plugin settings.
 *}
<script>
	$(function() {ldelim}
		$('#coauthorAlertSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="coauthorAlertSettingsForm"
	method="post"
	action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="coauthorAlertSettingsFormNotification"}

	<p id="coauthorAlertDescription" class="pkp_help">
		{translate key="plugins.generic.coauthorAlert.settings.description"}
	</p>

	{fbvFormArea id="coauthorAlertContributorsArea" title="plugins.generic.coauthorAlert.settings.contributorsArea"}
		{fbvFormSection}
			{fbvElement type="text" multilingual=true id="contributorsAlertTitle" name="contributorsAlertTitle" value=$contributorsAlertTitle label="plugins.generic.coauthorAlert.settings.contributorsAlertTitle"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="textarea" multilingual=true rich=true height=$fbvStyles.height.TALL id="contributorsAlertText" name="contributorsAlertText" value=$contributorsAlertText label="plugins.generic.coauthorAlert.settings.contributorsAlertText"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" multilingual=true id="soloWarning" name="soloWarning" value=$soloWarning label="plugins.generic.coauthorAlert.settings.soloWarning"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" multilingual=true id="soloOk" name="soloOk" value=$soloOk label="plugins.generic.coauthorAlert.settings.soloOk"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="coauthorAlertReviewArea" title="plugins.generic.coauthorAlert.settings.reviewArea"}
		{fbvFormSection}
			{fbvElement type="text" multilingual=true id="reviewAlertTitle" name="reviewAlertTitle" value=$reviewAlertTitle label="plugins.generic.coauthorAlert.settings.reviewAlertTitle"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="textarea" multilingual=true rich=true height=$fbvStyles.height.TALL id="reviewAlertText" name="reviewAlertText" value=$reviewAlertText label="plugins.generic.coauthorAlert.settings.reviewAlertText"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="coauthorAlertConfirmArea" title="plugins.generic.coauthorAlert.settings.confirmArea"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="requireConfirmation" name="requireConfirmation" checked=$requireConfirmation label="plugins.generic.coauthorAlert.settings.requireConfirmation"}
			{fbvElement type="checkbox" id="onlyWhenSingleAuthor" name="onlyWhenSingleAuthor" checked=$onlyWhenSingleAuthor label="plugins.generic.coauthorAlert.settings.onlyWhenSingleAuthor"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="textarea" multilingual=true rich=true id="confirmLabel" name="confirmLabel" value=$confirmLabel label="plugins.generic.coauthorAlert.settings.confirmLabel"}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" multilingual=true id="confirmError" name="confirmError" value=$confirmError label="plugins.generic.coauthorAlert.settings.confirmError"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
