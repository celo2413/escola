'use strict';
const EmailValidation = (() => {
  const message = 'Digite um endereço de e-mail válido.';
  function valid(value) {
    if (value.length > 190) return false;
    const pieces = value.split('@');
    if (pieces.length !== 2) return false;
    const [local, domain] = pieces;
    return local.length > 0 && local.length <= 64 && !local.startsWith('.') && !local.endsWith('.') && !local.includes('..') &&
      /^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+$/i.test(local) && domain.includes('.') &&
      domain.split('.').every(label => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(label));
  }
  function field(input) {
    input.value = input.value.trim();
    input.setCustomValidity(input.value ? (valid(input.value) ? '' : message) : (input.required ? message : ''));
    return input.validationMessage;
  }
  function check(scope) {
    let first = null;
    scope.querySelectorAll('input[type=email]').forEach(input => { if (!input.disabled && field(input) && !first) first = input; });
    return first;
  }
  document.addEventListener('input', event => { if (event.target.matches('input[type=email]')) field(event.target); });
  document.addEventListener('invalid', event => { if (event.target.matches('input[type=email]')) field(event.target); }, true);
  document.addEventListener('submit', event => {
    if (event.target.id === 'enrollment-form') return; // Wizard reveals the invalid step itself.
    const invalid = check(event.target);
    if (invalid) { event.preventDefault(); event.stopImmediatePropagation(); invalid.reportValidity(); }
  }, true);
  return { valid, check, message };
})();
