import { defineConfig } from '@playwright/test';
import base from './playwright.config';

// Requires: php index.php ha_test run (builds ONLY atlas_hospitality_test).
// Never point destructive browser fixtures at a learner's working database.
process.env.HKP_TEST_DATABASE = 'atlas_hospitality_test';
process.env.HKP_BASE_URL = 'http://127.0.0.1:8099/';

export default defineConfig({
  ...base,
  outputDir: 'test-results-isolated',
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'report-isolated' }]],
  use: { ...base.use, baseURL: process.env.HKP_BASE_URL },
  webServer: [
    base.webServer as any,
    {
      command: '"C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe" -S 127.0.0.1:8099 -t .. support/router.php',
      url: 'http://127.0.0.1:8099/en',
      reuseExistingServer: false,
      timeout: 30_000,
      stderr: 'ignore',
      stdout: 'ignore',
    },
  ],
});
