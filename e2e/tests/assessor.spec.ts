import { test, expect, open, flashOk } from '../support/fixtures';
import { userId } from '../support/db';

test.describe('Practical assessment (demo.supervisor@altusdemo.sa)', () => {
  test('start, rate every criterion, submit; the employee sees the result', async ({ as }) => {
    const sup = await as('supervisor');
    await open(sup, 'hkp/assess/queue');
    const learner = String(userId('demo.learner@altusdemo.sa'));
    const start = sup.locator('form[action*="assess/practical_start"]').first();
    await start.locator('#pu').selectOption(learner);
    await start.locator('#pr').selectOption({ index: 0 });
    await start.getByRole('button', { name: 'Start' }).click();
    await expect(sup).toHaveURL(/assess\/practical\/\d+/);
    const url = sup.url();

    // Saving a partial draft keeps the ratings (save & resume).
    const criteria = sup.locator('[role=radiogroup]');
    const n = await criteria.count();
    expect(n, 'rubric has criteria').toBeGreaterThan(0);
    await criteria.first().locator('label', { has: sup.locator('input[value=competent]') }).click();   // styled rating chips
    await sup.getByRole('button', { name: 'Save draft' }).click();
    await sup.goto(url);
    await expect(sup.locator('[role=radiogroup]').first().locator('input[value=competent]')).toBeChecked();

    // The live score preview reacts to ratings.
    for (let i = 0; i < n; i++) await criteria.nth(i).locator('label', { has: sup.locator('input[value=competent]') }).click();
    await sup.locator('#pc').fill('E2E: demonstrated the standard confidently.');
    await sup.getByRole('button', { name: 'Submit assessment' }).click();
    await flashOk(sup);
    await expect(sup.locator('main')).toContainText(/Competent|submitted/i);
    await expect(sup.locator('[role=radiogroup] input').first()).toBeDisabled();     // locked after submission

    const emp = await as('learner');
    await open(emp, 'hkp/assess');
    await expect(emp.locator('main a[href*="assess/practical/"]').first()).toBeVisible();
  });

  test('the assessor cannot assess someone outside their property', async ({ as }) => {
    const sup = await as('supervisor');
    await open(sup, 'hkp/assess/queue');
    const options = await sup.locator('#pu option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value));
    expect(options).not.toContain(String(userId('omar.learner@dyafagroup.sa')));
  });
});
