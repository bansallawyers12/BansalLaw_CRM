/**
 * Remove embedded Bootstrap v4.3.1 CSS from public/css/app.min.css.
 * CRM layouts load Bootstrap 5 CSS before this file; app.min.css must keep only Font Awesome 5.8.1.
 */
const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'public', 'css', 'app.min.css');
const source = fs.readFileSync(target, 'utf8');

const strippedHeader = '/* Bootstrap 4 CSS removed — use Bootstrap 5 CSS loaded before this file */\n';
const faMarkers = [
    '/*!\r\n * Font Awesome Free 5.8.1',
    '/*!\n * Font Awesome Free 5.8.1',
];
const hasBootstrap4 = source.includes('Bootstrap v4.3.1');
const alreadyStripped = source.includes(strippedHeader.trim()) && !hasBootstrap4;

if (alreadyStripped) {
    console.log('app.min.css already has Bootstrap 4 CSS stripped; nothing to do.');
    process.exit(0);
}

if (!hasBootstrap4) {
    console.error('Could not find Bootstrap v4.3.1 block in app.min.css');
    process.exit(1);
}

const faIndex = faMarkers.map((marker) => source.indexOf(marker)).find((index) => index !== -1);

if (faIndex === undefined) {
    console.error('Could not locate Font Awesome boundary in app.min.css');
    process.exit(1);
}

const kept = source.slice(faIndex);
fs.writeFileSync(target, strippedHeader + kept, 'utf8');

console.log(`Stripped Bootstrap 4 CSS from app.min.css (${source.length} -> ${strippedHeader.length + kept.length} bytes)`);
