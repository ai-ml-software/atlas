import { test as base, expect, Page, Locator, Browser, BrowserContext } from '@playwright/test';
import { copyFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { Role, stateFile } from '../support/users';

/**
 * Recording helpers for the walkthrough videos.
 *
 *   const p = await studio('admin');           a page signed in as that role, recorded
 *   await say(p, 'Step title', 'What to do');  caption bar at the bottom of the video
 *   await point(p.locator('#mt_en'));          highlights the control about to be used
 *
 * The video is copied to screen-recordings/<file>.webm when the guide finishes.
 */
export const OUT = path.resolve(__dirname, '..', '..', 'screen-recordings');
const SIZE = { width: 1280, height: 800 };

type Fixtures = { studio: (role: Role | null, file: string) => Promise<Page> };

export const test = base.extend<Fixtures>({
  studio: async ({ browser }, use) => {
    const opened: { ctx: BrowserContext; page: Page; file: string }[] = [];
    await use(async (role, file) => {
      const ctx = await (browser as Browser).newContext({
        storageState: role ? stateFile(role) : undefined,
        viewport: SIZE,
        recordVideo: { dir: path.resolve(__dirname, '..', 'test-results', 'guides', 'raw'), size: SIZE },
      });
      const page = await ctx.newPage();
      page.on('dialog', (d) => d.accept());
      opened.push({ ctx, page, file });
      return page;
    });
    mkdirSync(OUT, { recursive: true });
    for (const o of opened) {
      const video = o.page.video();
      await o.ctx.close();                                   // the file is complete only after close
      if (video) copyFileSync(await video.path(), path.join(OUT, o.file));
    }
  },
});

export { expect };

/** Caption bar, drawn into the page so it is part of the recording. */
export async function say(p: Page, title: string, text = '', hold = 1800) {
  await p.evaluate(([t, x]) => {
    let bar = document.getElementById('__guide_caption');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = '__guide_caption';
      bar.setAttribute('style', [
        'position:fixed', 'inset-inline:24px', 'bottom:22px', 'z-index:2147483647', 'pointer-events:none',
        'background:rgba(30,35,41,.94)', 'color:#F7F5F1', 'border-inline-start:5px solid #C45B2F',
        'border-radius:10px', 'padding:14px 20px', 'box-shadow:0 18px 40px rgba(0,0,0,.35)',
        'font:500 17px/1.45 Inter,system-ui,sans-serif', 'direction:ltr', 'text-align:left', 'max-width:1180px',
      ].join(';'));
      document.documentElement.appendChild(bar);
    }
    bar.innerHTML = '<strong style="display:block;color:#E58A5E;font-size:13px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:4px"></strong><span></span>';
    (bar.querySelector('strong') as HTMLElement).textContent = t;
    (bar.querySelector('span') as HTMLElement).textContent = x;
  }, [title, text]);
  await p.waitForTimeout(hold);
}

/** Scrolls a control into view and rings it in copper for a moment. */
export async function point(l: Locator, hold = 700) {
  await l.scrollIntoViewIfNeeded();
  await l.evaluate((el) => {
    const r = el.getBoundingClientRect();
    const ring = document.createElement('div');
    ring.className = '__guide_ring';
    ring.setAttribute('style', `position:fixed;left:${r.left - 6}px;top:${r.top - 6}px;width:${r.width + 12}px;height:${r.height + 12}px;` +
      'border:3px solid #C45B2F;border-radius:10px;box-shadow:0 0 0 6px rgba(196,91,47,.18);z-index:2147483646;pointer-events:none;transition:opacity .4s');
    document.documentElement.appendChild(ring);
    setTimeout(() => { ring.style.opacity = '0'; setTimeout(() => ring.remove(), 450); }, 1400);
  });
  await l.page().waitForTimeout(hold);
}

/** Types visibly, so the viewer can read what is entered. */
export async function type(l: Locator, text: string) {
  await point(l, 250);
  await l.click();
  await l.fill('');
  await l.pressSequentially(text, { delay: 18 });
}

export const stamp = () => new Date().toISOString().slice(5, 16).replace(/[-:T]/g, '');
