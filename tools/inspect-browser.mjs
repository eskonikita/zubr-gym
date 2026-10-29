import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const problems = [];
const network = [];
page.on('pageerror', error => problems.push({ type: 'pageerror', message: error.message }));
page.on('response', response => { if (response.status() >= 400) problems.push({ type: 'http', status: response.status(), url: response.url() }); });
page.on('request', request => { if (request.resourceType() === 'fetch' || request.resourceType() === 'xhr') network.push({ url: request.url(), method: request.method(), data: request.postData(), headers: request.headers() }); });
await fs.mkdir('reports/screenshots', { recursive: true });
const report = JSON.parse(await fs.readFile('reports/mirror.json', 'utf8'));
for (const item of report.files.filter(item => item.kind === 'page' && !item.error)) {
  const pathname = new URL(item.url).pathname;
  await page.goto('http://localhost:3000' + pathname);
  await page.waitForTimeout(600);
  const count = await page.locator('h1').count();
  for (let y = 0; y < await page.evaluate(() => document.body.scrollHeight); y += 900) {
    await page.evaluate(value => window.scrollTo(0, value), y);
    await page.waitForTimeout(100);
  }
  const broken = await page.locator('img').evaluateAll(images => images.filter(image => image.complete && !image.naturalWidth).map(image => image.getAttribute('src')));
  console.log(pathname, 'h1:', count, 'broken images:', broken.length);
  if (broken.length) problems.push({ type: 'images', pathname, broken });
}
await page.goto('http://localhost:3000/');
await page.waitForTimeout(1000);
await page.screenshot({ path: 'reports/screenshots/home-desktop.png', fullPage: true });
await page.setViewportSize({ width: 390, height: 844 });
await page.reload(); await page.waitForTimeout(1000);
await page.screenshot({ path: 'reports/screenshots/home-mobile.png', fullPage: true });
await fs.writeFile('reports/browser-inspection.json', JSON.stringify({ problems, network }, null, 2));
console.log('PROBLEMS', JSON.stringify(problems)); console.log('NETWORK', JSON.stringify(network));
await browser.close();
