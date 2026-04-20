const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const login = async (page) => {
  await page.goto('/en/login');
  await page.getByLabel('Email').fill('a11y-user@test.local');
  await page.getByLabel('Password').fill('ScenarioPass123!');
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/admin\/car\/show$/);
};

const scanPage = async (page, path) => {
  await page.goto(path);
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
    await scanPage(page, '/reset-password');
    await scanPage(page, '/reset-password/check-email');
  });

  test('core authenticated pages have no obvious axe violations', async ({ page }) => {
    await login(page);

    await scanPage(page, '/admin/car/show');
    await scanPage(page, '/admin/user/list');
    await scanPage(page, '/admin/booking');
    await scanPage(page, '/admin/expense/list/1');
    await scanPage(page, '/admin/messages/1');
    await scanPage(page, '/admin/parking');
    await scanPage(page, '/admin/payment/list/1');
    await scanPage(page, '/admin/trip/list/1');
  });
});
