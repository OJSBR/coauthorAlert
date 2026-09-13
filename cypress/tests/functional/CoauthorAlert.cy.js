/**
 * @file cypress/tests/functional/CoauthorAlert.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: enabling the plugin, its settings, and what an author meets
 * in the submission wizard.
 *
 * The contract under test is the author's: a notice on the contributors step
 * that reacts to the author list, and a submit button that does nothing until
 * the acknowledgement is ticked, while every earlier "Continue" keeps working.
 *
 * Assertions are made on data attributes and element names, never on labels,
 * so the spec runs unchanged against a journal in any language. The PKP test
 * data is the default; another installation can run it through --env:
 *   contextPath, adminUser, adminPassword, submissionId (an incomplete
 *   single-author submission the user can open, to skip creating one).
 * Captcha on login must be off for the run, as in the PKP test environment.
 */

describe('Co-author and Supervisor Alert plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	const customWarning = 'Cypress: only one author so far';

	// Without a hash: cy.visit() does not reload when only the fragment changes.
	const pluginsUrl = '/index.php/' + contextPath + '/management/settings/website';
	const settingsForm = 'form[id="coauthorAlertSettingsForm"]';
	const enableCheckbox = 'input[id^="select-cell-coauthoralertplugin-enabled"]';
	const footerButtons = '.submissionWizard__footer button';

	const openPluginsTab = () => {
		cy.visit(pluginsUrl);
		cy.get('button[id="plugins-button"]').click();
		cy.waitJQuery();
	};

	// The settings action lives in the row's extras, revealed by show_extras.
	// Queried from the body on every call: the grid is redrawn by AJAX.
	const openSettings = () => {
		openPluginsTab();
		cy.get('tr[id*="coauthoralertplugin"] a.show_extras').click();
		cy.get('a[id*="coauthoralertplugin-settings"]').click();
		// The modal is position: fixed, which Cypress does not count as visible.
		cy.get(settingsForm).should('exist');
	};

	const saveSettings = () => {
		cy.intercept('POST', /coauthorAlert|coauthoralert|settings-plugin-grid/).as('saveSettings');
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').scrollIntoView().click();
		cy.wait('@saveSettings').its('response.statusCode').should('eq', 200);
		cy.waitJQuery();
	};

	// Clicks the wizard's primary button until the given step is open.
	const goToStep = (stepId, attempts = 6) => {
		cy.location('hash').then((hash) => {
			if (hash === '#' + stepId || attempts === 0) {
				return;
			}
			cy.get(footerButtons).last().click();
			cy.wait(1500);
			goToStep(stepId, attempts - 1);
		});
	};

	const createSubmission = () => {
		if (Cypress.env('submissionId')) {
			return cy.wrap(Cypress.env('submissionId'));
		}

		cy.visit('/index.php/' + contextPath + '/submission');
		cy.get('input[name="locale"][value="en"]').click();
		cy.get('input[name="sectionId"][value="1"]').click();
		cy.setTinyMceContent('startSubmission-title-control', 'Coauthor Alert ' + Date.now());
		cy.get('input[name="submissionRequirements"]').check();
		cy.get('input[name="privacyConsent"]').check();
		cy.get('button.pkpButton--isPrimary').click();

		cy.location('search').should('match', /id=\d+/);
		cy.setTinyMceContent('titleAbstract-abstract-control-en', 'An abstract for the Coauthor Alert plugin test.');
		cy.get(footerButtons).last().click();
		cy.uploadSubmissionFiles([{
			file: 'dummy.pdf',
			fileName: 'dummy.pdf',
			mimeType: 'application/pdf',
			genre: 'Article Text'
		}]);

		return cy.location('search').then((search) => search.match(/id=(\d+)/)[1]);
	};

	it('Enables the plugin with prefilled texts and persists a change', function() {
		cy.login(adminUser, adminPassword, contextPath);

		openPluginsTab();
		cy.get(enableCheckbox).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				cy.waitJQuery();
			}
		});
		cy.get(enableCheckbox).should('be.checked');

		// The form opens with the shipped wording in every form locale.
		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[en]"]').invoke('val').should('not.be.empty');
		cy.get(settingsForm + ' input[name="requireConfirmation"]').should('be.checked');
		cy.get(settingsForm + ' input[name="onlyWhenSingleAuthor"]').should('not.be.checked');

		cy.get(settingsForm + ' input[name="soloWarning[en]"]').scrollIntoView().clear().type(customWarning, {delay: 0});
		saveSettings();

		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[en]"]').should('have.value', customWarning);
	});

	it('Reacts to the author list and blocks submitting until acknowledged', function() {
		cy.login(adminUser, adminPassword, contextPath);

		createSubmission().then((submissionId) => {
			cy.visit('/index.php/' + contextPath + '/en/submission?id=' + submissionId);
		});

		// Continue is never blocked on the way to the review step.
		goToStep('contributors');
		cy.get('[data-coauthor-alert="contributors"]').should('have.length', 1).and('be.visible');
		cy.get('[data-coauthor-alert="contributors"] .coauthorAlert__status--danger')
			.should('be.visible')
			.and('contain', customWarning);

		goToStep('review');
		cy.location('hash').should('eq', '#review');
		cy.get('[data-coauthor-alert="review"]').should('have.length', 1).and('be.visible');
		cy.get('[data-coauthor-alert-error]').should('not.be.visible');

		// Submit without the acknowledgement: nothing is sent, the error shows.
		cy.get(footerButtons).last().should('not.be.disabled').click();
		cy.get('[data-coauthor-alert-error]').should('be.visible');
		cy.get('[data-coauthor-alert="review"]').should('have.class', 'coauthorAlert--pending');
		cy.get('div[role="dialog"]').should('not.exist');
		cy.location('hash').should('eq', '#review');

		// Ticked: the wizard's own confirmation opens. Cancel it, so the
		// submission stays incomplete and the spec can be run again.
		cy.get('[data-coauthor-alert-confirm]').check();
		cy.get('[data-coauthor-alert="review"]').should('have.class', 'coauthorAlert--confirmed');
		cy.get('[data-coauthor-alert-error]').should('not.be.visible');
		cy.get(footerButtons).last().click();
		cy.get('div[role="dialog"]').should('be.visible').find('button').last().click();
	});

	it('Falls back to the shipped text when a field is emptied', function() {
		cy.login(adminUser, adminPassword, contextPath);

		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[en]"]').scrollIntoView().clear();
		saveSettings();

		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[en]"]')
			.invoke('val')
			.should('not.be.empty')
			.and('not.eq', customWarning);
	});
});
