/**
 * Cross-tab logout: "Log out (this tab)" does not invalidate the server session,
 * so other tabs stay logged in. "Log out everywhere" invalidates the session
 * and notifies other tabs via localStorage so they redirect to login.
 *
 * Usage: add data-logout="tab" for this-tab-only, data-logout="all" for everywhere.
 * Ensure the link's form (for "all") has id "app-logout-form" or "crm-logout-form".
 */
(function () {
	'use strict';

	var LOGOUT_ALL_KEY = 'crm_logout_all_ts';
	var LOGIN_PATH = '/login';

	function getLoginUrl(withTabLogout) {
		var base = typeof window.crmLoginUrl !== 'undefined' ? window.crmLoginUrl : LOGIN_PATH;
		return withTabLogout ? base + (base.indexOf('?') !== -1 ? '&' : '?') + 'tab_logout=1' : base;
	}

	function redirectToLogin(tabLogout) {
		window.location.href = getLoginUrl(tabLogout);
	}

	// Other tabs: when "log out everywhere" is triggered, redirect to login
	function onStorage(e) {
		if (e.key === LOGOUT_ALL_KEY && e.newValue) {
			redirectToLogin(false);
		}
	}

	if (window.addEventListener) {
		window.addEventListener('storage', onStorage);
	}

	function handleLogoutTab(e) {
		e.preventDefault();
		redirectToLogin(true);
	}

	function handleLogoutAll(e) {
		e.preventDefault();
		try {
			localStorage.setItem(LOGOUT_ALL_KEY, String(Date.now()));
		} catch (err) {}
		var form = document.getElementById('app-logout-form') || document.getElementById('crm-logout-form');
		if (form) {
			form.submit();
		} else {
			redirectToLogin(false);
		}
	}

	function bind() {
		var tabLinks = document.querySelectorAll('[data-logout="tab"]');
		var allLinks = document.querySelectorAll('[data-logout="all"]');
		for (var i = 0; i < tabLinks.length; i++) {
			tabLinks[i].removeEventListener('click', handleLogoutTab);
			tabLinks[i].addEventListener('click', handleLogoutTab);
		}
		for (var j = 0; j < allLinks.length; j++) {
			allLinks[j].removeEventListener('click', handleLogoutAll);
			allLinks[j].addEventListener('click', handleLogoutAll);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
