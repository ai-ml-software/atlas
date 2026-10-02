import { defineConfig } from '@playwright/test';
import base from './playwright.config';

process.env.HKP_TEST_DATABASE = 'atlas_hospitality_test';
process.env.HKP_BASE_URL = 'http://127.0.0.1:8099/';

export default defineConfig({
  ...base,
  outputDir: 'test-results-studio',
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'report-studio' }]],
  use: { ...base.use, baseURL: process.env.HKP_BASE_URL },
  webServer: [base.webServer as any, {
    command: '"C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe" -S 127.0.0.1:8099 -t .. support/router.php',
    url: 'http://127.0.0.1:8099/en', reuseExistingServer: true, timeout: 30_000,
  }],
});
