import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end suite for altus HK&P + the Academy LMS.
 *
 * Runs against the local Laragon site (never production): global-setup refuses
 * to start unless application/config/database.local.php points at a local DB.
 * Uses the Microsoft Edge that ships with Windows, so no browser download.
 *
 *   npx playwright test                 all projects
 *   npx playwright test --project=desktop
 *   npx playwright show-report
 */
export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,          // tests share one database; keep order deterministic
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'report' }]],
  globalSetup: './support/global-setup.ts',
  globalTeardown: './support/global-teardown.ts',
  use: {
    baseURL: process.env.HKP_BASE_URL || 'http://localhost/atlas/atlas/',
    channel: 'msedge',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    actionTimeout: 15_000,
  },
  // OpenAI-compatible mock so AI features run end to end without a paid key.
  webServer: {
    command: 'node support/mock-ai.mjs',
    url: 'http://127.0.0.1:8765/health',
    reuseExistingServer: true,
    timeout: 20_000,
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'desktop',
      use: { ...devices['Desktop Edge'], channel: 'msedge', viewport: { width: 1440, height: 900 } },
      dependencies: ['setup'],
      testIgnore: /mobile\.spec\.ts/,
    },
    {
      name: 'mobile',
      use: { channel: 'msedge', viewport: { width: 390, height: 844 }, isMobile: false, hasTouch: true, deviceScaleFactor: 2 },
      dependencies: ['setup'],
      testMatch: /mobile\.spec\.ts/,
    },
  ],
});
