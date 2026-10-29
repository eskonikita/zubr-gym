import { test, expect } from '@playwright/test';
import fs from 'node:fs';
const report = JSON.parse(fs.readFileSync('reports/mirror.json', 'utf8'));
const routes = report.files.filter(item => item.kind === 'page' && !item.error).map(item => new URL(item.url).pathname);

test('all public pages open directly and render on desktop and mobile', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response.status(), route).toBe(200);
      await expect(page.locator('h1'), route).toHaveCount(1);
      await page.waitForTimeout(120);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), route + ' horizontal overflow').toBe(true);
    }
  }
  expect(errors).toEqual([]);
});

test('navigation, group tabs, cookie controls and forms stay local', async ({ page }) => {
  const external = [];
  const httpErrors = [];
  page.on('request', request => { if (new URL(request.url()).origin !== 'http://localhost:3000') external.push(request.url()); });
  page.on('response', response => { if (response.status() >= 400) httpErrors.push(response.url()); });
  await page.goto('/');
  await page.getByRole('button', { name: 'Отклонить все', exact: true }).click();
  await expect(page.locator('#cookie-banner')).toHaveCount(0);
  for (const name of ['Направления', 'Для кого', 'Клуб', 'Информация']) {
    await page.locator('header').getByRole('button', { name, exact: true }).hover();
    await page.waitForTimeout(150);
  }
  await page.getByRole('tab', { name: 'Детям и подросткам', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Детям и подросткам', exact: true })).toBeVisible();
  await page.locator('a[href="/info/schedule"]').first().click();
  await expect(page).toHaveURL(/\/info\/schedule$/);
  await expect(page.locator('h1')).toBeVisible();
  await page.goto('/');
  await page.locator('input[name="name"]').fill('Локальная проверка');
  await page.locator('input[type="tel"]').fill('+375291234567');
  await page.locator('form input[type="checkbox"]').check();
  await page.getByRole('button', { name: 'Отправить заявку', exact: true }).click();
  await expect(page.locator('#zubr-local-notice')).toBeVisible();
  expect(external).toEqual([]);
  expect(httpErrors).toEqual([]);
});
