import fs from 'node:fs/promises';
import path from 'node:path';

// Add newly discovered files without overwriting working frontend edits.
const report = JSON.parse(await fs.readFile('reports/mirror.json', 'utf8'));
let copied = 0;
for (const item of report.files.filter(item => item.kind === 'asset' && !item.error)) {
  const relative = item.file.replace('reference/bronxgym/', '');
  const target = path.resolve('public', relative);
  if (await fs.stat(target).then(() => true, () => false)) continue;
  await fs.mkdir(path.dirname(target), { recursive: true });
  await fs.copyFile(item.file, target); copied++;
}
// Explicitly disable the reference site's analytics in the working copy.
async function disableAnalytics(dir) {
  for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
    const file = path.join(dir, entry.name);
    if (entry.isDirectory()) await disableAnalytics(file);
    else if (/\.(?:html|js)$/.test(entry.name)) {
      const text = await fs.readFile(file, 'utf8');
      const updated = text.replace(/var GA_SRC="[^"]*";/g, 'var GA_SRC="";').replace(/const pm="G-[A-Z0-9]+"/g, 'const pm=""');
      if (updated !== text) await fs.writeFile(file, updated);
    }
  }
}
await disableAnalytics('public');
console.log(`Added ${copied} assets. Working pages and existing assets preserved. Analytics disabled.`);
