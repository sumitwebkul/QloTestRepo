import { test, expect } from '@playwright/test';

test.describe('Customer Login', () => {

  const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1/QloApps-develop/';
  const CUSTOMER_EMAIL = process.env.CUSTOMER_EMAIL || 'pub@qloapps.com';
  const CUSTOMER_PASSWORD = process.env.CUSTOMER_PASSWORD || '123456789';
  const INVALID_EMAIL = process.env.INVALID_EMAIL || 'unregistered_user@example.com';
  const INVALID_PASSWORD = process.env.INVALID_PASSWORD || 'WrongPassword@123';
  const INVALID_EMAIL_FORMAT = process.env.INVALID_EMAIL_FORMAT || 'invalid-email-format';
  const SQL_INJECTION_EMAIL = process.env.SQL_INJECTION_EMAIL || "' OR '1'='1";
  const XSS_EMAIL = process.env.XSS_EMAIL || "<script>alert('xss')</script>";

  test.beforeEach(async ({ page }) => {
    await page.goto(BASE_URL + 'login');
  });

  test('CL-001: Login with valid email and password', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });
  });

  test('CL-002: Login with valid email and wrong password', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(INVALID_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('Authentication failed')).toBeVisible({ timeout: 10000 });
  });

  test('CL-003: Login with unregistered email', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(INVALID_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('Authentication failed')).toBeVisible({ timeout: 10000 });
  });

  test('CL-004: Login with empty email and password', async ({ page }) => {
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('An email address required')).toBeVisible({ timeout: 10000 });
  });

  test('CL-005: Login with invalid email format', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(INVALID_EMAIL_FORMAT);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/login/);
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
  });

  test('CL-006: SQL injection attempt in email field', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(SQL_INJECTION_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/login/);
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
  });

  test('CL-007: XSS script injection attempt', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(XSS_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/login/);
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
  });

  test('CL-008: Verify redirect after successful login', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });
  });

  test('CL-009: Verify session persists after refresh', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });
    
    await page.reload();
    
    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });
  });

  test('CL-010: Verify logout after login', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: /John/i }).click();
    await page.getByRole('link', { name: 'Logout' }).click();

    await expect(page.getByRole('link', { name: 'Sign in' })).toBeVisible({ timeout: 10000 });
  });

});
