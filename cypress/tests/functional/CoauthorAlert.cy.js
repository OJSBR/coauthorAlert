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

	// The journal's primary language: the settings form shows it first, so the
	// spec never assumes English there. The wizard is driven in English where the
	// journal has it, because PKP's own upload command looks for English labels.
	let locale = Cypress.env('locale') || null;
	let wizardLocale = null;
	let submissionLocale = null;

	// Without a hash: cy.visit() does not reload when only the fragment changes.
	const pluginsUrl = '/index.php/' + contextPath + '/management/settings/website';
	const settingsForm = 'form[id="coauthorAlertSettingsForm"]';
	const enableCheckbox = 'input[id^="select-cell-coauthoralertplugin-enabled"]';
	const footerButtons = '.submissionWizard__footer button';

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// jQuery may not be on the page yet when this runs, so the check retries on the window
	// itself instead of on a property that would resolve as undefined.
	const waitJQuery = () => cy.window({timeout: 60000}).should((win) => {
		expect(win.jQuery && win.jQuery.active, 'pending jQuery requests').to.eq(0);
	});

	// The form is only the plugin's once its PKP handler is attached: a Save clicked before
	// that submits the form natively and leaves the page for the grid's manage URL. After a
	// failed validation the modal replaces the form, so this is checked before every save.
	const waitFormHandler = (formSelector) => cy.window({timeout: 30000}).should((win) => {
		expect(win.jQuery(formSelector).data('pkp.handler'), 'form handler').to.exist;
	});

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// The primary language of the journal of contextPath.
	const withLocale = (callback) => {
		if (locale) {
			return cy.wrap(locale, {log: false}).then(callback);
		}
		// The listing carries summary properties only: the language comes from the
		// journal's own entry.
		request('/index.php/index/api/v1/contexts?count=100').then((response) => {
			const list = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
			const journal = list.items.find((item) => item.urlPath === contextPath);
			request(pageUrl('api/v1/contexts/' + journal.id)).then((detail) => {
				const context = typeof detail.body === 'string' ? JSON.parse(detail.body) : detail.body;
				locale = context.primaryLocale;
				expect(locale, 'the primary language of the journal').to.be.a('string');
				// The interface language of the wizard, and the language the submission
				// itself is made in: a journal may offer English in one and not the other.
				wizardLocale = (context.supportedLocales || []).includes('en') ? 'en' : locale;
				const forSubmission = context.supportedSubmissionLocales || context.supportedLocales || [locale];
				submissionLocale = forSubmission.includes(wizardLocale) ? wizardLocale : forSubmission[0];
				callback(locale);
			});
		});
	};

	// ---- end of helpers ----

	// The website settings page on its Plugins tab (a new query string forces a load).
	const openPluginsTab = () => {
		cy.visit(pluginsUrl + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// The settings action lives in the row's extras, revealed by show_extras.
	// Queried from the body on every call: the grid is redrawn by AJAX. The page
	// is never reloaded here — a reload right after saving stalls the web server
	// of PKP's CI — so the caller opens the Plugins tab once per test.
	const openSettings = () => {
		cy.get('tr[id*="coauthoralertplugin"]', {timeout: 30000}).then(($row) => {
			if (!$row.find('a[id*="coauthoralertplugin-settings"]:visible').length) {
				cy.get('tr[id*="coauthoralertplugin"] a.show_extras').first().click();
			}
		});
		cy.get('a[id*="coauthoralertplugin-settings"]').first().click({force: true});
		// The modal is position: fixed, which Cypress does not count as visible.
		cy.get(settingsForm).should('exist');
		waitFormHandler(settingsForm);
	};

	const saveSettings = () => {
		waitFormHandler(settingsForm);
		cy.intercept('POST', /coauthorAlert|coauthoralert|settings-plugin-grid/).as('saveSettings');
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').scrollIntoView().click();
		cy.wait('@saveSettings').its('response.statusCode').should('eq', 200);
		waitJQuery();
		// The modal closes on a successful save: reopening it before the old form
		// is gone would read the values still on screen.
		cy.get(settingsForm).should('not.exist');
	};

	// Clicks the wizard's primary button until the given step is open.
	const goToStep = (stepId, attempts = 6) => {
		cy.location('hash').then((hash) => {
			if (hash === '#' + stepId || attempts === 0) {
				return;
			}
			cy.get(footerButtons).last().click();
			// A step is a route change of the wizard: wait for the hash to move on
			// instead of for a fixed time.
			cy.location('hash', {timeout: 30000}).should('not.eq', hash);
			goToStep(stepId, attempts - 1);
		});
	};

	const createSubmission = () => {
		if (Cypress.env('submissionId')) {
			return cy.wrap(Cypress.env('submissionId'));
		}

		cy.visit(pageUrl(wizardLocale + '/submission'));
		// Whatever this journal offers: another installation has other ids.
		cy.get('input[name="locale"][value="' + submissionLocale + '"]').click();
		// A journal with a single section renders it as a hidden input.
		cy.get('input[name="sectionId"]').first().then(($section) => {
			if ($section.is(':visible')) {
				cy.wrap($section).click();
			}
		});
		cy.setTinyMceContent('startSubmission-title-control', 'Coauthor Alert ' + Date.now());
		cy.get('input[name="submissionRequirements"]').check();
		cy.get('input[name="privacyConsent"]').check();
		// The class of the primary button changed between 3.5 builds: the footer of
		// the start form is where it always is.
		// The class of the primary button changed between 3.5 builds: take it when it
		// is there, otherwise the last button of the form.
		cy.get('form button', {timeout: 30000}).then(($buttons) => {
			const primary = $buttons.filter('.pkpButton--isPrimary');
			cy.wrap(primary.length ? primary.last() : $buttons.last()).click();
		});

		cy.location('search').should('match', /id=\d+/);
		cy.setTinyMceContent('titleAbstract-abstract-control-' + submissionLocale, 'An abstract for the Coauthor Alert plugin test.');
		cy.get(footerButtons).last().click();
		cy.uploadSubmissionFiles([{
			file: 'dummy.pdf',
			fileName: 'dummy.pdf',
			mimeType: 'application/pdf',
			genre: 'Article Text'
		}]);

		return cy.location('search').then((search) => search.match(/id=(\d+)/)[1]);
	};

	// Resolved before any test body is queued, so the selectors below can carry it.
	before(function() {
		login(adminUser, adminPassword);
		withLocale(() => {});
	});

	it('Enables the plugin with prefilled texts and persists a change', function() {
		login(adminUser, adminPassword);

		openPluginsTab();
		cy.get(enableCheckbox, {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get(enableCheckbox).should('be.checked');

		// The form opens with the shipped wording in every form locale.
		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[' + locale + ']"]').invoke('val').should('not.be.empty');
		cy.get(settingsForm + ' input[name="requireConfirmation"]').should('be.checked');
		cy.get(settingsForm + ' input[name="onlyWhenSingleAuthor"]').should('not.be.checked');

		cy.get(settingsForm + ' input[name="soloWarning[' + locale + ']"]').scrollIntoView().clear().type(customWarning, {delay: 0});
		saveSettings();

		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[' + locale + ']"]').should('have.value', customWarning);
	});

	it('Reacts to the author list and blocks submitting until acknowledged', function() {
		login(adminUser, adminPassword);

		createSubmission().then((submissionId) => {
			cy.visit(pageUrl(wizardLocale + '/submission') + '?id=' + submissionId);
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
		login(adminUser, adminPassword);

		openPluginsTab();
		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[' + locale + ']"]').scrollIntoView().clear();
		saveSettings();

		openSettings();
		cy.get(settingsForm + ' input[name="soloWarning[' + locale + ']"]')
			.invoke('val')
			.should('not.be.empty')
			.and('not.eq', customWarning);
	});
});
