/**
 * Verify Bootstrap 5 CSS/JS are wired consistently and Bootstrap 4 CSS is not bundled in app.min.css.
 */
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

const appMinCss = read('public/css/app.min.css');
assert(!appMinCss.includes('Bootstrap v4.3.1'), 'app.min.css still contains Bootstrap v4.3.1 CSS');
assert(!appMinCss.includes('Font Awesome Free 5.8.1'), 'app.min.css still contains Font Awesome 5 CSS — use fontawesome.min.css');

const fontAwesomeCss = read('public/css/fontawesome.min.css');
assert(fontAwesomeCss.includes('Font Awesome'), 'public/css/fontawesome.min.css missing Font Awesome bundle');

const bootstrapCss = read('public/css/bootstrap.min.css');
assert(/Bootstrap\s+v5\.3/.test(bootstrapCss), 'public/css/bootstrap.min.css is not Bootstrap 5.3.x');

const bootstrapJs = read('public/js/bootstrap.bundle.min.js');
assert(bootstrapJs.includes('bootstrap'), 'public/js/bootstrap.bundle.min.js missing bootstrap global');

const layoutFiles = [
    'resources/views/layouts/crm_client_detail.blade.php',
    'resources/views/layouts/crm_client_detail_dashboard.blade.php',
    'resources/views/layouts/crm-login.blade.php',
];

layoutFiles.forEach((relPath) => {
    const content = read(relPath);
    assert(
        content.includes("components.bootstrap5-assets") || content.includes("asset('css/bootstrap.min.css')"),
        `${relPath} missing Bootstrap 5 CSS include`
    );
    assert(
        content.includes("components.font-awesome") || content.includes("css/fontawesome.min.css"),
        `${relPath} missing Font Awesome 7 CSS include`
    );
    assert(
        content.includes("components.bootstrap5-scripts") || content.includes("asset('js/bootstrap.bundle.min.js')"),
        `${relPath} missing Bootstrap 5 JS include`
    );
    assert(
        !content.includes('cdn.jsdelivr.net/npm/bootstrap@5.3.7'),
        `${relPath} still uses Bootstrap 5 CDN instead of shared components`
    );
});

['crm_client_detail.blade.php', 'crm_client_detail_dashboard.blade.php', 'crm-login.blade.php'].forEach((name) => {
    const relPath = `resources/views/layouts/${name}`;
    const content = read(relPath);
    const assetsPos = content.indexOf('components.bootstrap5-assets');
    const appMinPos = content.indexOf('css/app.min.css');
    assert(assetsPos !== -1 && appMinPos !== -1 && assetsPos < appMinPos, `${relPath} must load Bootstrap 5 CSS before app.min.css`);
});

if (errors.length) {
    console.error('Bootstrap 5 verification failed:\n- ' + errors.join('\n- '));
    process.exit(1);
}

console.log('Bootstrap 5 verification passed.');
