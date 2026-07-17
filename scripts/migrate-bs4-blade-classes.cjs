/**
 * Migrate common Bootstrap 4 class names to Bootstrap 5 in views, controllers, and JS.
 */
const fs = require('fs');
const path = require('path');

const scanRoots = [
    path.join(__dirname, '..', 'resources', 'views'),
    path.join(__dirname, '..', 'app'),
    path.join(__dirname, '..', 'public', 'js'),
];

const extensions = new Set(['.blade.php', '.php', '.js']);

const literalReplacements = [
    ['custom-control custom-checkbox', 'form-check'],
    ['custom-control custom-radio', 'form-check'],
    ['custom-control custom-switch', 'form-check form-switch'],
    ['custom-checkbox custom-checkbox-table custom-control', 'form-check custom-checkbox-table'],
    ['custom-checkbox custom-control', 'form-check'],
    ['custom-control-input', 'form-check-input'],
    ['custom-control-label', 'form-check-label'],
    ['form-row', 'row g-3'],
    ['badge badge-primary', 'badge bg-primary'],
    ['badge badge-secondary', 'badge bg-secondary'],
    ['badge badge-success', 'badge bg-success'],
    ['badge badge-danger', 'badge bg-danger'],
    ['badge badge-warning', 'badge bg-warning text-dark'],
    ['badge badge-info', 'badge bg-info text-dark'],
    ['badge badge-light', 'badge bg-light text-dark'],
    ['badge badge-dark', 'badge bg-dark'],
    ['badge-pill', 'rounded-pill'],
];

const regexReplacements = [
    [/badge badge-\{\{/g, 'badge bg-{{'],
    [/badge badge-' \+/g, "badge bg-' +"],
    [/badge badge-" \+/g, 'badge bg-" +'],
    [/badge badge-\$\{/g, 'badge bg-${'],
];

function walk(dir, files = []) {
    if (!fs.existsSync(dir)) {
        return files;
    }

    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walk(full, files);
        } else {
            const ext = path.extname(entry.name);
            if (extensions.has(ext) || entry.name.endsWith('.blade.php')) {
                files.push(full);
            }
        }
    }
    return files;
}

function migrateContent(content) {
    let updated = content;

    for (const [from, to] of literalReplacements) {
        if (updated.includes(from)) {
            updated = updated.split(from).join(to);
        }
    }

    for (const [pattern, replacement] of regexReplacements) {
        updated = updated.replace(pattern, replacement);
    }

    return updated;
}

let changedFiles = 0;
let totalReplacements = 0;

for (const root of scanRoots) {
    for (const file of walk(root)) {
        const original = fs.readFileSync(file, 'utf8');
        const content = migrateContent(original);

        if (content !== original) {
            const beforeCount = original.length;
            const afterCount = content.length;
            totalReplacements += Math.abs(beforeCount - afterCount) > 0 ? 1 : 0;
            fs.writeFileSync(file, content, 'utf8');
            changedFiles += 1;
            console.log('updated:', path.relative(path.join(__dirname, '..'), file));
        }
    }
}

console.log(`Done. ${changedFiles} files updated.`);
