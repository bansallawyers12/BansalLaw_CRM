const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'public/js/intlTelInput.js'), 'utf8');
const initJs = fs.readFileSync(path.join(root, 'public/js/intl-tel-input-init.js'), 'utf8');

const dom = new JSDOM('<!doctype html><body><input class="telephone" name="country_code" value="+61"></body>', {
    runScripts: 'dangerously'
});
const { window } = dom;

function runScript(code) {
    const script = window.document.createElement('script');
    script.textContent = code;
    window.document.body.appendChild(script);
}

runScript(js);
runScript(initJs);
window.initIntlTelInputs();

const input = window.document.querySelector('.telephone');
const iti = window.intlTelInput.getInstance(input);
const country = iti.getSelectedCountry();

const result = {
    version: window.intlTelInput.version,
    value: input.value,
    iso2: country && country.iso2,
    dialCode: country && country.dialCode,
    hasItiWrapper: !!input.closest('.iti')
};

console.log(JSON.stringify(result));

if (!result.hasItiWrapper) {
    process.exit(1);
}

if (result.value !== '+61') {
    process.exit(2);
}

if (result.iso2 !== 'au') {
    process.exit(3);
}
