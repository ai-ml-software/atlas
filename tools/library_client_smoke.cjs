/* Independent DOM fixture: no site setup, database writes or sign-in. */
const {chromium} = require('../e2e/node_modules/@playwright/test');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  try {
    const page = await browser.newPage();
    await page.setContent('<p id="static">Save changes</p><input placeholder="Search"><input type="submit" value="Save changes"><textarea>Save changes</textarea><code>Save changes</code><p translate="no">Save changes</p><script type="application/json" id="ha-reviewed-client-text">{"Save changes":"حفظ التغييرات","Search":"بحث"}</script>');
    await page.addScriptTag({path:require('node:path').resolve('assets/academy/reviewed-translations.js')});
    assert.equal(await page.locator('#static').textContent(),'حفظ التغييرات');
    assert.equal(await page.locator('input[placeholder]').getAttribute('placeholder'),'بحث');
    assert.equal(await page.locator('input[type="submit"]').inputValue(),'حفظ التغييرات');
    for (const selector of ['textarea','code','[translate="no"]']) assert.equal(await page.locator(selector).textContent(),'Save changes');
    await page.evaluate(()=>{
      const p=document.createElement('p'); p.id='dynamic'; p.textContent='Save changes'; document.body.append(p);
      const untouched=document.createElement('p'); untouched.id='untouched'; untouched.textContent='Unreviewed string'; document.body.append(untouched);
    });
    await page.waitForFunction(()=>document.getElementById('dynamic').textContent==='حفظ التغييرات');
    assert.equal(await page.locator('#untouched').textContent(),'Unreviewed string');
    const dialog = new Promise(resolve=>page.once('dialog',async d=>{const message=d.message(); await d.accept(); resolve(message);}));
    await page.evaluate(()=>alert('Save changes'));
    assert.equal(await dialog,'حفظ التغييرات');
    console.log('Passed reviewed static, dynamic, attribute, dialog and protected-text checks');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
