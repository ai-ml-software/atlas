import {test,expect,open} from '../support/fixtures';

test('integrations tabs support keyboard navigation, mobile layout and Arabic RTL',async({as})=>{
  test.setTimeout(120000);
  const p=await as('admin');
  for(const locale of ['en','ar']) {
    await p.setViewportSize({width:390,height:844});
    for(const tab of ['approvals','connections','health','audit','archive']) {
      await open(p,`hkp/cms/integrations?tab=${tab}&lang=${locale}`);
      await expect(p.locator('html')).toHaveAttribute('dir',locale==='ar'?'rtl':'ltr');
      await expect(p.locator('.mcp-tabs [aria-current=page]')).toHaveAttribute('href',new RegExp('tab='+tab));
      await expect(p.locator('.hkp-nav a[aria-current=page]')).toHaveAttribute('href',/cms\/integrations$/);
      expect(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
      await expect(p.locator('body')).not.toContainText('A PHP Error was encountered');
    }
  }
  await p.setViewportSize({width:1440,height:900});await open(p,'hkp/cms/integrations?tab=connections&lang=en');
  const health=p.locator('.mcp-tabs a[href$="tab=health"]');await health.focus();await p.keyboard.press('Enter');await expect(p).toHaveURL(/tab=health/);
  await p.getByRole('button',{name:'Copy MCP URL'}).click();await expect(p.locator('#mcp-copy-status')).toHaveText('MCP URL copied.');
  await p.screenshot({path:'../docs/screenshots/mcp-integrations.png',fullPage:true});
});

test('learners cannot access integration administration',async({as})=>{
  const p=await as('learner');for(const tab of ['approvals','connections','health','audit','archive']) expect((await p.request.get('hkp/cms/integrations?tab='+tab)).status()).toBe(403);
});
