'use strict';
(() => {
  let token = new URLSearchParams(location.hash.slice(1)).get('token') || '';
  history.replaceState(null, '', location.pathname);
  document.getElementById('confirmation-brand').innerHTML = UI.brand();
  const message = document.getElementById('confirmation-message');
  const button = document.getElementById('confirm-email-button');
  if (!/^[a-f0-9]{64}$/.test(token)) { message.textContent = 'Link de confirmação inválido. Entre no portal para solicitar um novo.'; return; }
  API.request('/public').then(() => { button.disabled = false; }).catch(() => { message.textContent = 'Não foi possível abrir a confirmação. Abra novamente o link recebido por e-mail.'; });
  document.getElementById('confirm-email-form').addEventListener('submit', async event => {
    event.preventDefault(); button.disabled = true;
    try {
      const result = await API.request('/auth/confirm-email', 'POST', { token });
      token = ''; message.textContent = result.message; event.target.hidden = true;
    } catch (error) { message.textContent = error.message; button.disabled = false; }
  });
})();
