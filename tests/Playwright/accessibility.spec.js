const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const login = async (page) => {
  await page.goto('/en/login');
  await page.getByLabel('Email').fill('a11y-user@test.local');
  await page.getByLabel('Password').fill('ScenarioPass123!');
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/admin\/car\/show$/);
};

const scanPage = async (page, path, theme = 'classic') => {
  await page.goto(path);
  await page.evaluate((selectedTheme) => {
    document.documentElement.dataset.theme = selectedTheme;
  }, theme);
  await page.evaluate(() => {
    document.querySelectorAll('[id^="sfwdt"], .sf-toolbar, .sf-toolbarreset').forEach((element) => element.remove());
  });
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();

  expect(accessibilityScanResults.violations).toEqual([]);
};

test.describe('accessibility smoke checks', () => {
  test('public auth pages have no obvious axe violations', async ({ page }) => {
    await scanPage(page, '/en/login');
    await scanPage(page, '/en/register');
    await scanPage(page, '/en/reset-password');
    await scanPage(page, '/en/reset-password/check-email');
  });

  test('core authenticated pages have no obvious axe violations', async ({ page }) => {
    await login(page);

    await scanPage(page, '/admin/car/show');
    await scanPage(page, '/admin/user/edit');
    await scanPage(page, '/admin/user/list');
    await scanPage(page, '/admin/booking');
    await scanPage(page, '/admin/expense/list/1');
    await scanPage(page, '/admin/messages/1');
    await scanPage(page, '/admin/parking');
    await scanPage(page, '/admin/payment/list/1');
    await scanPage(page, '/admin/trip/list/1');
  });

  test('dark theme has no obvious axe violations on core pages', async ({ page }) => {
    await login(page);

    await scanPage(page, '/admin/car/show', 'dark');
    await scanPage(page, '/admin/user/edit', 'dark');
    await scanPage(page, '/admin/messages/1', 'dark');
    await scanPage(page, '/admin/booking', 'dark');
    await scanPage(page, '/admin/expense/list/1', 'dark');
    await scanPage(page, '/admin/parking', 'dark');
    await scanPage(page, '/admin/payment/list/1', 'dark');
    await scanPage(page, '/admin/trip/list/1', 'dark');
  });

  test('classic theme has no obvious axe violations on core pages', async ({ page }) => {
    await login(page);

    await scanPage(page, '/admin/car/show', 'classic');
    await scanPage(page, '/admin/user/edit', 'classic');
    await scanPage(page, '/admin/messages/1', 'classic');
    await scanPage(page, '/admin/booking', 'classic');
  });
});
