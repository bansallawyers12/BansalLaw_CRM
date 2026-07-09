const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const jquery = fs.readFileSync(path.join(root, 'node_modules/jquery/dist/jquery.min.js'), 'utf8');
const appMin = fs.readFileSync(path.join(root, 'public/js/app.min.js'), 'utf8');

const dom = new JSDOM('<!doctype html><html><body></body></html>', {
    runScripts: 'dangerously'
});

const { window } = dom;

function runScript(code) {
    const script = window.document.createElement('script');
    script.textContent = code;
    window.document.body.appendChild(script);
}

runScript(jquery);
const versionAfterJquery = window.jQuery.fn.jquery;

runScript(appMin);
const versionAfterAppMin = window.jQuery.fn.jquery;

const result = {
    versionAfterJquery,
    versionAfterAppMin,
    hasPopper: typeof window.Popper === 'function',
    hasMoment: typeof window.moment === 'function'
};

console.log(JSON.stringify(result));

if (result.versionAfterJquery !== '3.7.1' || result.versionAfterAppMin !== '3.7.1') {
    process.exit(1);
}

if (!result.hasPopper || !result.hasMoment) {
    process.exit(2);
}
