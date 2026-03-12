# E2E Tests - QloApps

This directory contains end-to-end tests for QloApps using Playwright.

## Prerequisites

- Node.js LTS (16+)
- npm or yarn
- The QloApps application running locally or accessible at the configured BASE_URL

## Setup

### 1. Install Dependencies

```bash
npm install
```

### 2. Install Playwright Browsers

```bash
npx playwright install --with-deps
```

### 3. Configure Environment Variables

Copy the `.env.example` file to `.env` and update it with your test environment details:

```bash
cp tests/e2e/.env.example tests/e2e/.env
```

Update the `.env` file with your application URL and test credentials:

```env
BASE_URL=http://127.0.0.1/QloApps-develop/
CUSTOMER_EMAIL=pub@qloapps.com
CUSTOMER_PASSWORD=123456789
```

## Running Tests

### Run all tests

```bash
npm run test:e2e
```

### Run tests in CI mode (with GitHub reporter)

```bash
npm run test:e2e:ci
```

### Run tests in debug mode

```bash
npm run test:e2e:debug
```

### Run tests with UI mode

```bash
npm run test:e2e:ui
```

### Run tests in headed mode (visible browser)

```bash
npm run test:e2e:headed
```

### Run tests for a specific browser

```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

### Run tests matching a pattern

```bash
npx playwright test customer-login
```

### Run a specific test case

```bash
npx playwright test customer-login.spec.ts
```

## Test Structure

```
tests/e2e/
├── .env                  # Environment variables (should not be committed)
├── .env.example          # Example environment variables
├── specs/                # Test specifications
│   └── customer-login.spec.ts
├── test-cases/           # Test case definitions (YAML format)
│   └── customer-login-testcases.yaml
└── playwright.config.ts  # Playwright configuration
```

## Test Reports

After running tests, reports are generated in:

- **HTML Report**: `playwright-report/index.html`
- **JSON Report**: `test-results/results.json`
- **JUnit Report**: `test-results/junit.xml`

### View HTML Report

```bash
npx playwright show-report
```

## GitHub Actions CI/CD

Tests run automatically on Pull Requests against the `master` branch.

### Workflow Details

- **Trigger**: Pull requests on `master` branch
- **Runners**: Ubuntu latest
- **Browsers**: Chromium, Firefox, WebKit (parallel execution)
- **Reporters**: GitHub, HTML, JSON, JUnit
- **Artifacts**: Test reports and results retained for 30 days

### Setting GitHub Secrets

For CI/CD, add the following secrets to your GitHub repository:

- `BASE_URL`: Application URL (default: `http://127.0.0.1/QloApps-develop/`)
- `CUSTOMER_EMAIL`: Test customer email
- `CUSTOMER_PASSWORD`: Test customer password
- `INVALID_EMAIL`: Invalid email for testing
- `INVALID_PASSWORD`: Invalid password for testing
- `INVALID_EMAIL_FORMAT`: Invalid email format
- `SQL_INJECTION_EMAIL`: SQL injection test input
- `XSS_EMAIL`: XSS test input

## Writing Tests

Tests are written in TypeScript using Playwright. Each test file should end with `.spec.ts`.

### Example Test

```typescript
import { test, expect } from '@playwright/test';

test.describe('Customer Login', () => {
  const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1/QloApps-develop/';
  const CUSTOMER_EMAIL = process.env.CUSTOMER_EMAIL || 'pub@qloapps.com';
  const CUSTOMER_PASSWORD = process.env.CUSTOMER_PASSWORD || '123456789';

  test.beforeEach(async ({ page }) => {
    await page.goto(BASE_URL + 'login');
  });

  test('CL-001: Login with valid credentials', async ({ page }) => {
    await page.locator('form').nth(1).getByLabel('Email address').fill(CUSTOMER_EMAIL);
    await page.locator('form').nth(1).getByLabel('Password').fill(CUSTOMER_PASSWORD);
    await page.locator('form').nth(1).getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByRole('button', { name: /John/i })).toBeVisible({ timeout: 10000 });
  });
});
```

## Best Practices

1. **Use environment variables** for test data and credentials
2. **Name test cases** with clear identifiers (e.g., CL-001)
3. **Use proper locators** (getByRole, getByLabel, etc. before getBySelector)
4. **Add descriptive assertions** with meaningful error messages
5. **Run tests locally** before pushing to GitHub
6. **Check test reports** for failures and debug issues
7. **Keep tests independent** - avoid test interdependencies

## Troubleshooting

### Tests fail with "Authentication failed" message

- Verify credentials in `.env` file
- Ensure the application is running and accessible
- Check if the user account is active

### Tests timeout

- Increase timeout in `playwright.config.ts`
- Check network connectivity to the application
- Verify if the application is responding slowly

### Browser installation issues

```bash
npx playwright install --with-deps
```

### Clear cache and reinstall

```bash
rm -rf node_modules
npm install
npx playwright install --with-deps
```

## Additional Resources

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Test Best Practices](https://playwright.dev/docs/best-practices)
- [QloApps Documentation](https://qloapps.com/qlo-reservation-system)

## Support

For issues or questions, refer to the main repository documentation or contact the QloApps team.
