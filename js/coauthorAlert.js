/**
 * plugins/generic/coauthorAlert/js/coauthorAlert.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Blocks the primary submission wizard button while the acknowledgement
 * checkbox is visible and unchecked. The click is intercepted in the capture
 * phase so the gate survives any Vue re-render of the footer buttons.
 */
(function () {
	'use strict';

	var ALERT_SELECTOR = '[data-coauthor-alert]';
	var CHECKBOX_SELECTOR = '[data-coauthor-alert-confirm]';
	var ERROR_SELECTOR = '[data-coauthor-alert-error]';
	var FOOTER_SELECTOR = '.submissionWizard__footer';

	/**
	 * An element inside an inactive wizard step has no layout boxes, which is
	 * how we know the review step is not the step being displayed.
	 */
	function isVisible(element) {
		if (!element) {
			return false;
		}

		return !!(
			element.offsetWidth ||
			element.offsetHeight ||
			element.getClientRects().length
		);
	}

	/**
	 * The primary action is always the last button of the wizard footer,
	 * whatever its label is in the current locale.
	 */
	function isPrimaryButton(button) {
		var footer = document.querySelector(FOOTER_SELECTOR);

		if (!footer || !footer.contains(button)) {
			return false;
		}

		var buttons = footer.querySelectorAll('button');

		return buttons.length > 0 && buttons[buttons.length - 1] === button;
	}

	function getPendingCheckbox() {
		var checkboxes = document.querySelectorAll(CHECKBOX_SELECTOR);

		for (var i = 0; i < checkboxes.length; i++) {
			if (isVisible(checkboxes[i]) && !checkboxes[i].checked) {
				return checkboxes[i];
			}
		}

		return null;
	}

	function highlight(checkbox) {
		var block = checkbox.closest(ALERT_SELECTOR);

		if (!block) {
			checkbox.focus();
			return;
		}

		var error = block.querySelector(ERROR_SELECTOR);
		if (error) {
			error.hidden = false;
		}

		block.classList.add('coauthorAlert--pending');
		block.classList.remove('coauthorAlert--shake');
		// Force a reflow so the animation restarts on every blocked attempt.
		void block.offsetWidth;
		block.classList.add('coauthorAlert--shake');

		if (typeof block.scrollIntoView === 'function') {
			try {
				block.scrollIntoView({behavior: 'smooth', block: 'center'});
			} catch (e) {
				block.scrollIntoView();
			}
		}

		checkbox.focus();
	}

	document.addEventListener(
		'click',
		function (event) {
			var target = event.target;

			if (!target || typeof target.closest !== 'function') {
				return;
			}

			var button = target.closest('button');

			if (!button || !isPrimaryButton(button)) {
				return;
			}

			var checkbox = getPendingCheckbox();

			if (!checkbox) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			if (typeof event.stopImmediatePropagation === 'function') {
				event.stopImmediatePropagation();
			}

			highlight(checkbox);
		},
		true
	);

	document.addEventListener('change', function (event) {
		var checkbox = event.target;

		if (!checkbox || typeof checkbox.matches !== 'function' || !checkbox.matches(CHECKBOX_SELECTOR)) {
			return;
		}

		var block = checkbox.closest(ALERT_SELECTOR);

		if (!block) {
			return;
		}

		if (checkbox.checked) {
			block.classList.remove('coauthorAlert--pending', 'coauthorAlert--shake');
			block.classList.add('coauthorAlert--confirmed');

			var error = block.querySelector(ERROR_SELECTOR);
			if (error) {
				error.hidden = true;
			}
		} else {
			block.classList.remove('coauthorAlert--confirmed');
		}
	});
})();
