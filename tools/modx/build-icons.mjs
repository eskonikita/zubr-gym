/** Render the transparent red boxing glove into browser, Apple and Android icon formats. */
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const svg = fs.readFileSync('public/favicon.svg', 'utf8');

const outputs = [
  ['favicon-16x16.png', 16], ['favicon-32x32.png', 32], ['favicon-48x48.png', 48],
  ['apple-touch-icon.png', 180], ['web-app-manifest-192x192.png', 192],
  ['web-app-manifest-512x512.png', 512], ['web-app-manifest-maskable-512x512.png', 512, true],
];
const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
  const page = await browser.newPage();
  for (const [name, size, masked] of outputs) {
    const png = await page.evaluate(async ({ source, size, opaque }) => {
      const img = new Image();
      img.src = 'data:image/svg+xml;base64,' + btoa(source);
      await img.decode();
      const canvas = document.createElement('canvas'); canvas.width = canvas.height = size;
      const context = canvas.getContext('2d');
      context.drawImage(img, 0, 0, size, size);
      return canvas.toDataURL('image/png').split(',')[1];
    }, { source: svg, size, opaque: false });
    fs.writeFileSync('public/' + name, Buffer.from(png, 'base64'));
  }
} finally { await browser.close(); }

// Multi-resolution ICO containing lossless PNG frames (16, 32 and 48 pixels).
const frames = outputs.slice(0, 3).map(([name, size]) => ({ size, data: fs.readFileSync('public/' + name) }));
const header = Buffer.alloc(6 + 16 * frames.length); header.writeUInt16LE(1, 2); header.writeUInt16LE(frames.length, 4);
let offset = header.length;
frames.forEach(({ size, data }, i) => {
  const at = 6 + i * 16; header[at] = header[at + 1] = size;
  header.writeUInt16LE(1, at + 4); header.writeUInt16LE(32, at + 6);
  header.writeUInt32LE(data.length, at + 8); header.writeUInt32LE(offset, at + 12); offset += data.length;
});
fs.writeFileSync('public/favicon.ico', Buffer.concat([header, ...frames.map(frame => frame.data)]));
fs.writeFileSync('public/site.webmanifest', JSON.stringify({
  id: './', name: 'ZUBR GYM — тайский бокс и кикбоксинг', short_name: 'ZUBR GYM', lang: 'ru',
  description: 'Тайский бокс и кикбоксинг для детей и взрослых в Гродно.',
  start_url: './', scope: './', display: 'standalone', background_color: '#edeef0', theme_color: '#de0014',
  icons: [
    { src: 'web-app-manifest-192x192.png?v=glove-1', sizes: '192x192', type: 'image/png', purpose: 'any' },
    { src: 'web-app-manifest-512x512.png?v=glove-1', sizes: '512x512', type: 'image/png', purpose: 'any' },
  ],
}, null, 2) + '\n');
console.log('Created SVG, multi-size ICO, 7 PNG icons and site.webmanifest.');

// Keep distinct URLs for the glove to avoid reusing the previous favicon cache.
for (const [source,target] of [['favicon.svg','boxing-glove.svg'],['favicon.ico','boxing-glove.ico'],['favicon-16x16.png','boxing-glove-16.png'],['favicon-32x32.png','boxing-glove-32.png'],['favicon-48x48.png','boxing-glove-48.png'],['web-app-manifest-512x512.png','favicon.png']]) fs.copyFileSync('public/'+source,'public/'+target);
