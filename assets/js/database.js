'use strict';
const APP_BASE=document.querySelector('meta[name=app-base]')?.content||'./';
const API=(()=>{
 let csrf='';
 async function request(path,method='GET',body){const response=await fetch(APP_BASE+'api/index.php?route='+encodeURIComponent(path),{method,credentials:'same-origin',headers:{...(body!==undefined?{'Content-Type':'application/json'}:{}),...(csrf?{'X-CSRF-Token':csrf}:{})},...(body!==undefined?{body:JSON.stringify(body)}:{})});let data;try{data=await response.json();}catch{throw Error('Resposta inválida do servidor.');}if(!response.ok){const error=Error(data.message||'Não foi possível concluir a operação.');error.status=response.status;error.fields=data.fields||{};if(response.status===401&&!['/auth/login','/auth/session'].includes(path))document.dispatchEvent(new CustomEvent('session-expired'));throw error;}if(data.csrf)csrf=data.csrf;return data;}
 return {request,clear:()=>{csrf='';}};
})();
const DB=(()=>{
 const blank=()=>({user:null,settings:{name:'Colégio Legado',year:new Date().getFullYear(),term:1,minimum:6,logo:'',email:''},students:[],teachers:[],classes:[],subjects:[],grades:[],attendance:[],activities:[],occurrences:[],announcements:[],contents:[],users:[],enrollments:[],timeRecords:[],adjustments:[],schedules:[],notifications:[],logs:[],years:[],terms:[],evaluations:[],links:[],gradeEntries:[],frequencies:[],read:{}});
 let data=blank();
 async function init(){const publicData=await API.request('/public');data.settings=publicData.settings;try{const session=await API.request('/auth/session');data.user=session.user;await refresh();}catch(e){if(e.status!==401)throw e;}return data;}
 async function refresh(){data=await API.request('/bootstrap');return data;}
 async function upsert(type,r){const exists=data[type]?.some(x=>x.id===r.id);const result=await API.request('/records/'+type+(exists?'/'+encodeURIComponent(r.id):''),exists?'PUT':'POST',r);await refresh();return result;}
 async function remove(type,id){await API.request('/records/'+type+'/'+encodeURIComponent(id),'DELETE',{});await refresh();}
 function clear(){const settings=data.settings;data=blank();data.settings=settings;}
 return {init,refresh,upsert,remove,clear,id:()=>crypto.randomUUID(),today:()=>new Date().toLocaleDateString('en-CA',{timeZone:'America/Sao_Paulo'}),get data(){return data;},get:(type,id)=>data[type]?.find(x=>x.id===id)};
})();
