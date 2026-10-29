/** Read-only integration check against the running local MODX site. */
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';

const base = process.env.MODX_TEST_URL || 'http://zubr-gym.local';
const manifest = JSON.parse(fs.readFileSync('cms/static-pages.json', 'utf8'));
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
try {
  const page = await browser.newPage();
  await page.route('https://**', route => route.abort());
  const cookiePage = fs.existsSync('cms/cookie-setup.json') ? [{ uri: JSON.parse(fs.readFileSync('cms/cookie-setup.json', 'utf8')).uri }] : [];
  for (const item of [...manifest.pages.filter(item => !item.redirect), ...cookiePage, { uri: '/503/' }]) {
    const response = await page.goto(base + item.uri);
    const data = await page.evaluate(() => {
      const meta = key => document.querySelector(`meta[name="${key}"],meta[property="${key}"]`)?.content;
      return {
        title: document.title,
        titleCount: document.querySelectorAll('title').length,
        description: meta('description'),
        ogTitle: meta('og:title'), twitterTitle: meta('twitter:title'),
        ogDescription: meta('og:description'), twitterDescription: meta('twitter:description'),
        image: meta('og:image'), twitterImage: meta('twitter:image'),
        card: meta('twitter:card'), robots: meta('robots'),
        canonical: document.querySelector('link[rel="canonical"]')?.href,
        canonicalCount: document.querySelectorAll('link[rel="canonical"]').length,
      };
    });
    assert.equal(data.titleCount, 1, item.uri);
    assert.ok(data.title && data.description, item.uri);
    assert.equal(data.title, data.ogTitle, item.uri);
    assert.equal(data.title, data.twitterTitle, item.uri);
    assert.equal(data.description, data.ogDescription, item.uri);
    assert.equal(data.description, data.twitterDescription, item.uri);
    assert.equal(data.image, data.twitterImage, item.uri);
    assert.equal(data.card, 'summary_large_image', item.uri);
    assert.ok(/^https?:\/\//.test(data.image), item.uri);
    const error = ['/404/', '/503/'].includes(item.uri);
    assert.equal(response.status(), error ? Number(item.uri.slice(1, 4)) : 200, item.uri);
    assert.equal(data.canonicalCount, error ? 0 : 1, item.uri);
    if (!error) assert.equal(data.canonical, base + item.uri, item.uri);
    results.push({ uri: item.uri, status: response.status(), title: data.title, robots: data.robots });
  }
  const unknown = await page.goto(base + '/missing-seo-verification/');
  assert.equal(unknown.status(), 404);
  assert.equal(await page.locator('link[rel="canonical"]').count(), 0);
  const sitemap = await (await page.request.get(base + '/sitemap.xml')).text();
  assert.ok(sitemap.includes('<urlset') && sitemap.includes(base + '/info/prices/'));
  for (const path of ['/404/', '/503/']) assert.ok(!sitemap.includes(base + path), path);
  for (const path of ['/privacy-policy/', '/terms-of-use/']) assert.ok(sitemap.includes(base + path), path);
  for (const item of cookiePage) assert.ok(sitemap.includes(base + item.uri), item.uri);
  console.log(JSON.stringify({ checkedPages: results.length, pages: results, sitemap: 'passed', unknown404: 'passed' }, null, 2));
} finally {
  await browser.close();
}
