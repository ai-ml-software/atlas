import { test, expect, open } from '../support/fixtures';

test.describe('Executive / owner (demo.exec@altusdemo.sa)', () => {
  test('executive overview shows portfolio capability figures', async ({ as }) => {
    const p = await as('exec');
    await open(p, 'hkp/exec');
    await expect(p.locator('.hkp-tile').first()).toBeVisible();
    await expect(p.locator('main')).toContainText(/%/);
  });

  test('board report is printable and explains the numbers', async ({ as }) => {
    const p = await as('exec');
    await open(p, 'hkp/exec/board');
    await expect(p.locator('h1')).toBeVisible();
    await p.emulateMedia({ media: 'print' });
    await expect(p.locator('.hkp-nav')).toBeHidden();              // print layout hides the chrome
    const pdf = await p.pdf({ format: 'A4' });                     // renders to PDF without error
    expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
  });

  test('executive sees no editing tools', async ({ as }) => {
    const p = await as('exec');
    await open(p, 'hkp/exec');
    await expect(p.locator('.hkp-nav')).not.toContainText('Website pages');
    await expect(p.locator('.hkp-nav')).not.toContainText('Assign learning');
  });
});
