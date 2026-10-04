import { test, expect, open, flashOk } from '../support/fixtures';
import { one, workerEnv } from '../support/db';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const PHP = 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe';
const SOURCE = '[Page 1]\nAlways wear PPE before cleaning hotel bathrooms. Follow the approved procedure.\n\n[Page 2]\nReport every chemical spill to the duty manager and record it in the hazard log.';
const builders: Record<string, { url: RegExp; table: string }> = {
  page: { url: /cms\/page\/(\d+)/, table: 'ha_page' },
  article: { url: /cms\/catalogue\/articles\/(\d+)/, table: 'ha_article' },
  topic: { url: /cms\/catalogue\/topics\/(\d+)/, table: 'ha_topic' },
};

for (const target of Object.keys(builders)) {
  test(`pasted source becomes an unpublished ${target} with cited source pages`, async ({ as }) => {
    const p = await as('admin'); await open(p, 'hkp/cms/publisher');
    await expect(p.locator('#publisher-health')).toContainText('Extraction worker');
    await expect(p.locator('#publisher-health')).toContainText('publisher_cli daemon');
    await p.locator('#publisher-source').fill(SOURCE); await p.locator('#publisher-target').selectOption(target);
    await p.getByRole('button', { name: 'Analyse document' }).click(); await flashOk(p);
    await p.locator('#publisher-provider').selectOption('e2e_mock'); await p.locator('#publisher-model').selectOption('mock-writer');
    await p.getByRole('button', { name: 'Generate draft', exact: true }).click();
    await expect(p.locator('#publisher-status')).toContainText('Draft saved', { timeout: 30_000 });
    await expect(p.locator('#publisher-visual')).toContainText('Preparation');
    // Cite pages on the generated section, then save.
    const pages = p.locator('.studio-source-pages input').first(); await p.locator('#publisher-visual details summary').first().click();
    await pages.fill('1, 2'); await p.getByRole('button', { name: 'Save draft' }).click(); await expect(p.locator('#publisher-status')).toContainText('Draft saved');
    const draft = Number(p.url().match(/publisher\/(\d+)/)![1]);
    expect(JSON.parse(one(`SELECT payload_json FROM ha_publisher_draft WHERE id=${draft}`)!).sections[0].source_pages).toEqual([1, 2]);
    // Featured image from the media library, when the library has images.
    const media = one(`SELECT id FROM ha_media WHERE disk='public' AND mime_type IN ('image/jpeg','image/png','image/webp') ORDER BY id DESC LIMIT 1`);
    if (media) {
      await p.locator('#publisher-media-choose').click(); await p.locator(`[data-media-id="${media}"]`).click();
      await expect(p.locator('#publisher-media [data-media-current] img')).toBeVisible();
      expect(one(`SELECT media_id FROM ha_publisher_draft WHERE id=${draft}`)).toBe(String(media));
    }
    await p.getByRole('button', { name: 'Create content as draft' }).click(); await expect(p).toHaveURL(builders[target].url);
    const entity = Number(p.url().match(builders[target].url)![1]);
    expect(one(`SELECT status FROM ${builders[target].table} WHERE id=${entity}`)).toBe('draft');
    expect(one(`SELECT status FROM ha_publisher_draft WHERE id=${draft}`)).toBe('imported');
    if (media && target === 'article') expect(one(`SELECT cover_image FROM ha_article WHERE id=${entity}`)).toBe(one(`SELECT file_path FROM ha_media WHERE id=${media}`));
  });
}

test('uploaded PDF shows per-page provenance beside the source', async ({ as }) => {
  const p = await as('admin'); await open(p, 'hkp/cms/publisher');
  const buffer = readFileSync(path.resolve('..', 'application', 'tests', 'fixtures', 'publisher', 'text.pdf'));
  await p.locator('#publisher-file').setInputFiles({ name: 'two-pages.pdf', mimeType: 'application/pdf', buffer });
  await p.locator('#publisher-target').selectOption('article'); await p.getByRole('button', { name: 'Analyse document' }).click(); await flashOk(p);
  await expect(p.locator('#document-progress')).toBeVisible();
  execFileSync(PHP, ['index.php', 'publisher_cli', 'work', '10'], { cwd: path.resolve('..'), env: workerEnv() });
  await p.reload();
  await expect(p.locator('#publisher-provenance tbody tr')).toHaveCount(2);
  await expect(p.locator('#publisher-provenance tr[data-page="1"]')).toHaveAttribute('data-method', 'text');
  await expect(p.locator('#publisher-source-correction')).toHaveValue(/\[Page 2\]\nReport every chemical spill/);
  await expect(p.locator('#publisher-worker-status')).toHaveAttribute('data-alive', '1');
});
