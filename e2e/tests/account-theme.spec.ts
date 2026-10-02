import { test, expect, Page } from '@playwright/test';
import { sql, one, userId } from '../support/db';
import { USERS } from '../support/users';

test.describe('Shared public and account layout', () => {
  test.skip(!process.env.HKP_TEST_DATABASE, 'Use the isolated config; account fixtures must not touch learner data.');
  const publicPages = ['en', 'en/about', 'en/about-altus', 'en/services', 'en/knowledge-performance',
    'en/ascent', 'en/market', 'en/case-studies', 'en/leadership', 'en/profile', 'en/courses',
    'en/programs', 'en/learning-paths', 'en/hospitality-topics', 'en/sop', 'en/articles',
    'en/certificates', 'en/verify', 'en/contact', 'en/hotels', 'en/privacy', 'en/terms', 'en/credits', 'en/search?q=hotel'];

  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('accept_cookie_academy', 'true'));
  });

  async function shell(page: Page) {
    await expect(page.locator('[data-ha-chrome]')).toHaveCount(1);
    await expect(page.locator('.ha-foot')).toHaveCount(1);
    await expect(page.locator('main#ha-main')).toHaveCount(1);
    const overflow = await page.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,
      outside:[...document.querySelectorAll('main *')].filter(e => e.getBoundingClientRect().right > innerWidth+2).slice(0,12).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width}))}));
    expect(overflow.scroll <= overflow.width+2, `${page.url()} ${JSON.stringify(overflow)}`).toBe(true);
    await expect(page.locator('.ha-mast__brand')).toHaveAttribute('href', /\/(en|ar)$/);
  }

  async function styles(page: Page) {
    return page.evaluate(() => ['.ha-mast', '.ha-mast__logo', '.ha-mast__cta', '.ha-foot', '.ha-foot__logo', '.ha-foot__nav a'].map(selector => {
      const e = document.querySelector(selector)!; const s = getComputedStyle(e);
      return [s.fontFamily, s.fontSize, s.color, s.backgroundColor, s.borderRadius, s.padding,
        selector.endsWith('__logo') ? s.height : '', selector.endsWith('__logo') ? s.maxWidth : ''];
    }));
  }

  for (const locale of ['en', 'ar']) for (const width of [320, 390, 768, 1440]) {
    test(`${locale} account pages match public chrome at ${width}px`, async ({ page }) => {
      await page.setViewportSize({width, height:900});
      await page.goto(`${locale}/about`);
      const baseline = await styles(page);
      for (const path of ['login', 'sign_up', 'login/forgot_password_request', 'home/forgot_password']) {
        const errors: string[] = [];
        const bad: string[] = [];
        const onError = e => errors.push(e.message);
        const onResponse = r => { if (r.status() >= 400) bad.push(r.url()); };
        page.on('pageerror', onError); page.on('response', onResponse);
        const response = await page.goto(`${path}?lang=${locale}`);
        expect(response!.status()).toBe(200);
        await shell(page);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('h1')).toHaveText(locale === 'ar'
          ? path === 'login' ? 'تسجيل الدخول' : path === 'sign_up' ? 'أنشئ حسابك' : 'إعادة تعيين كلمة المرور'
          : path === 'login' ? 'Log in' : path === 'sign_up' ? 'Create your account' : 'Reset your password');
        expect(await styles(page)).toEqual(baseline);
        await expect(page.locator('input[type=email]')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('meta[name=robots]')).toHaveAttribute('content', 'noindex, nofollow');
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(100);
        expect(errors).toEqual([]); expect(bad).toEqual([]);
        page.off('pageerror', onError); page.off('response', onResponse);
      }
      await page.screenshot({path:`test-results-isolated/account-${locale}-${width}.png`,fullPage:true});
    });
  }

  test('all public page families and legacy information pages use one shell', async ({ page }) => {
    test.setTimeout(120_000);
    for (const path of [...publicPages, 'home/about_us', 'home/terms_and_condition',
      'home/privacy_policy', 'home/refund_policy', 'home/cookie_policy', 'home/shopping_cart', 'courses']) {
      const response = await page.goto(path);
      await page.waitForLoadState('domcontentloaded');
      expect(response!.status(), path).toBe(200);
      await shell(page);
    }
  });

  test('signed-in legacy learner pages retain one shared header and footer', async ({ page }) => {
    test.setTimeout(120_000);
    sql(`UPDATE users SET sessions='[]' WHERE email='${USERS.student.email}'`);
    await page.goto('login?lang=en');
    await page.locator('#email').fill(USERS.student.email);
    await page.locator('#password').fill(USERS.student.password);
    await page.locator('#login-form button[type=submit]').click();
    await expect(page).toHaveURL(/\/hkp/);
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:900});
      await page.goto('en/about');
      const baseline = await styles(page);
      for (const path of ['home/my_courses', 'home/my_messages', 'home/my_notifications', 'home/my_wishlist',
        'home/purchase_history', 'home/profile', 'home/profile/user_credentials',
        'home/profile/user_photo', 'home/instructor_following']) {
        const response = await page.goto(`${path}?lang=en`);
        expect(response!.status(),path).toBe(200);
        await shell(page);
        expect(await styles(page),path).toEqual(baseline);
        await expect(page.locator('[data-hkp-menu]')).toHaveCount(1);
      }
    }
  });

  test('account actions keep readable text contrast and visible keyboard focus', async ({ page }) => {
    await page.goto('login?lang=en');
    for (const selector of ['.ha-auth__intro .ha-btn','.ha-auth__submit','[data-password-toggle]','.ha-auth__switch a']) {
      const target = page.locator(selector);
      const ratio = await target.evaluate(element => {
        function rgb(value: string) { return (value.match(/[\d.]+/g) || []).map(Number); }
        function luminance(c: number[]) { const v=c.slice(0,3).map(x=>{x/=255;return x<=.04045 ? x/12.92 : ((x+.055)/1.055)**2.4;});return v[0]*.2126+v[1]*.7152+v[2]*.0722; }
        let current: Element | null = element; let background: number[] = [255,255,255];
        while(current) { const color=rgb(getComputedStyle(current).backgroundColor);if(color.length===3 || color[3]===1){background=color;break;}current=current.parentElement; }
        const a=luminance(rgb(getComputedStyle(element).color));const b=luminance(background);
        return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
      });
      expect(ratio,selector).toBeGreaterThanOrEqual(4.5);
      await target.focus();
      expect(await target.evaluate(e=>getComputedStyle(e).outlineStyle)).not.toBe('none');
    }
  });

  test('password controls, required fields, keyboard focus and failed login are accessible', async ({ page }) => {
    await page.goto('login?lang=en');
    await page.keyboard.press('Tab');
    await expect(page.locator('.ha-skip')).toBeFocused();
    await page.keyboard.press('Enter');
    expect(await page.evaluate(() => location.hash)).toBe('#ha-main');
    const submit = page.locator('#login-form button[type=submit]');
    await submit.click();
    await expect(page).toHaveURL(/login\?lang=en/);
    await expect(page.getByLabel('Email address', {exact:true})).toBeFocused();
    await page.getByLabel('Email address', {exact:true}).fill(USERS.student.email);
    await page.locator('#password').fill('wrong-password');
    const reveal = page.getByRole('button', {name:'Show password',exact:true});
    await reveal.focus(); await page.keyboard.press('Enter');
    await expect(page.locator('#password')).toHaveAttribute('type','text');
    await expect(page.getByRole('button',{name:'Hide password'})).toHaveAttribute('aria-pressed','true');
    await page.getByRole('button',{name:'Hide password'}).click();
    await submit.click();
    await expect(page.locator('[data-auth-error]')).toBeVisible();
    await expect(page.locator('[data-auth-error]')).toBeFocused();
    await expect(page.locator('#email')).toHaveValue(USERS.student.email);
    await expect(page.locator('#password')).toHaveValue('');
  });

  test('Arabic links keep the language and instructor fields only validate when enabled', async ({ page }) => {
    await page.goto('login?lang=ar');
    await page.getByRole('link',{name:'نسيت كلمة المرور؟',exact:true}).click();
    await expect(page.locator('html')).toHaveAttribute('lang','ar');
    await expect(page.locator('.ha-auth__form')).toHaveAttribute('action', /lang=ar/);
    await page.goto('sign_up?lang=ar');
    await expect(page.locator('#become-instructor-fields')).toBeHidden();
    await expect(page.locator('#phone')).toBeDisabled();
    await page.locator('#instructor').check();
    await expect(page.locator('#become-instructor-fields')).toBeVisible();
    await expect(page.locator('#phone')).toBeEnabled();
    await expect(page.locator('#document')).toHaveAttribute('required','');
    await page.locator('#instructor').uncheck();
    await expect(page.locator('#phone')).toBeDisabled();
    await page.locator('[data-back-login], .ha-auth__switch a').click();
    await expect(page.locator('html')).toHaveAttribute('lang','ar');
  });

  // Test-only database session setup exercises guarded screens without sending email.
  async function pending(page: Page, data: Record<string, string | number>) {
    await page.goto('login?lang=en');
    const sid = (await page.context().cookies()).find(c => c.name === 'ci_session')!.value;
    const serialized = Object.entries(data).map(([key,value]) => `${key}|${typeof value === 'number'
      ? `i:${value};` : `s:${Buffer.byteLength(value)}:"${value}";`}`).join('');
    sql(`UPDATE ci_sessions SET data=CONCAT(data,UNHEX('${Buffer.from(serialized).toString('hex')}')) WHERE id='${sid}'`);
  }

  for (const locale of ['en','ar']) {
    test(`${locale} guarded verification screens use the same layout and safe labels`, async ({ page }) => {
      await page.setViewportSize({width:390,height:844});
      const uid = userId(USERS.student.email);
      await pending(page, {ha_2fa_user_id:uid,ha_2fa_expires:Math.floor(Date.now()/1000)+300,
        new_device_user_id:uid,new_device_user_email:USERS.student.email,
        new_device_code_expiration_time:Math.floor(Date.now()/1000)+300,
        new_device_verification_code:'123456',register_email:'e2e.newlearner@example.invalid'});
      for (const width of [1440,390]) {
        await page.setViewportSize({width,height:900});
        for (const [path,screen,field] of [
          ['login/two_factor','two_factor','ha-2fa-code'],
          ['login/new_login_confirmation','new_login_confirmation','new_device_verification_code'],
          ['sign_up/verification_code','verification_code','verification_code']]) {
          await page.goto(`${path}?lang=${locale}`); await shell(page);
          await expect(page.locator('[data-account-screen]')).toHaveAttribute('data-account-screen',screen);
          await expect(page.locator(`#${field}`)).toHaveAttribute('autocomplete','one-time-code');
          await expect(page.locator(`label[for="${field}"]`)).toBeVisible();
          await expect(page.locator('h1')).toHaveCount(1);
          await expect(page.locator('html')).toHaveAttribute('lang',locale);
          await page.screenshot({path:`../backups/account-${screen}-${locale}-${width}.png`,fullPage:true});
        }
      }
      // No mail request leaves the browser; test the visible async failure states.
      await page.route('**/login/resend_verification_code**', route => route.fulfill({status:503,body:'Unavailable'}));
      await page.locator('[data-auth-resend]').click();
      await expect(page.locator('[data-auth-feedback]')).toHaveText(locale==='ar'
        ? 'تعذّر طلب الرمز. يُرجى المحاولة مرة أخرى.' : 'Unable to request a code. Please try again.');
      await expect(page.locator('[data-auth-resend]')).toBeEnabled();
      await page.unroute('**/login/resend_verification_code**');
      await page.route('**/login/resend_verification_code**', route => route.fulfill({status:200,body:'1'}));
      await page.locator('[data-auth-resend]').click();
      await expect(page.locator('[data-auth-feedback]')).toHaveText(locale==='ar'
        ? 'تم طلب رمز تحقق جديد.' : 'A new verification code has been requested.');
      await page.route('**/login/verify_email_address**', route => route.fulfill({status:503,body:'Unavailable'}));
      await page.locator('#verification_code').fill('123456');
      await page.locator('form button[type=submit]').click();
      await expect(page.locator('[data-auth-feedback]')).toHaveText(locale==='ar'
        ? 'تعذّر طلب الرمز. يُرجى المحاولة مرة أخرى.' : 'Unable to request a code. Please try again.');
    });
  }

  test('device confirmation rejects an incorrect code, then signs in without changing progress', async ({ page }) => {
    const uid = userId(USERS.student.email);
    sql(`UPDATE users SET sessions='[]' WHERE id=${uid}`);
    const before = sql(`SELECT id,course_id,progress_percentage,lessons_completed,time_spent_seconds FROM ha_enrollment WHERE user_id=${uid} ORDER BY id`);
    await pending(page, {new_device_user_id:uid,new_device_user_email:USERS.student.email,
      new_device_code_expiration_time:Math.floor(Date.now()/1000)+300,new_device_verification_code:'123456'});
    await page.goto('login/new_login_confirmation?lang=en');
    await page.locator('#new_device_verification_code').fill('incorrect');
    await page.locator('.ha-auth__form button[type=submit]').click();
    await expect(page.locator('[data-auth-error]')).toBeVisible();
    await expect(page).toHaveURL(/new_login_confirmation$/);
    await page.locator('#new_device_verification_code').fill('123456');
    await page.locator('.ha-auth__form button[type=submit]').click();
    await expect(page).toHaveURL(/\/hkp\/?$/);
    expect(sql(`SELECT id,course_id,progress_percentage,lessons_completed,time_spent_seconds FROM ha_enrollment WHERE user_id=${uid} ORDER BY id`)).toEqual(before);
  });

  test('new password screen validates matching values and completes a reset', async ({ page }) => {
    const uid = userId('e2e.newlearner@example.invalid');
    const token = Buffer.from('e2e.newlearner__example.invalid--theme-fixture').toString('base64').replace(/=/g,'');
    sql(`UPDATE users SET verification_code='${token}',last_modified=UNIX_TIMESTAMP() WHERE id=${uid}`);
    try {
      for (const locale of ['ar','en']) for (const width of [390,1440]) {
        await page.setViewportSize({width,height:900});
        await page.goto(`login/change_password/${token}?lang=${locale}`); await shell(page);
        await expect(page.locator('[data-account-screen]')).toHaveAttribute('data-account-screen','change_password_from_forgot_password');
        await expect(page.locator('h1')).toHaveText(locale==='ar' ? 'اختر كلمة مرور جديدة' : 'Choose a new password');
        await expect(page.locator('html')).toHaveAttribute('dir',locale==='ar' ? 'rtl' : 'ltr');
        await page.screenshot({path:`../backups/account-password-reset-${locale}-${width}.png`,fullPage:true});
      }
      await page.locator('#new_password').fill('Theme#2026'); await page.locator('#confirm_password').fill('different');
      await page.locator('form button[type=submit]').click();
      expect(await page.locator('#confirm_password').evaluate((e: HTMLInputElement) => e.validationMessage)).toBe('Passwords must match.');
      await page.locator('#confirm_password').fill('Theme#2026');
      await page.locator('form button[type=submit]').click();
      await expect(page).toHaveURL(/\/login$/);
      await expect(page.locator('[role=status].ha-auth__notice')).toBeVisible();
      expect(one(`SELECT password=SHA1('Theme#2026') FROM users WHERE id=${uid}`)).toBe('1');
    } finally { sql(`UPDATE users SET password=SHA1('Academy#2026'),verification_code='' WHERE id=${uid}`); }
  });

  test('expired challenges return to the styled login; cookie acceptance works with a keyboard', async ({ page }) => {
    await pending(page,{ha_2fa_user_id:1,ha_2fa_expires:1,new_device_code_expiration_time:1});
    for (const path of ['login/two_factor','login/new_login_confirmation']) {
      await page.goto(path); await expect(page).toHaveURL(/\/login$/); await shell(page);
      await expect(page.locator('[data-auth-error]')).toBeVisible();
    }
    const fresh = await page.context().browser()!.newContext();
    try {
      const guest = await fresh.newPage();
      await guest.goto(new URL('login?lang=en', page.url()).href);
      await expect(guest.locator('[data-site-cookie]')).toBeVisible();
      await guest.locator('[data-cookie-accept]').focus(); await guest.keyboard.press('Enter');
      await expect(guest.locator('[data-site-cookie]')).toBeHidden();
      expect(await guest.evaluate(() => localStorage.getItem('accept_cookie_academy'))).toBe('true');
      await guest.goto(new URL('en/about', page.url()).href);
      await expect(guest.locator('[data-site-cookie]')).toBeHidden();
    } finally { await fresh.close(); }
  });
});
