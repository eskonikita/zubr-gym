import fs from 'node:fs/promises';
import path from 'node:path';

const source = path.resolve('public');
const destination = path.resolve('dist');
const base = '/' + (process.env.PAGES_BASE_PATH || 'zubr-gym').replace(/^\/+|\/+$/g, '') + '/';
const entries = await fs.readdir(source);
const escaped = entries.map(name => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
const rootPath = new RegExp('(["\'`(=])/(?!/)(' + escaped.join('|') + ')(?=[/"\'`?#)\\s])', 'g');

async function copy(directory, output) {
  await fs.mkdir(output, { recursive: true });
  for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
    const from = path.join(directory, entry.name);
    const to = path.join(output, entry.name);
    if (entry.isDirectory()) await copy(from, to);
    else if (/\.(html|css|js|json|webmanifest|xml|txt|svg)$/i.test(entry.name)) {
      let text = await fs.readFile(from, 'utf8');
      text = text.replace(rootPath, (_, quote, name) => quote + base + name);
      if (entry.name === 'index-DeYhi4WE.js') {
        text = text.replace('const a=e.replace(/^\\//,"")', `const a=e.replace(${JSON.stringify(base)},"/").replace(/^\\//,"")`);
      }
      text = text.replace(/(href\s*=\s*["'])\/(#?[^"']*)?(["'])/g, (match, start, suffix, end) => {
        if (suffix && !suffix.startsWith('#')) return match;
        return start + base + (suffix || '') + end;
      });
      await fs.writeFile(to, text);
    } else await fs.copyFile(from, to);
  }
}

await copy(source, destination);
await fs.writeFile(path.join(destination, '.nojekyll'), '');
console.log(`GitHub Pages files written to dist; base path: ${base}`);
