import fs from 'node:fs/promises';
import path from 'node:path';

const source = path.resolve('public');
const destination = path.resolve('dist');
const base = '/' + (process.env.PAGES_BASE_PATH || 'zubr-gym').replace(/^\/+|\/+$/g, '') + '/';
// GitHub Pages stays a static preview. Never export CMS runtime or local credentials.
const cmsDirectories = new Set(['core', 'manager', 'connectors', 'setup']);
const entries = (await fs.readdir(source)).filter(name => !cmsDirectories.has(name) && !name.endsWith('.php') && !name.startsWith('.')); 
const escaped = entries.map(name => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
const rootPath = new RegExp('(["\'`(=])/(?!/)(' + escaped.join('|') + ')(?=[/"\'`?#)\\s])', 'g');

async function copy(directory, output) {
  await fs.mkdir(output, { recursive: true });
  for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
    const from = path.join(directory, entry.name);
    const to = path.join(output, entry.name);
    const relative = path.relative(source, from).replaceAll('\\', '/');
    if ((directory === source && cmsDirectories.has(entry.name)) || relative === 'assets/components' || entry.name.endsWith('.php') || entry.name.startsWith('.')) continue;
    if (entry.isDirectory()) await copy(from, to);
    else if (/\.(html|css|js|json|webmanifest|xml|txt|svg)$/i.test(entry.name)) {
      let text = await fs.readFile(from, 'utf8');
      text = text.replace(rootPath, (_, quote, name) => quote + base + name);
      if (/\.html$/i.test(entry.name)) {
        text = text.replace(/\bsrcset\s*=\s*(["'])(.*?)\1/gi, (_, quote, candidates) => {
          const rewritten = candidates.split(',').map(candidate => {
            const value = candidate.trim();
            if (value.startsWith(base) || !/^\/(?!\/)/.test(value)) return value;
            return base + value.slice(1);
          }).join(', ');
          return `srcset=${quote}${rewritten}${quote}`;
        });
      }
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
