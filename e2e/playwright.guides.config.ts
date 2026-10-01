import { defineConfig, devices } from '@playwright/test';

/**
 * Screen-recorded walkthroughs of the system (how to add a course, lessons and
 * quizzes by hand and with AI, and what a learner sees). Each guide is a real
 * Playwright run against the local Laragon site, slowed down and captioned, and
 * its video is saved to ../screen-recordings/<NN-name>.webm.
 *
 *   npm run guides                       record every walkthrough
 *   npx playwright test -c playwright.guides.config.ts -g "03"   one guide
 *
 * Same safety rails as the test suite: global-setup refuses to run unless
 * application/config/database.local.php points at a local database.
 */
export default defineConfig({
  testDir: './guides',
  timeout: 240_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  outputDir: './test-results/guides',
  reporter: [['list']],
  globalSetup: './support/global-setup.ts',
  globalTeardown: './support/global-teardown.ts',
  use: {
    baseURL: process.env.HKP_BASE_URL || 'http://localhost/atlas/atlas/',
    channel: 'msedge',
    viewport: { width: 1280, height: 800 },
    launchOptions: { slowMo: Number(process.env.GUIDE_SLOWMO || 220) },
    actionTimeout: 20_000,
  },
  webServer: {
    command: 'node support/mock-ai.mjs',
    url: 'http://127.0.0.1:8765/health',
    reuseExistingServer: true,
    timeout: 20_000,
  },
  projects: [
    { name: 'setup', testDir: './tests', testMatch: /auth\.setup\.ts/ },
    { name: 'guides', use: { ...devices['Desktop Edge'], channel: 'msedge', viewport: { width: 1280, height: 800 } }, dependencies: ['setup'] },
  ],
});
