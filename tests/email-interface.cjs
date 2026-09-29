'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {JSDOM,VirtualConsole}=require('jsdom');
const base='http://127.0.0.1:8089';
function client(){let cookie='',csrf='';return {
  async fetch(url,options={}){const response=await fetch(new URL(url,base),{...options,headers:{...options.headers,Cookie:cookie}});const set=response.headers.get('set-cookie');if(set)cookie=set.split(';')[0];return response;},
  async request(route,body){const response=await this.fetch('/api/index.php?route='+encodeURIComponent(route),body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(body)}:{});const data=await response.json();if(data.csrf)csrf=data.csrf;return {status:response.status,data};}
};}
async function wait(fn){for(let i=0;i<150;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,20));}throw Error('Interface não respondeu');}
async function mount(c,path,scripts){
  const response=await c.fetch(path);assert.equal(response.status,200);
  const errors=[];const console=new VirtualConsole();console.on('jsdomError',error=>{if(!/navigation|scrollTo/.test(error.message))errors.push(error.message);});
  const dom=new JSDOM(await response.text(),{url:base+path,runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:console});
  dom.window.fetch=(url,options)=>c.fetch(url,options);dom.window.scrollTo=()=>{};dom.window.HTMLElement.prototype.scrollIntoView=()=>{};
  for(const script of scripts)vm.runInContext(fs.readFileSync('assets/js/'+script+'.js','utf8'),dom.getInternalVMContext());
  return {dom,w:dom.window,errors};
}
(async()=>{
  const director=client();await director.request('/public');assert.equal((await director.request('/auth/login',{email:'diretor@gmail.com',password:'123456'})).status,200);
  const email='legado.interface.'+Date.now()+'@gmail.com';
  const created=await director.request('/records/users',{name:'Teste interface e-mail',email,password:'TesteEmail123!',role:'coordenador',status:'Ativo'});
  assert.equal(created.status,201);assert.equal(created.data.emailDelivery,'unavailable');
  const candidate=client();await candidate.request('/public');const login=await candidate.request('/auth/login',{email,password:'TesteEmail123!'});
  assert.equal(login.status,200);assert.equal(login.data.redirect,'/status-matricula.php');
  assert.equal((await candidate.fetch('/coordenador/dashboard.php')).status,403);
  const page=await mount(candidate,'/status-matricula.php',['theme','email-validation','database','auth','ui','entities','academic','dashboard','workflows','management','app']);
  await wait(()=>page.w.document.querySelector('[data-action=resend-verification]'));
  assert.ok(page.w.document.body.textContent.includes(email));assert.ok(page.w.document.body.textContent.includes('ainda não foi enviada'));
  assert.equal(page.w.document.querySelector('.sidebar'),null);assert.equal(page.w.document.querySelector('#password-form'),null);
  page.w.document.querySelector('[data-action=resend-verification]').click();
  await wait(()=>page.w.document.querySelector('#toasts').textContent.includes('60 segundos'));
  assert.deepEqual(page.errors,[]);page.dom.window.close();
  const visitor=client();const confirmation=await mount(visitor,'/confirmar-email.php#token='+'f'.repeat(64),['theme','email-validation','database','ui','email-confirmation']);
  await wait(()=>!confirmation.w.document.querySelector('#confirm-email-button').disabled);
  assert.equal(confirmation.w.location.hash,'');
  confirmation.w.document.querySelector('#confirm-email-form').dispatchEvent(new confirmation.w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(()=>confirmation.w.document.querySelector('#confirmation-message').textContent.includes('inválido'));
  assert.deepEqual(confirmation.errors,[]);confirmation.dom.window.close();
  console.log('OK: new account gate, PHP authorization, resend feedback, confirmation page, token removed from URL and invalid-link feedback.');
})().catch(error=>{console.error(error);process.exitCode=1;});
