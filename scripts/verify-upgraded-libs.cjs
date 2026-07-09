const { spawnSync } = require('child_process');
const path = require('path');

const scripts = [
    'verify-jquery-load-order.cjs',
    'verify-intl-tel-input.cjs',
    'verify-bootstrap5.cjs',
    'verify-bootstrap-compat.cjs',
    'verify-sweetalert2.cjs',
    'verify-chartjs.cjs'
];

const root = path.join(__dirname, '..');
let failed = false;

scripts.forEach((script) => {
    const result = spawnSync(process.execPath, [path.join(__dirname, script)], {
        cwd: root,
        encoding: 'utf8'
    });

    const label = script.replace('.cjs', '');
    if (result.status === 0) {
        const output = (result.stdout || '').trim();
        console.log(`[pass] ${label}${output ? ': ' + output : ''}`);
        return;
    }

    failed = true;
    console.error(`[fail] ${label}`);
    if (result.stdout) {
        console.error(result.stdout.trim());
    }
    if (result.stderr) {
        console.error(result.stderr.trim());
    }
});

if (failed) {
    process.exit(1);
}

console.log('All upgraded library checks passed.');
