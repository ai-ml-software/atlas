/* Native account controls shared by every authentication screen. */
(function () {
  'use strict';
  const form = document.querySelector('.ha-auth__form');
  if (!form) return;
  const feedback = document.querySelector('[data-auth-feedback]');
  function announce(message) { feedback.textContent = message; feedback.hidden = false; }
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.textContent = visible ? button.dataset.hide : button.dataset.show;
  }));
  const error = document.querySelector('[data-auth-error]');
  if (error) error.focus();
  const instructor = document.querySelector('[data-instructor-toggle]');
  if (instructor) instructor.addEventListener('change', () => {
    const fields = document.getElementById(instructor.getAttribute('aria-controls'));
    fields.hidden = fields.disabled = !instructor.checked;
  });
  const confirm = document.querySelector('[data-password-confirm]');
  if (confirm) {
    const password = document.getElementById('new_password');
    const validate = () => confirm.setCustomValidity(confirm.value && confirm.value !== password.value ? confirm.dataset.matchMessage : '');
    password.addEventListener('input', validate); confirm.addEventListener('input', validate);
  }
  document.querySelectorAll('[data-auth-resend]').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const data = new FormData(form);
      data.set('email', button.dataset.email);
      const response = await fetch(button.dataset.authResend, {method:'POST',body:data});
      if (!response.ok) throw new Error('Request failed');
      announce(button.dataset.success);
    } catch (_) { announce(button.dataset.failure); }
    finally { button.disabled = false; }
  }));
  async function verifyEmail() {
    try {
      const response = await fetch(form.action, {method:'POST',body:new FormData(form)});
      if (!response.ok) throw new Error('Request failed');
      if ((await response.text()).trim() === '1') location.assign(document.querySelector('[data-back-login]').href);
      else location.reload();
    } catch (_) { announce(form.dataset.failure); }
  }
  if (form.hasAttribute('data-auth-verify-email')) form.addEventListener('submit', event => { event.preventDefault(); verifyEmail(); });
  window.onAccountSubmit = token => {
    if (!form.reportValidity()) return;
    let field = form.querySelector('[name="g-recaptcha-response"]');
    if (!field) { field = document.createElement('input'); field.type='hidden'; field.name='g-recaptcha-response'; form.append(field); }
    field.value = token;
    if (form.hasAttribute('data-auth-verify-email')) verifyEmail(); else form.submit();
  };
})();
