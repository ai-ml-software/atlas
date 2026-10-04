(function () {
  'use strict';
  var panel = document.querySelector('[data-mobile-connection]');
  if (!panel) return;
  var button = panel.querySelector('[data-mobile-test]');
  var status = panel.querySelector('[data-mobile-test-status]');
  var ar = document.documentElement.dir === 'rtl';
  button.addEventListener('click', async function () {
    var key = panel.querySelector('[data-mobile-test-key]').value.trim();
    if (!/^altm_[a-f0-9]{12}_[A-Za-z0-9]{40}$/.test(key)) {
      status.textContent = ar ? 'أدخل مفتاح التطبيق altm_ الصحيح.' : 'Enter a valid altm_ app key.';
      return;
    }
    button.disabled = true;
    status.textContent = ar ? 'جارٍ اختبار الاتصال…' : 'Testing connection…';
    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 10000);
    try {
      var response = await fetch(panel.dataset.endpoint, { headers: { Accept: 'application/json', 'X-App-Key': key }, cache: 'no-store', signal: controller.signal });
      var body = await response.json();
      status.textContent = response.ok && body.success
        ? (ar ? 'تم الاتصال بنجاح. إصدار الإعدادات ' : 'Connection successful. Config version ') + body.data.version
        : (ar ? 'فشل الاتصال: ' : 'Connection failed: ') + (body.message || response.status);
    } catch (error) {
      status.textContent = ar ? 'تعذر الوصول إلى المنصة. تحقق من الخادم وعنوان URL.' : 'Could not reach the platform. Check the server and URL.';
    } finally { clearTimeout(timer); button.disabled = false; }
  });
}());
