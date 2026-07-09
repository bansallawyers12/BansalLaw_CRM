const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const swalJs = fs.readFileSync(path.join(root, 'public/js/sweetalert2.min.js'), 'utf8');

const dom = new JSDOM('<!doctype html><body></body>', { runScripts: 'dangerously' });
const { window } = dom;

const script = window.document.createElement('script');
script.textContent = swalJs;
window.document.body.appendChild(script);

const result = {
    hasSwal: typeof window.Swal === 'function',
    hasFire: typeof window.Swal.fire === 'function',
    version: window.Swal && window.Swal.version
};

console.log(JSON.stringify(result));

if (!result.hasSwal || !result.hasFire) {
    process.exit(1);
}

if (!String(result.version || '').startsWith('11.')) {
    process.exit(2);
}
