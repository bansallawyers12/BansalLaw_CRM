/**
 * intl-tel-input v29+ initialisation for CRM country-code picker fields.
 * .telephone inputs store dial codes (e.g. +61) in name="country_code".
 */
(function () {
    'use strict';

    var DEFAULT_OPTIONS = {
        initialCountry: 'au',
        countryOrder: ['au', 'gb'],
        separateDialCode: false,
        strictMode: false,
        formatAsYouType: false,
        showFlags: true
    };

    function syncCountryCodeValue(input, iti) {
        var country = iti.getSelectedCountry();

        if (country && country.dialCode) {
            input.value = '+' + country.dialCode;
        }
    }

    function preventManualTyping(input) {
        input.addEventListener('keydown', function (event) {
            var allowedKeys = ['Tab', 'Shift', 'Escape', 'Enter', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

            if (allowedKeys.indexOf(event.key) !== -1 || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            event.preventDefault();
        });
    }

    window.initIntlTelInput = function (input, options) {
        if (!input || typeof window.intlTelInput !== 'function') {
            return null;
        }

        if (window.intlTelInput.getInstance(input)) {
            return window.intlTelInput.getInstance(input);
        }

        var opts = Object.assign({}, DEFAULT_OPTIONS, options || {});
        var initialValue = (input.value || '').trim();
        var iti = window.intlTelInput(input, opts);

        if (initialValue) {
            iti.setNumber(initialValue.charAt(0) === '+' ? initialValue : '+' + initialValue);
        }

        input.addEventListener('countrychange', function () {
            syncCountryCodeValue(input, iti);
        });

        preventManualTyping(input);
        syncCountryCodeValue(input, iti);

        return iti;
    };

    window.initIntlTelInputs = function (root) {
        var scope = root && root.querySelectorAll ? root : document;
        var inputs = scope.querySelectorAll('.telephone');
        var instances = [];

        inputs.forEach(function (input) {
            var instance = window.initIntlTelInput(input);

            if (instance) {
                instances.push(instance);
            }
        });

        return instances;
    };
}());
