{**
 * plugins/generic/coauthorAlert/templates/wizardReview.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Final notice and acknowledgement checkbox, rendered next to the contributors
 * panel on the review step of the submission wizard. Every element holding text
 * typed by the journal carries v-pre, so that text is never compiled by Vue.
 *}
<div class="coauthorAlert coauthorAlert--review" data-coauthor-alert="review">
	<div class="coauthorAlert__banner coauthorAlert__banner--final">
		<span class="coauthorAlert__icon" aria-hidden="true">!</span>
		<div class="coauthorAlert__body">
			<h3 class="coauthorAlert__title" v-pre>{$coauthorAlertTitle}</h3>
			<div class="coauthorAlert__text" v-pre>{$coauthorAlertText}</div>
		</div>
	</div>
	<div v-if="publication.authors.length < 2" class="coauthorAlert__status coauthorAlert__status--danger">
		<span class="coauthorAlert__statusIcon" aria-hidden="true">&#10007;</span>
		<span v-pre>{$coauthorAlertSoloWarning}</span>
	</div>
	{if $coauthorAlertRequireConfirmation}
		<div
			class="coauthorAlert__confirm"
			{if $coauthorAlertOnlyWhenSingleAuthor}v-if="publication.authors.length < 2"{/if}
		>
			<label class="coauthorAlert__confirmLabel" for="coauthorAlertConfirm">
				<input
					type="checkbox"
					id="coauthorAlertConfirm"
					class="coauthorAlert__checkbox"
					aria-describedby="coauthorAlertConfirmError"
					data-coauthor-alert-confirm="1"
				>
				<span class="coauthorAlert__confirmText" v-pre>{$coauthorAlertConfirmLabel}</span>
			</label>
			<p id="coauthorAlertConfirmError" class="coauthorAlert__error" role="alert" data-coauthor-alert-error hidden v-pre>{$coauthorAlertConfirmError}</p>
		</div>
	{/if}
</div>
