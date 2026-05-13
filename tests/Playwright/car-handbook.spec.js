const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const createTestImage = () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'car-handbook-'));
  const filePath = path.join(dir, 'email-logo.png');
  const pngBuffer = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl7s2QAAAAASUVORK5CYII=',
    'base64',
  );

  fs.writeFileSync(filePath, pngBuffer);

  return filePath;
};

const login = async (page) => {
  await page.goto('/en/login');
  await page.getByLabel('Email').fill('a11y-user@test.local');
  await page.getByLabel('Password').fill('ScenarioPass123!');
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/\/admin\/car\/show$/);
};

test.describe('car handbook', () => {
  test('photo upload inserts markdown before save and opens as modal thumbnail', async ({ page }) => {
    await login(page);

    await page.locator('a[href*="/handbook"]').first().click();
    await expect(page).toHaveURL(/\/admin\/car\/\d+\/handbook(?:\/edit)?$/);

    if (!page.url().endsWith('/edit')) {
      await page.getByRole('link', { name: 'Edit' }).click();
      await expect(page).toHaveURL(/\/admin\/car\/\d+\/handbook\/edit$/);
    }

    const chooserPromise = page.waitForEvent('filechooser');
    await page.locator('#photo-add-btn').click();
    const chooser = await chooserPromise;

    expect(chooser).toBeTruthy();
    expect(await chooser.isMultiple()).toBe(true);
    const input = await chooser.element();
    expect(await input.getAttribute('type')).toBe('file');

    const imagePath = createTestImage();

    try {
      await chooser.setFiles(imagePath);
      await expect(page.locator('.photo-preview-item')).toHaveCount(1);
      await expect(page.locator('#car_handbook_form_content')).toHaveValue(/!\[email-logo\]\(handbook-upload:\/\/.+\)$/);

      await page.getByRole('button', { name: 'Save handbook' }).click();
      await expect(page).toHaveURL(/\/admin\/car\/\d+\/handbook$/);

      const handbookImage = page.locator('.handbook-markdown img[alt="email-logo"]').last();
      await expect(handbookImage).toBeVisible();
      await expect(handbookImage).toHaveClass(/message-photo-thumb/);

      const handbookImageSrc = await handbookImage.getAttribute('src');
      expect(handbookImageSrc).toMatch(/\/admin\/car\/\d+\/handbook\/attachments\//);

      await handbookImage.click();
      await expect(page.locator('#photoModal')).toBeVisible();
      await expect(page.locator('#photoModalImg')).toHaveAttribute('src', handbookImageSrc);
    } finally {
      fs.rmSync(path.dirname(imagePath), { recursive: true, force: true });
    }
  });
});
