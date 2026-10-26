'use strict';
const mobileToggle=document.querySelector('.menu-toggle');
const mobileNav=document.getElementById('mobile-nav');
const mobileMenuIcon=mobileToggle?.querySelector('svg')?.outerHTML || '<svg class="ui-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
const mobileCloseIcon='<svg class="ui-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>';
const mobileBreakpoint=window.matchMedia('(max-width: 1020px)');
function setMobileMenu(open,restoreFocus=false){
 if(!mobileNav || !mobileToggle)return;
 mobileNav.hidden=!open;mobileToggle.setAttribute('aria-expanded',String(open));
 mobileToggle.setAttribute('aria-label',open?'Close navigation':'Open navigation');
 mobileToggle.innerHTML=open?mobileCloseIcon:mobileMenuIcon;
 if(!open)mobileNav.querySelectorAll('details[open]').forEach(el=>el.open=false);
 if(restoreFocus)mobileToggle.focus();
}
function positionCart(){
 const cart=document.getElementById('header-cart');if(!cart?.open)return;
 const trigger=cart.querySelector('summary').getBoundingClientRect();
 const panel=cart.querySelector('#cart-content');
 const edge=16;const width=Math.min(380,window.innerWidth-edge*2);
 const left=Math.max(edge,Math.min(trigger.right-width,window.innerWidth-width-edge));
 const headerBottom=document.querySelector('.header-v3')?.getBoundingClientRect().bottom ?? trigger.bottom;
 const top=Math.max(8,Math.min(Math.max(trigger.bottom+10,headerBottom+8),window.innerHeight-100));
 panel.style.setProperty('--cart-top',top+'px');
 panel.style.setProperty('--cart-right',Math.max(edge,window.innerWidth-left-width)+'px');
}
function closeHeaderDropdowns(except=null){
 document.querySelectorAll('.header-dropdown[open]').forEach(el=>{if(el!==except)el.open=false;});
}
function openCart(){
 const cart=document.getElementById('header-cart');if(!cart)return;
 setMobileMenu(false);closeHeaderDropdowns(cart);cart.open=true;positionCart();
}
mobileToggle?.addEventListener('click',()=>{
 const open=mobileToggle.getAttribute('aria-expanded')!=='true';
 closeHeaderDropdowns();setMobileMenu(open);
});
document.querySelectorAll('.header-dropdown>summary').forEach(summary=>{
 summary.addEventListener('click',event=>{
  event.preventDefault();const dropdown=summary.parentElement;const open=!dropdown.open;
  closeHeaderDropdowns(dropdown);setMobileMenu(false);dropdown.open=open;
  if(open && dropdown.id==='header-cart')positionCart();
 });
});
document.addEventListener('click',event=>{
 document.querySelectorAll('.header-dropdown[open]').forEach(el=>{if(!el.contains(event.target))el.open=false;});
 if(!event.composedPath().includes(document.querySelector('.header-v3')) && mobileNav && !mobileNav.hidden)setMobileMenu(false);
});
document.addEventListener('keydown',event=>{
 if(event.key!=='Escape')return;
 const open=document.querySelector('.header-dropdown[open]');
 if(open){open.open=false;open.querySelector('summary').focus();}
 else if(mobileNav && !mobileNav.hidden)setMobileMenu(false,true);
});
mobileNav?.addEventListener('click',event=>{if(event.target.closest('a'))setMobileMenu(false);});
mobileBreakpoint.addEventListener('change',()=>{closeHeaderDropdowns();setMobileMenu(false);});
window.addEventListener('resize',positionCart,{passive:true});
window.addEventListener('scroll',positionCart,{passive:true});
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', e => {
 if (!confirm(form.dataset.confirm)) e.preventDefault();
}));
function hideExpired() {
 document.querySelectorAll('[data-expires]').forEach(card => {
  if (Date.parse(card.dataset.expires) <= Date.now()) card.remove();
 });
}
hideExpired();setInterval(hideExpired, 15000);

let cartFeedbackTimer;
function cartFeedback(message) {
 const notice=document.querySelector('.cart-feedback');if(!notice)return;
 notice.textContent=message;notice.hidden=false;clearTimeout(cartFeedbackTimer);
 cartFeedbackTimer=setTimeout(()=>{notice.hidden=true;},4500);
}
document.addEventListener('submit', async event => {
 const form=event.target;if(!(form instanceof HTMLFormElement) || !form.matches('[data-cart-form]') || !window.fetch)return;
 event.preventDefault();const submit=event.submitter;const data=new FormData(form);
 if(submit?.name)data.append(submit.name,submit.value);if(submit)submit.disabled=true;
 try {
  const response=await fetch(form.getAttribute('action') || window.location.href,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
  const result=await response.json();
  if(result.html){const panel=document.getElementById('cart-content');if(panel)panel.innerHTML=result.html;document.querySelectorAll('.cart-full-page').forEach(el=>el.innerHTML=result.html);}
  if(Number.isInteger(result.count))document.querySelectorAll('[data-cart-count]').forEach(el=>el.textContent=result.count);
  cartFeedback(result.message || 'Cart updated.');
  if(!result.error && data.get('action')==='add')openCart();
  else positionCart();
 } catch(error) {cartFeedback('Could not update your cart. Reload the page and try again.');}
 finally {if(submit)submit.disabled=false;}
});

// Hide decorative hero imagery gracefully if an external image is unavailable.
document.querySelectorAll('.market-hero img').forEach(img=>{
 const hide=()=>{img.hidden=true;img.style.display='none';};
 img.addEventListener('error',hide);if(img.complete && img.naturalWidth===0)hide();
});
