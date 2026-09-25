import { test, expect, open } from '../support/fixtures';
import type { Page } from '@playwright/test';

/**
 * AI writing help, end to end: browser → Hkp_cms::ai → Ha_ai_assist → Ha_ai_gateway
 * → OpenAI-compatible HTTP call → mock model (support/mock-ai.mjs) → back to the page.
 */
async function panel(p: Page) {
  const ai = p.locator('[data-ai-panel]');
  await expect(ai).toBeVisible();
  await ai.locator('[data-ai-provider]').selectOption('e2e_mock');
  await expect(ai.locator('[data-ai-model] option')).not.toHaveCount(0);   // models follow the provider
  return ai;
}
const status = (ai) => ai.locator('[data-ai-status]');

test.describe('AI writing help (provider/model choice, enhance prompt, generate, insert)', () => {
  test('on a page: choose a model, enhance the prompt, suggest SEO meta and insert it', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/page/1');
    const ai = await panel(p);
    await ai.locator('[data-ai-model]').selectOption('mock-fast');

    await ai.locator('[data-ai-prompt]').fill('pre opening help riyadh hotels');
    await ai.getByRole('button', { name: 'Enhance prompt' }).click();
    await expect(ai.locator('[data-ai-prompt]')).toHaveValue(/^BRIEF: /);          // the brief replaces the rough request
    await expect(status(ai)).toContainText('e2e_mock / mock-fast');

    await ai.locator('[data-ai-task]').selectOption('seo_meta');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/Pre-opening hotel support in Riyadh/);
    await ai.getByRole('button', { name: 'Insert into the field' }).click();
    await expect(p.locator('#mden')).toHaveValue(/opening day/);                    // description → description
    await expect(p.locator('#mten')).toHaveValue('Pre-opening hotel support in Riyadh | Altus');   // title → title
  });

  test('write a page section and an FAQ (structured JSON output)', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/page/1');
    const ai = await panel(p);
    await ai.locator('[data-ai-prompt]').fill('A section about pre-opening support');
    await ai.locator('[data-ai-task]').selectOption('section');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/Pre-opening support in Riyadh/);   // fenced JSON parsed
    await ai.locator('[data-ai-task]').selectOption('faq');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/What is pre-opening support\? \| /);  // ready for the FAQ field
  });

  test('translate to Arabic', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/page/1');
    const ai = await panel(p);
    await ai.locator('[data-ai-context]').fill('Welcome to the hotel');
    await ai.locator('[data-ai-task]').selectOption('translate_ar');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/مرحبا/);
  });

  test('in a lesson: write a lesson and insert it into the lesson body', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/modules');
    await p.locator('main a[href*="hkp/cms/module/"]').first().click();
    await p.getByRole('link', { name: 'Add lesson' }).click();
    const ai = await panel(p);
    await ai.locator('[data-ai-prompt]').fill('Welcoming guests at the front desk');
    await ai.locator('[data-ai-task]').selectOption('lesson');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/<h2>Welcoming the guest<\/h2>/);
    await ai.getByRole('button', { name: 'Insert into the field' }).click();
    await expect(p.locator('#lb_en')).toHaveValue(/Greet every guest within ten seconds/);
  });

  test('an empty request is refused with a clear message', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/page/1');
    const ai = await panel(p);
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(status(ai)).toContainText(/Write a request/);
  });

  test('every AI call is logged with the model used', async ({ as }) => {
    const p = await as('admin');
    const r = await p.request.get('hkp/admin/ai');
    expect(r.status()).toBe(200);
    const { one } = await import('../support/db');
    // (created_at is written in the app's PHP timezone, so match on content, not on MySQL NOW())
    const row = one(`SELECT CONCAT(model,'|',ok,'|',LEFT(enhanced_prompt,6)) FROM ha_ai_prompt_log
                     WHERE provider='e2e_mock' AND context='enhance_prompt' ORDER BY id DESC LIMIT 1`);
    expect(row).toBe('mock-fast|1|BRIEF:');                   // model, success and the enhanced brief are recorded
    expect(Number(one(`SELECT COUNT(*) FROM ha_ai_prompt_log WHERE provider='e2e_mock' AND context='seo_meta' AND ok=1`))).toBeGreaterThan(0);
  });

  test('a learner cannot call the editor AI endpoint', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp');
    const r = await p.evaluate(async () => {
      const body = new URLSearchParams({ task: 'section', prompt: 'x', ha_csrf: (window as any).HKP?.csrf || '' });
      const res = await fetch(location.origin + '/atlas/atlas/hkp/cms/ai', { method: 'POST', body, credentials: 'same-origin' });
      return { status: res.status, text: await res.text() };
    });
    expect(r.text).not.toContain('"ok":true');
  });
});
