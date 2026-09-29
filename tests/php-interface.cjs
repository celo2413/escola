'use strict';
// Teste DOM opcional; não faz parte do backend PHP.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {JSDOM,VirtualConsole}=require('jsdom');
const base=process.env.TEST_BASE||'http://127.0.0.1:8089';
async function client(){let cookie='',csrf='';return {async request(path,method='GET',body){const res=await fetch(base+'/api/index.php?route='+encodeURIComponent(path),{method,headers:{Cookie:cookie,'Content-Type':'application/json','X-CSRF-Token':csrf},...(body?{body:JSON.stringify(body)}:{})});const c=res.headers.get('set-cookie');if(c)cookie=c.split(';')[0];const data=await res.json();if(data.csrf)csrf=data.csrf;assert.equal(res.status,200,JSON.stringify(data));return data;},async html(path){return (await fetch(base+path,{headers:{Cookie:cookie}})).text();},get cookie(){return cookie;},set cookie(v){cookie=v;}};}
async function wait(fn){for(let i=0;i<300;i++){if(fn())return;await new Promise(r=>setTimeout(r,20));}throw Error('Tempo esgotado aguardando interface.');}
async function page(role,password='123456'){
 const c=await client();await c.request('/public');if(role)await c.request('/auth/login','POST',{email:role.includes('@')?role:role+'@gmail.com',password});
 const path=role?'/'+(role.includes('@')?(role.startsWith('responsavel')?'responsavel':'aluno'):role)+'/dashboard.php':'/login.php';const html=await c.html(path);const errors=[];const virtualConsole=new VirtualConsole();virtualConsole.on('jsdomError',e=>{if(!/navigation|scrollTo/.test(e.message))errors.push(e.message);});
 const dom=new JSDOM(html,{url:base+path,runScripts:'outside-only',pretendToBeVisual:true,virtualConsole});const w=dom.window;
 w.fetch=async(url,options={})=>{const res=await fetch(new URL(url,w.location.href),{...options,headers:{...options.headers,Cookie:c.cookie}});const cookie=res.headers.get('set-cookie');if(cookie)c.cookie=cookie.split(';')[0];return res;};w.scrollTo=()=>{};w.HTMLElement.prototype.scrollIntoView=()=>{};w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};w.print=()=>{};
 for(const file of ['theme','email-validation','database','auth','ui','entities','academic','dashboard','workflows','management','app'])vm.runInContext(fs.readFileSync('assets/js/'+file+'.js','utf8'),dom.getInternalVMContext(),{filename:file+'.js'});
 await wait(()=>w.document.querySelector(role?'#main[data-page]':'#login-form'));
 return {dom,w,errors,run:code=>vm.runInContext(code,dom.getInternalVMContext()),async route(route){w.location.hash=route;await wait(()=>w.document.querySelector('#main')?.dataset.page===route.split('/')[0]&&!w.document.querySelector('#main .loading'));assert(!w.document.querySelector('#main').textContent.includes('Página não encontrada'));assert(!w.document.querySelector('#main').textContent.includes('Acesso não autorizado'));}};
}
(async()=>{
 let count=0;
 const publicPage=await page();assert.equal(publicPage.w.document.querySelector('.brand img').getAttribute('src'),'/assets/images/logo-oficial.jpg');assert(!publicPage.w.document.querySelector('#login-form').textContent.includes('123456'));publicPage.w.location.hash='enrollment';await wait(()=>publicPage.w.document.querySelector('#enrollment-form'));assert(publicPage.w.document.querySelector('[name=year]'));assert(publicPage.w.document.querySelector('[name=requestedPeriod] option[value=Noite]'));publicPage.dom.window.close();count++;
 for(const [role,pass] of [['diretor','123456'],['coordenador','123456'],['professor','123456'],['estudante.integracao@gmail.com','Integracao123!'],['responsavel.integracao@gmail.com','ResponsavelNova123!']]){
  const p=await page(role,pass);const routes=p.run('Auth.menus[Auth.user.role]');for(const route of routes){await p.route(route);assert(!p.w.document.querySelector('#main').textContent.includes('undefined'),role+' '+route);count++;}
  if(role==='diretor'){for(const type of ['classes','teachers','subjects','users']){p.run(`Entities.edit('${type}')`);assert(p.w.document.querySelector('#entity-form'));assert(!p.w.document.querySelector('#entity-form').textContent.includes('${'));p.run('UI.close()');}for(const type of ['years','terms','links','schedules','evaluations']){p.run(`Management.render('${type}')`);p.w.document.querySelector(`[data-management=edit][data-type=${type}]`)?.click();}p.run("Workflows.enrollmentApprove(DB.data.enrollments[0].id)");assert(p.w.document.querySelector('[name=guardianPassword]'));p.run('UI.close()');}
  if(role==='professor'){await p.route('grades');assert(p.w.document.querySelector('[name^=grade_]'));assert.equal(p.w.document.querySelector('[name^=grade_]').max,'20');await p.route('diary');assert(p.w.document.querySelector('[data-action=presence]'));}
  assert.deepEqual(p.errors,[],role+' erros DOM');p.dom.window.close();
 }
 console.log(count+' telas e estados DOM verificados com a API PHP real.');
})().catch(e=>{console.error(e);process.exit(1);});
