'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

function page(saved, blocked = false) {
  const dom = new JSDOM('<head><meta name="app-base" content="/escola/"></head><body></body>', {
    url: 'http://localhost/escola/login.php', runScripts: 'outside-only'
  });
  if (saved) dom.window.localStorage.setItem('legado.theme', saved);
  if (blocked) Object.defineProperty(dom.window, 'localStorage', { get() { throw Error('Blocked'); } });
  const context = dom.getInternalVMContext();
  vm.runInContext(read('assets/js/theme.js'), context);
  vm.runInContext("const APP_BASE='/escola/'; const DB={data:{settings:{logo:''}}};", context);
  vm.runInContext(read('assets/js/ui.js'), context);
  return { dom, document: dom.window.document, run: code => vm.runInContext(code, context) };
}

for (const saved of [null, 'light', 'dark', 'invalid']) {
  const p = page(saved);
  const expected = saved === 'dark' ? 'dark' : 'light';
  assert.equal(p.document.documentElement.dataset.theme, expected);
  // The first HTML already contains the correct src, before it enters the DOM.
  const markup = p.run('UI.brand()');
  assert.match(markup, new RegExp(expected === 'dark' ? 'src="/escola/assets/images/logo-escuro.jpg"' : 'src="/escola/assets/images/logo-oficial.jpg"'));
  p.document.body.innerHTML = markup + markup;
  p.run('Theme.toggle()');
  const opposite = expected === 'dark' ? 'light' : 'dark';
  for (const img of p.document.querySelectorAll('img')) {
    assert.ok(img.src.endsWith(opposite === 'dark' ? 'logo-escuro.jpg' : 'logo-oficial.jpg'));
    assert.equal(img.dataset.logoPending, 'true');
    Object.defineProperties(img, {
      complete: { get: () => true }, naturalWidth: { get: () => 1024 }, currentSrc: { get: () => img.src }
    });
    img.dispatchEvent(new p.dom.window.Event('load'));
    assert.equal(img.dataset.logoPending, undefined);
  }
  assert.equal(p.dom.window.localStorage.getItem('legado.theme'), opposite);
  const reloaded = page(p.dom.window.localStorage.getItem('legado.theme'));
  assert.equal(reloaded.document.documentElement.dataset.theme, opposite);
  reloaded.dom.window.close();
  // Re-rendered route content must inherit the active theme.
  p.document.body.innerHTML = p.run('UI.brand()');
  assert.ok(p.document.querySelector('img').src.endsWith(opposite === 'dark' ? 'logo-escuro.jpg' : 'logo-oficial.jpg'));
  p.run('Theme.toggle(); Theme.toggle(); Theme.toggle();');
  assert.equal(p.document.documentElement.dataset.theme, expected);
  p.dom.window.close();
}
const custom = page('light');
custom.run("DB.data.settings.logo='data:image/png;base64,AA==';");
custom.document.body.innerHTML = custom.run('UI.brand()');
custom.run('Theme.toggle()');
assert.ok(custom.document.querySelector('img').src.endsWith('logo-escuro.jpg'));
custom.run('Theme.toggle()');
assert.equal(custom.document.querySelector('img').src, 'data:image/png;base64,AA==');
custom.dom.window.close();
const blocked = page(null, true);
blocked.document.body.innerHTML = blocked.run('UI.brand()');
blocked.run('Theme.toggle()');
assert.equal(blocked.document.documentElement.dataset.theme, 'dark');
blocked.dom.window.close();
assert.match(read('assets/css/brand.css'), /\[data-logo-pending\]\s*\{\s*visibility: hidden/);
console.log('OK: first render, saved theme, multiple logos, toggles, reload, navigation, original restoration and unavailable storage.');
