/**
 * Verify Chart.js 4.x is bundled locally and chart configs used in CRM pages still work.
 */
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const errors = [];

function read(relPath) {
    return fs.readFileSync(path.join(root, relPath), 'utf8');
}

function assert(condition, message) {
    if (!condition) {
        errors.push(message);
    }
}

const chartJs = read('public/js/chart.umd.min.js');
assert(chartJs.includes('Chart'), 'public/js/chart.umd.min.js missing Chart global');
assert(/4\.5\./.test(chartJs) || chartJs.includes('4.5'), 'public/js/chart.umd.min.js is not Chart.js 4.5.x');

const chartPages = [
    'resources/views/crm/staff-login-analytics/index.blade.php',
    'resources/views/crm/leads/analytics/dashboard.blade.php',
    'resources/views/crm/clients/insights.blade.php',
    'resources/views/crm/clients/analytics-dashboard.blade.php',
    'resources/views/AdminConsole/features/esignature/index.blade.php',
];

chartPages.forEach((relPath) => {
    const content = read(relPath);
    assert(
        content.includes("components.chartjs-scripts"),
        `${relPath} must include components.chartjs-scripts`
    );
    assert(
        !content.includes('cdn.jsdelivr.net/npm/chart.js'),
        `${relPath} still loads Chart.js from CDN`
    );
    assert(
        !content.includes('chart.js@3.9.1'),
        `${relPath} still references Chart.js 3.9.1`
    );
});

const dom = new JSDOM('<!doctype html><body></body>', { runScripts: 'dangerously' });
const { window } = dom;
const script = window.document.createElement('script');
script.textContent = chartJs;
window.document.body.appendChild(script);

assert(typeof window.Chart === 'function', 'Chart global is not available after loading bundle');
assert(String(window.Chart.version || '').startsWith('4.5'), `Expected Chart.js 4.5.x, got ${window.Chart.version || 'unknown'}`);

if (errors.length) {
    console.error('Chart.js verification failed:\n- ' + errors.join('\n- '));
    process.exit(1);
}

console.log('Chart.js verification passed.');
