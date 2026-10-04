import {test,expect,open,flashOk,stamp} from '../support/fixtures';
import {one,keepPage,preserve} from '../support/db';

test('actual preview clicks focus fields and section identities survive publication',async({as,page})=>{
  const p=await as('admin'),id=Number(one("SELECT id FROM ha_page WHERE code='contact'")!),title='Private click-to-edit '+stamp();
  const restore=keepPage(id);   // this spec publishes to the shared contact page; put it back afterwards
  try {
  await open(p,`hkp/cms/live/${id}`);const t=p.frameLocator('#live-preview').locator('[data-studio-field=title]');await t.click();await expect(t).toHaveAttribute('contenteditable','true');await expect(t).toBeFocused();
  await p.locator('[data-field=title]').first().fill(title);await expect(p.locator('#live-status')).toContainText('Draft saved');
  await open(page,'en/contact');await expect(page.locator('h1')).not.toContainText(title);
  await p.locator('#live-add-type').selectOption('rich_text');await p.getByRole('button',{name:'Add section',exact:true}).click();
  const card=p.locator('#live-fields details').last();await card.locator('[data-field$=":heading"]').fill('Click this section');await card.locator('[data-field$=":body"]').fill('<p>Source-supported text.</p>');await expect(p.locator('#live-status')).toContainText('Draft saved');
  await p.frameLocator('#live-preview').locator('[data-studio-section]').last().click();await expect(card).toHaveAttribute('open','');
  await p.getByRole('button',{name:'Publish',exact:true}).click();await expect(p.locator('#live-status')).toContainText('Published');
  const sectionId=one(`SELECT id FROM ha_page_section WHERE page_id=${id} ORDER BY id DESC LIMIT 1`);await p.getByRole('button',{name:'Save draft',exact:true}).click();await expect(p.locator('#live-status')).toContainText('Draft saved');await p.getByRole('button',{name:'Publish',exact:true}).click();await expect(p.locator('#live-status')).toContainText('Published');expect(one(`SELECT id FROM ha_page_section WHERE page_id=${id} ORDER BY id DESC LIMIT 1`)).toBe(sectionId);
  await open(page,'en/contact');await expect(page.locator('h1')).toContainText(title);
  } finally { restore(); }
});

test('theme drafts preview privately and require explicit publication',async({as,page})=>{
  const p=await as('admin');await open(p,'hkp/cms/theme');await p.locator('[name="theme[accent]"]').fill('#123456');await p.getByRole('button',{name:'Save private draft',exact:true}).click();await flashOk(p);
  const guest=await page.request.get('publisher_theme/css');expect(await guest.text()).not.toContain('#123456');
  const preview=await p.request.get('publisher_theme/css?preview=1');expect(await preview.text()).toContain('#123456');
  await p.getByRole('button',{name:'Publish saved draft',exact:true}).click();await flashOk(p);expect(await (await page.request.get('publisher_theme/css')).text()).toContain('#123456');
  await p.locator('[name="theme[accent]"]').fill('#a84d27');await p.getByRole('button',{name:'Save private draft',exact:true}).click();await flashOk(p);await p.getByRole('button',{name:'Publish saved draft',exact:true}).click();await flashOk(p);
});

test('published article live editing binds native content without exposing its draft',async({as,page})=>{
  const p=await as('admin'),id=Number(one("SELECT id FROM ha_article WHERE status='published' LIMIT 1")!),old=one(`SELECT title FROM ha_article_translation WHERE article_id=${id} AND locale='en'`),slug=one(`SELECT slug_en FROM ha_article WHERE id=${id}`)!;
  const restore=preserve([['ha_article_translation',`article_id=${id}`]]);   // published to the shared article; put it back afterwards
  try {
  await open(p,`hkp/cms/catalogue_live/articles/${id}`);const t=p.frameLocator('#entity-preview').locator('[data-studio-field=title]');await t.click();await expect(t).toHaveAttribute('contenteditable','true');await expect(t).toBeFocused();
  await p.locator('#entity-fields [data-field=title]').fill('Private article live headline');await p.getByRole('button',{name:'Save draft',exact:true}).click();await expect(p.locator('#entity-status')).toContainText('Private draft saved');await expect(p.frameLocator('#entity-preview').locator('h1')).toHaveText('Private article live headline');
  await open(page,'en/articles/'+slug);await expect(page.locator('h1')).toHaveText(old!);
  await p.getByRole('button',{name:'Publish saved draft',exact:true}).click();await expect(p.locator('#entity-status')).toHaveText('Published');await page.reload();await expect(page.locator('h1')).toHaveText('Private article live headline');
  } finally { restore(); }
});
