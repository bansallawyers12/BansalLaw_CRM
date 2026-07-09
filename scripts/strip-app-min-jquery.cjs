/**
 * Remove embedded jQuery 1.9.1 from public/js/app.min.js.
 * CRM layouts load jQuery 3.7.1 from CDN first; app.min.js must not overwrite it.
 */
const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'public', 'js', 'app.min.js');
const source = fs.readFileSync(target, 'utf8');

const popperStart = 'function(e,t){"object"==typeof exports&&"undefined"!=typeof module?module.exports=t():"function"==typeof define&&define.amd?define(t):e.Popper=t()}';
const marker = `}),${popperStart}`;
const strippedHeader = '/* jQuery removed — use CDN jQuery 3.7.1 loaded before this file */\n';
const hasEmbeddedJqueryBundle = source.trimStart().startsWith('!function(e,t)') && source.includes('S.jQuery=S.$=E');
const alreadyStripped = source.includes(strippedHeader.trim()) && source.includes('0,' + popperStart) && !hasEmbeddedJqueryBundle;

if (alreadyStripped) {
    console.log('app.min.js already has jQuery stripped; nothing to do.');
    process.exit(0);
}

if (!hasEmbeddedJqueryBundle) {
    console.error('Could not find embedded jQuery v1.9.1 bundle in app.min.js');
    process.exit(1);
}

const index = source.indexOf(marker);

if (index === -1) {
    console.error('Could not locate jQuery/Popper boundary in app.min.js');
    process.exit(1);
}

const stripped = source.slice(index + 3);
const prefix = '0,';
fs.writeFileSync(target, strippedHeader + prefix + stripped, 'utf8');

console.log(`Stripped jQuery from app.min.js (${source.length} -> ${strippedHeader.length + stripped.length} bytes)`);
