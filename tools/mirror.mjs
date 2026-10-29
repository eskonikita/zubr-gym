import fs from 'node:fs/promises';
import path from 'node:path';

const origin = 'https://bronxgym.by';
const root = path.resolve('reference/bronxgym');
const reportPath = path.resolve('reports/mirror.json');
const previous = await fs.readFile(reportPath, 'utf8').then(JSON.parse, () => ({ files: [] }));
const cached = new Map(previous.files.filter(item => !item.error).map(item => [item.url, item]));
const queue = [];
const seen = new Set();
const results = [];
const excluded = /^\/(?:api|admin|account|member|app|login|register|offline|_server)\b/;
const assetExt = /\.(?:css|js|mjs|png|jpe?g|webp|svg|gif|ico|avif|woff2?|ttf|otf|mp4|webm|pdf|webmanifest|json|xml|txt)$/i;
const decode = s => s.replace(/&amp;/g, '&').replace(/\\u002F/g, '/').replace(/\\\//g, '/');

function enqueue(value, base = origin + '/', type) {
  if (!value || /^(?:data:|mailto:|tel:|javascript:|#)/i.test(value)) return;
  let url;
  try { url = new URL(decode(value), base); } catch { return; }
  if (url.origin !== origin || excluded.test(url.pathname)) return;
  url.hash = ''; url.search = '';
  if (/[{}$<>]/.test(url.pathname) || url.pathname.includes('..')) return;
  const kind = type || (assetExt.test(url.pathname) ? 'asset' : 'page');
  if (kind === 'asset' && !assetExt.test(url.pathname)) return;
  if (seen.has(url.href)) return;
  seen.add(url.href); queue.push({ url: url.href, kind });
}

function collect(text, base, kind) {
  if (kind === 'page') {
    for (const m of text.matchAll(/\b(?:href|src|poster|content)=["']([^"']+)["']/g)) {
      if (m[1].startsWith('/') || m[1].startsWith(origin)) enqueue(m[1], base);
    }
    for (const m of text.matchAll(/\bsrcset=["']([^"']+)["']/g)) {
      for (const item of m[1].split(',')) enqueue(item.trim().split(/\s+/)[0], base);
    }
  }
  for (const m of text.matchAll(/["'`]((?:\.?\.?\/|assets\/|https:\/\/bronxgym\.by\/)[^"'`\s<>]*?\.(?:css|js|mjs|png|jpe?g|webp|svg|gif|ico|avif|woff2?|ttf|otf|mp4|webmanifest|webm|pdf))(?:[?#][^"'`]*)?["'`]/g)) {
    const value = m[1];
    enqueue(value.startsWith('assets/') ? '/' + value : value, base, 'asset');
  }
  for (const m of text.matchAll(/url\(\s*["']?([^\s"')]+)["']?\s*\)/g)) enqueue(m[1], base, 'asset');
  for (const m of text.matchAll(/<loc>([^<]+)<\/loc>/g)) enqueue(m[1], base);
  if (base.endsWith('webmanifest')) for (const m of text.matchAll(/"src"\s*:\s*"([^"]+)"/g)) enqueue(m[1], base, 'asset');

  // These galleries are assembled from filenames in the original JavaScript.
  if (base.endsWith('.js')) {
    for (const m of text.matchAll(/filename:"([^"]+\.(?:png|jpg))"/g)) {
      enqueue('/gallery/' + m[1], base, 'asset');
      enqueue('/thumbs/mosaic/gallery/' + m[1].replace(/\.[^.]+$/, '.webp'), base, 'asset');
    }
    for (const m of text.matchAll(/Ro\("([^"]+\.jpg)"/g)) enqueue('/coaches/' + m[1], base, 'asset');
  }
}

async function save(item) {
  const pathname = decodeURIComponent(new URL(item.url).pathname);
  const relative = item.kind === 'page' ? pathname.replace(/^\//, '').replace(/\/$/, '') + '/index.html' : pathname.slice(1);
  const file = path.resolve(root, relative.replace(/^\//, ''));
  if (!file.startsWith(root + path.sep)) throw new Error('Unsafe output path');
  if (!process.argv.includes('--refresh') && cached.has(item.url) && await fs.stat(file).then(() => true, () => false)) {
    const record = cached.get(item.url);
    if (/text|javascript|json|xml/.test(record.contentType) || /\.(?:js|css|webmanifest|xml)$/.test(pathname)) collect(await fs.readFile(file, 'utf8'), item.url, item.kind);
    return record;
  }
  let response;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      response = await fetch(item.url, { signal: AbortSignal.timeout(60000), headers: { 'User-Agent': 'ZubrGym-LocalMirror/1.0' } });
      if (response.ok || response.status < 500) break;
    } catch (error) { if (attempt === 2) throw error; }
  }
  if (!response.ok) return { ...item, status: response.status, error: response.statusText || `HTTP ${response.status}` };
  const contentType = response.headers.get('content-type') || '';
  if (item.kind === 'asset' && contentType.includes('text/html')) return { ...item, status: response.status, error: 'Asset returned HTML' };
  const bytes = Buffer.from(await response.arrayBuffer());
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, bytes);
  if (/text|javascript|json|xml/.test(contentType) || /\.(?:js|css|webmanifest|xml)$/.test(pathname)) collect(bytes.toString('utf8'), item.url, item.kind);
  return { ...item, status: response.status, contentType, bytes: bytes.length, file: path.relative(path.resolve('.'), file).replaceAll('\\', '/') };
}

enqueue('/'); enqueue('/sitemap.xml', origin, 'asset'); enqueue('/robots.txt', origin, 'asset'); enqueue('/manifest.webmanifest');
for (const value of JSON.parse(await fs.readFile('tools/mirror-assets.json', 'utf8'))) enqueue(value, origin, 'asset');
for (const value of process.argv.slice(2).filter(value => value.startsWith('/'))) enqueue(value);
await fs.mkdir(root, { recursive: true });
while (queue.length) {
  const batch = queue.splice(0, 5);
  const done = await Promise.all(batch.map(async item => {
    try { return await save(item); } catch (error) { return { ...item, error: error.message }; }
  }));
  results.push(...done);
  for (const result of done) if (result.error) console.log('FAILED', result.url, result.error);
  if (results.length % 25 < 5 || queue.length === 0) console.log(`Processed ${results.length}; queued ${queue.length}`);
}
await fs.mkdir(path.dirname(reportPath), { recursive: true });
const report = { origin, capturedAt: new Date().toISOString(), files: results, pages: results.filter(r => r.kind === 'page' && !r.error).length, assets: results.filter(r => r.kind === 'asset' && !r.error).length, bytes: results.reduce((n, r) => n + (r.bytes || 0), 0), failures: results.filter(r => r.error) };
await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ pages: report.pages, assets: report.assets, bytes: report.bytes, failures: report.failures.length }));
