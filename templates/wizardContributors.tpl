{**
 * plugins/generic/coauthorAlert/templates/wizardContributors.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Notice rendered on the contributors step of the submission wizard.
 *
 * The hook output is repeated by Vue for every section of every step, so v-if
 * restricts it to the contributors section. Every element holding text typed by
 * the journal carries v-pre, so that text is never compiled as a Vue template.
 *}
<div v-if="section.id === 'contributors'" class="coauthorAlert" data-coauthor-alert="contributors">
	<div class="coauthorAlert__banner">
		<span class="coauthorAlert__icon" aria-hidden="true">!</span>
		<div class="coauthorAlert__body">
			<h3 class="coauthorAlert__title" v-pre>{$coauthorAlertTitle}</h3>
			<div class="coauthorAlert__text" v-pre>{$coauthorAlertText}</div>
		</div>
	</div>
	<div role="status" aria-live="polite">
		<div v-if="publication.authors.length < 2" class="coauthorAlert__status coauthorAlert__status--danger">
			<span class="coauthorAlert__statusIcon" aria-hidden="true">&#10007;</span>
			<span v-pre>{$coauthorAlertSoloWarning}</span>
		</div>
		<div v-else class="coauthorAlert__status coauthorAlert__status--ok">
			<span class="coauthorAlert__statusIcon" aria-hidden="true">&#10003;</span>
			<span v-pre>{$coauthorAlertSoloOk}</span>
		</div>
	</div>
</div>
