/**
 * Remove embedded Font Awesome 5.x CSS from public/css/app.min.css.
 * CRM layouts load Font Awesome 7 via components/font-awesome (fontawesome.min.css).
 */
const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'public', 'css', 'app.min.css');
let source = fs.readFileSync(target, 'utf8');

const strippedHeader = '/* Bootstrap 4 CSS removed — use Bootstrap 5 CSS loaded before this file */\n';
const faRemovedHeader = '/* Font Awesome 5 CSS removed — use public/css/fontawesome.min.css (FA7) */\n';

if (source.includes(faRemovedHeader.trim()) && !source.includes('Font Awesome Free 5.8.1')) {
    console.log('app.min.css already has Font Awesome 5 CSS stripped; nothing to do.');
    process.exit(0);
}

const faStartMarkers = [
    '/*!\r\n * Font Awesome Free 5.8.1',
    '/*!\n * Font Awesome Free 5.8.1',
];

let faIndex = -1;
for (const marker of faStartMarkers) {
    const idx = source.indexOf(marker);
    if (idx !== -1) {
        faIndex = idx;
        break;
    }
}

if (faIndex === -1) {
    console.log('No Font Awesome 5 block found in app.min.css; nothing to strip.');
    process.exit(0);
}

// Keep any content before FA (header comment only) and any content after FA block.
// FA block runs until end of file or Material Icons @font-face if present after.
const materialMarker = "@font-face{font-family:'Material Icons'";
const materialIndex = source.indexOf(materialMarker, faIndex);

let kept = '';
if (materialIndex !== -1) {
    kept = source.slice(materialIndex);
}

const header = source.startsWith(strippedHeader.trim())
    ? strippedHeader + faRemovedHeader
    : faRemovedHeader;

fs.writeFileSync(target, header + kept, 'utf8');
console.log(`Stripped Font Awesome 5 CSS from app.min.css (${source.length} -> ${header.length + kept.length} bytes)`);
