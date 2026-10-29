import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
await fs.mkdir('reports/screenshots', { recursive: true });
const previews = [];
for (const [name, width, height] of [['desktop', 1440, 1000], ['mobile', 390, 844]]) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto('http://localhost:3000/', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Отклонить все', exact: true }).click();
  const pageHeight = await page.evaluate(() => document.body.scrollHeight);
  for (let y = 0; y < pageHeight; y += height - 100) {
    await page.evaluate(value => scrollTo(0, value), y);
    await page.waitForTimeout(100);
  }
  await page.waitForTimeout(500);
  const missing = await page.locator('img').evaluateAll(xs => xs.filter(x => x.complete && !x.naturalWidth).map(x => x.src));
  await page.evaluate(() => scrollTo(0, 0));
  await page.waitForTimeout(300);
  await page.screenshot({ path: `reports/screenshots/home-${name}.png`, fullPage: true });
  previews.push({ name, width, missingImages: missing });
  await page.close();
}
await browser.close();
await fs.writeFile('reports/preview.json', JSON.stringify(previews, null, 2));
console.log(JSON.stringify(previews));
