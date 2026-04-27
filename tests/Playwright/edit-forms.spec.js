const { test, expect } = require('@playwright/test');

const login = async (page) => {
  await page.goto('/en/login');
  await page.getByLabel('Email').fill('a11y-user@test.local');
  await page.getByLabel('Password').fill('ScenarioPass123!');
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/admin\/car\/show$/);
};

const openEditFormFromList = async (page, listPath, rowText) => {
  await page.goto(listPath);

  const row = page.locator('tbody tr', { hasText: rowText }).first();
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Edit' }).click();
};

test.describe('edit forms', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('expense edit submits successfully', async ({ page }) => {
    await openEditFormFromList(page, '/admin/expense/list/1', 'Playwright expense seed');

    await expect(page).toHaveURL(/\/admin\/expense\/edit\/\d+$/);
    await page.getByLabel('Name').fill('Playwright expense updated');
    await page.getByRole('button', { name: 'Update expense' }).click();

    await expect(page).toHaveURL(/\/admin\/expense\/list(?:\/1)?$/);
    await expect(page.locator('.alert-success')).toContainText('Expense updated.');
    await expect(page.locator('.table > tbody').first()).toContainText('Playwright expense updated');
  });

  test('payment edit submits successfully', async ({ page }) => {
    await openEditFormFromList(page, '/admin/payment/list/1', 'Playwright payment seed');

    await expect(page).toHaveURL(/\/admin\/payment\/edit\/\d+$/);
    await page.locator('[name$="[comment]"]').fill('Playwright payment updated');
    await page.getByRole('button', { name: 'Update payment' }).click();

    await expect(page).toHaveURL(/\/admin\/payment\/list(?:\/1)?$/);
    await expect(page.locator('.alert-success')).toContainText('Payment updated.');
    await expect(page.locator('.table > tbody').first()).toContainText('Playwright payment updated');
  });

  test('trip edit submits successfully', async ({ page }) => {
    await openEditFormFromList(page, '/admin/trip/list/1', 'Playwright trip seed');

    await expect(page).toHaveURL(/\/admin\/trip\/edit\/\d+$/);
    await page.locator('[name$="[comment]"]').fill('Playwright trip updated');
    await page.getByRole('button', { name: 'Update trip' }).click();

    await expect(page).toHaveURL(/\/admin\/trip\/list(?:\/1)?$/);
    await expect(page.locator('.alert-success')).toContainText('Trip updated.');
    await expect(page.locator('.table > tbody').first()).toContainText('Playwright trip updated');
  });
});
