const {request, chromium} = require('@playwright/test');
(async () => {
  const c = await request.newContext({storageState: '.auth/isolated-admin.json', baseURL: 'http://127.0.0.1:8099'});
  for (const u of ['/hkp/cms/studio', '/hkp/cms/catalogue/articles/new', '/hkp/cms/publisher']) {
    const r = await c.get(u); console.log(u, r.status(), (await r.text()).slice(0, 1500));
  }
  await c.dispose();
  const b = await chromium.launch({channel:'msedge'});
  const p = await b.newPage({storageState: '.auth/isolated-admin.json'});
  p.on('pageerror', e=>console.log('JS ERROR',e.message));
  p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto('http://127.0.0.1:8099/hkp/cms/publisher/4');
  console.log('json', await p.locator('#publisher-models').textContent());
  await p.locator('#publisher-provider').selectOption('e2e_mock');
  console.log('options', await p.locator('#publisher-model').innerHTML());
  await b.close();
})();
