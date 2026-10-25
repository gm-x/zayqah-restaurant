'use strict';
const formatPaisa=value=>'Rs '+(value/100).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});
const checkout=document.getElementById('checkout-form');
function updateCheckout(){
 if(!checkout)return;let subtotal=0,delivery=0,needsAddress=false;
 checkout.querySelectorAll('.checkout-shop').forEach(shop=>{subtotal+=Number(shop.dataset.subtotal)||0;const selected=shop.querySelector('input[type=radio]:checked');const fee=selected?.value==='delivery'?(Number(selected.dataset.fee)||0):0;delivery+=fee;if(selected?.value==='delivery')needsAddress=true;const line=checkout.querySelector('[data-shop-fee="'+shop.dataset.shop+'"]');if(line)line.textContent=formatPaisa(fee);});
 checkout.querySelector('[data-delivery-total]').textContent=formatPaisa(delivery);checkout.querySelector('[data-checkout-total]').textContent=formatPaisa(subtotal+delivery);
 const section=document.getElementById('delivery-address-section');if(section)section.hidden=!needsAddress;const address=checkout.querySelector('[name=delivery_address]');if(address)address.required=needsAddress;
}
checkout?.addEventListener('change',updateCheckout);updateCheckout();
checkout?.addEventListener('submit',()=>{if(!checkout.checkValidity())return;const button=checkout.querySelector('button[type=submit]');if(button){button.disabled=true;button.textContent='Placing your order…';}});
const scheduleMode=document.getElementById('schedule_mode');
function updateSchedule(){
 if(!scheduleMode)return;const mode=scheduleMode.value;
 document.querySelectorAll('[data-schedule-section]').forEach(section=>{const active=section.dataset.scheduleSection.split(' ').includes(mode);section.hidden=!active;section.querySelectorAll('input').forEach(input=>{input.disabled=!active;input.required=active && ['starts_at','expires_at','start_time','end_time','start_date'].includes(input.name);});});
}
scheduleMode?.addEventListener('change',updateSchedule);updateSchedule();
