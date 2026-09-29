'use strict';
const Theme=(()=>{
 const normalize=value=>value==='dark'?'dark':'light';
 const base=document.querySelector('meta[name="app-base"]')?.content||'/';
 const logos={light:base+'assets/images/logo-oficial.jpg',dark:base+'assets/images/logo-escuro.jpg'};
 const get=()=>{try{return normalize(localStorage.getItem('legado.theme'));}catch{return 'light';}};
 const logoSource=light=>document.documentElement.dataset.theme==='dark'?logos.dark:(light||logos.light);
 // Keep both originals in the browser cache before the user changes theme.
 const preload=Object.values(logos).map(src=>{const image=new Image();image.src=src;return image;});
 function revealLogo(image){
  if(image.complete&&image.naturalWidth>0&&image.currentSrc===image.src)delete image.dataset.logoPending;
 }
 document.addEventListener('load',event=>{
  if(event.target.matches?.('img[data-theme-logo]'))revealLogo(event.target);
 },true);
 function set(value){
  const theme=normalize(value);
  document.documentElement.dataset.theme=theme;
  document.querySelectorAll('img[data-theme-logo]').forEach(image=>{
   const src=logoSource(image.dataset.logoLight);
   if(image.getAttribute('src')===src)return;
   // A browser may retain the previous bitmap while the new src loads.
   image.dataset.logoPending='true';
   image.src=src;
   revealLogo(image);
  });
  try{localStorage.setItem('legado.theme',theme);}catch{}
 }
 function toggle(){set(document.documentElement.dataset.theme==='dark'?'light':'dark');}
 set(get());return {toggle,logoSource};
})();
