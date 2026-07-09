const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const jquery = fs.readFileSync(path.join(root, 'node_modules/jquery/dist/jquery.min.js'), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, 'public/js/bootstrap.bundle.min.js'), 'utf8');
const compat = fs.readFileSync(path.join(root, 'public/js/bootstrap5-jquery-compat.js'), 'utf8');

const dom = new JSDOM(
    '<!doctype html><html><body>' +
    '<div class="dropdown"><button class="dropdown-toggle" data-toggle="dropdown">Menu</button><div class="dropdown-menu"></div></div>' +
    '<div class="modal" id="testModal"><div class="modal-dialog"></div></div>' +
    '<div class="alert alert-success"><button data-dismiss="alert">x</button></div>' +
    '</body></html>',
    { runScripts: 'dangerously' }
);

const { window } = dom;

function runScript(code) {
    const script = window.document.createElement('script');
    script.textContent = code;
    window.document.body.appendChild(script);
}

runScript(jquery);
runScript(bootstrap);
runScript(compat);

const toggle = window.document.querySelector('[data-toggle="dropdown"]');
const dismiss = window.document.querySelector('[data-dismiss="alert"]');

const result = {
    hasBootstrap: typeof window.bootstrap === 'object',
    hasModalPlugin: typeof window.jQuery.fn.modal === 'function',
    hasDropdownPlugin: typeof window.jQuery.fn.dropdown === 'function',
    hasTooltipPlugin: typeof window.jQuery.fn.tooltip === 'function',
    hasPopoverPlugin: typeof window.jQuery.fn.popover === 'function',
    migratedDropdownToggle: toggle && toggle.getAttribute('data-bs-toggle') === 'dropdown',
    migratedAlertDismiss: dismiss && dismiss.getAttribute('data-bs-dismiss') === 'alert'
};

console.log(JSON.stringify(result));

if (!result.hasBootstrap || !result.hasModalPlugin || !result.hasDropdownPlugin) {
    process.exit(1);
}

if (!result.migratedDropdownToggle || !result.migratedAlertDismiss) {
    process.exit(2);
}
