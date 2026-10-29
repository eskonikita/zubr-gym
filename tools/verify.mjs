import fs from 'node:fs/promises';
import path from 'node:path';
const report = JSON.parse(await fs.readFile('reports/mirror.json', 'utf8'));
const failures = [];
for (const item of report.files.filter(item => !item.error)) {
  const relative = item.file.replace('reference/bronxgym/', '');
  const file = path.resolve('public', relative);
  if (!await fs.stat(file).then(stat => stat.isFile(), () => false)) failures.push(`Missing: ${relative}`);
  if (item.kind === 'page') {
    const html = await fs.readFile(file, 'utf8');
    if (!html.includes('/local/guard.js') || !html.includes('noindex,nofollow')) failures.push(`Local protections absent: ${relative}`);
  }
}
if (report.failures.length) failures.push(...report.failures.map(item => `${item.url}: ${item.error}`));
console.log(`${report.pages} pages; ${report.assets} assets; ${(report.bytes / 1024 / 1024).toFixed(1)} MiB. Checked local files.`);
if (failures.length) { console.error(failures.join('\n')); process.exitCode = 1; }
else console.log('Verification passed.');

