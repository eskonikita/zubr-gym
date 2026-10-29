import fs from 'node:fs/promises';
import path from 'node:path';

const source = path.resolve('reference/bronxgym');
const target = path.resolve('public');
if (await fs.stat(target).then(() => true, () => false)) {
  throw new Error('public already exists. Keep your working edits: rename it manually before rebuilding the reference.');
}
await fs.cp(source, target, { recursive: true });
await fs.mkdir(path.join(target, 'local'), { recursive: true });
await fs.copyFile('tools/local-guard.js', path.join(target, 'local/guard.js'));
const csp = "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; media-src 'self' blob:; connect-src 'self'; frame-src 'none'; object-src 'none'; form-action 'none'; base-uri 'self'";
async function localize(dir) {
  for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
    const file = path.join(dir, entry.name);
    if (entry.isDirectory()) await localize(file);
    else if (entry.name.endsWith('.html')) {
      let html = await fs.readFile(file, 'utf8');
      html = html.replace('<head>', `<head><meta http-equiv="Content-Security-Policy" content="${csp}"><meta name="robots" content="noindex,nofollow"><script src="/local/guard.js"></script>`);
      html = html.replace(/https:\/\/bronxgym\.by(?=[/"'\s<])/g, 'http://zubr-gym.local');
      html = html.replace(/var GA_SRC="[^"]*";/g, 'var GA_SRC="";');
      await fs.writeFile(file, html);
    } else if (entry.name.endsWith('.js')) {
      const js = await fs.readFile(file, 'utf8');
      await fs.writeFile(file, js.replace(/const pm="G-[A-Z0-9]+"/g, 'const pm=""'));
    }
  }
}
await localize(target);
await fs.writeFile(path.join(target, 'robots.txt'), 'User-agent: *\nDisallow: /\n');
await fs.writeFile(path.join(target, '.htaccess'), `Options -Indexes -MultiViews\nDirectoryIndex index.html index.php\n<IfModule mod_headers.c>\n  Header always set X-Robots-Tag "noindex, nofollow"\n</IfModule>\n<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %{REQUEST_METHOD} !^(GET|HEAD)$\n  RewriteRule ^ - [R=503,L]\n  RewriteCond %{DOCUMENT_ROOT}/$1/index.html -f\n  RewriteRule ^(.+?)/?$ $1/index.html [L]\n</IfModule>\n`);
console.log('Working copy ready in public/; original preserved in reference/bronxgym/.');
