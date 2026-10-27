(() => {
  'use strict';
  const pickers = document.querySelectorAll('[data-location-picker]');
  const validPoint = (a,b) => a!=='' && b!=='' && Number.isFinite(Number(a)) && Number.isFinite(Number(b)) && Math.abs(Number(a))<=90 && Math.abs(Number(b))<=180;
  const pinIcon = color => window.L.divIcon({className:'zq-map-marker',html:`<span style="background:${color}"></span>`,iconSize:[24,24],iconAnchor:[12,12]});
  const newMap = (element,point,zoom) => {
    const map = L.map(element,{scrollWheelZoom:false}).setView(point,zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'}).addTo(map);
    setTimeout(() => map.invalidateSize(),0);
    return map;
  };
  pickers.forEach(picker => {
    const lat=picker.querySelector('[name=latitude]'),lng=picker.querySelector('[name=longitude]');
    const status=picker.querySelector('[data-location-status]'),canvas=picker.querySelector('[data-location-map]'),gps=picker.querySelector('[data-location-gps]');
    let map,marker;
    const choose = (a,b,pan=true) => {
      if(!validPoint(String(a),String(b)))return;
      lat.value=Number(a).toFixed(7);lng.value=Number(b).toFixed(7);
      if(map){
        if(!marker){marker=L.marker([a,b],{draggable:true,icon:pinIcon('#ff7a00')}).addTo(map);marker.on('dragend',() => {const p=marker.getLatLng();choose(p.lat,p.lng,false);});}
        else marker.setLatLng([a,b]);
        if(pan)map.setView([a,b],Math.max(map.getZoom(),15));
      }
      status.textContent=zqT('Location pin selected. You can adjust it before submitting.');
    };
    if(window.L){
      const saved=validPoint(lat.value,lng.value);
      map=newMap(canvas,saved?[Number(lat.value),Number(lng.value)]:[30.3753,69.3451],saved?15:5);
      map.on('click',event => choose(event.latlng.lat,event.latlng.lng,false));
      if(saved)choose(lat.value,lng.value,false);
    }else{canvas.hidden=true;status.textContent=zqT('The map could not load. Use GPS or enter coordinates below.');}
    [lat,lng].forEach(input => input.addEventListener('change',() => {if(validPoint(lat.value,lng.value))choose(lat.value,lng.value);}));
    gps.addEventListener('click',() => {
      if(!window.isSecureContext){status.textContent=zqT('GPS requires an HTTPS connection. Choose a pin on the map or enter coordinates.');return;}
      if(!navigator.geolocation){status.textContent=zqT('GPS is unavailable. Choose a pin on the map or enter coordinates.');return;}
      gps.disabled=true;status.textContent=zqT('Finding your location…');
      navigator.geolocation.getCurrentPosition(position => {gps.disabled=false;choose(position.coords.latitude,position.coords.longitude);},error => {gps.disabled=false;status.textContent=error.code===1?zqT('Location permission was denied. You can still choose a pin on the map.'):zqT('Could not get a GPS position. Choose a pin on the map or try again.');},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
    });
  });
  const viewMaps=new Map();
  document.querySelectorAll('[data-view-map]').forEach(canvas => {
    if(!window.L || !validPoint(canvas.dataset.lat,canvas.dataset.lng))return;
    const point=[Number(canvas.dataset.lat),Number(canvas.dataset.lng)],map=newMap(canvas,point,15);
    L.marker(point,{icon:pinIcon('#ff7a00')}).addTo(map).bindTooltip(zqT('Customer destination'));
    const state={map,rider:null};
    const update=location => {
      if(location?.rider_latitude===null || !validPoint(String(location?.rider_latitude??''),String(location?.rider_longitude??'')))return;
      const rider=[Number(location.rider_latitude),Number(location.rider_longitude)];
      if(state.rider)state.rider.setLatLng(rider);else state.rider=L.marker(rider,{icon:pinIcon('#41622a')}).addTo(map).bindTooltip(zqT('Rider’s last shared pin'));
    };
    update({rider_latitude:canvas.dataset.riderLat??null,rider_longitude:canvas.dataset.riderLng??null});
    if(state.rider)map.fitBounds(L.latLngBounds([point,state.rider.getLatLng()]),{padding:[28,28],maxZoom:15});
    viewMaps.set(canvas.dataset.orderMap,{update});
  });
  document.querySelectorAll('[data-auto-category]').forEach(group => group.addEventListener('change',event => {
    const input=event.target;if(!input.matches('input[name=category]'))return;
    const form=input.form;form.action=new URL(Number(input.value)>0?'category.php':'index.php',form.action).href;form.submit();
  }));
  const more=document.querySelector('[data-load-more]');
  more?.addEventListener('click',async event => {
    event.preventDefault();if(more.getAttribute('aria-disabled')==='true')return;
    const status=document.querySelector('[data-load-status]');more.setAttribute('aria-disabled','true');more.textContent=zqT('Loading…');
    try{
      const target=new URL(more.href);target.searchParams.set('format','json');
      const response=await fetch(target,{headers:{Accept:'application/json'},credentials:'same-origin'});
      if(!response.ok)throw new Error('load');const data=await response.json();if(data.error)throw new Error(data.error);
      const parsed=new DOMParser().parseFromString(data.html,'text/html');const grid=document.querySelector('[data-category-results] .restaurant-grid');
      if(grid){parsed.querySelectorAll('.food-card').forEach(card => grid.append(card));}
      const shown=document.querySelectorAll('[data-category-results] .food-card').length;
      document.querySelector('[data-load-count]').textContent=zqT('{shown} of {total} dishes shown',{shown,total:data.total});
      status.textContent=data.count?zqT('{count} more dishes loaded.',{count:data.count}):zqT('You’ve reached the end.');
      if(data.next){more.href=data.next;more.textContent=zqT('Load more dishes');more.removeAttribute('aria-disabled');}else more.hidden=true;
    }catch(error){status.textContent=zqT('Could not load more dishes. Please try again.');more.textContent=zqT('Try again');more.removeAttribute('aria-disabled');}
  });
  const account=document.querySelector('[data-account-updates]');
  const dialog=document.querySelector('[data-review-dialog]');
  let promptId=null,polling=false,latest=Number(account?.dataset.latest||0),promptInFlight=null;
  const postUpdate=async(action,id) => {
    if(!account)return;const data=new FormData();data.append('csrf',account.querySelector('[name=csrf]').value);data.append('action',action);data.append('id',id);
    const response=await fetch(account.dataset.url,{method:'POST',body:data,credentials:'same-origin'});if(!response.ok)throw new Error('update');return response.json();
  };
  const closePrompt=async() => {
    const id=promptId;if(!id)return;
    try{await postUpdate('dismiss',id);promptId=null;dialog.close();}catch(error){dialog.querySelector('[data-review-body]').textContent=zqT('Could not save this choice. Please try again.');}
  };
  dialog?.querySelector('[data-review-later]').addEventListener('click',closePrompt);
  dialog?.addEventListener('cancel',event => {event.preventDefault();closePrompt();});
  dialog?.querySelector('[data-review-link]').addEventListener('click',() => {if(promptId)postUpdate('dismiss',promptId).catch(() => {});});
  const renderUpdates=data => {
    const badge=document.querySelector('[data-notification-count]');if(badge){badge.textContent=data.unread>99?'99+':data.unread;badge.hidden=!data.unread;}
    const trigger=document.querySelector('[data-notification-trigger]');if(trigger)trigger.setAttribute('aria-label',data.unread?zqT('Notifications, {count} unread',{count:data.unread}):zqT('Notifications'));
    const fresh=data.notifications?.find(n => n.id>latest);
    if(fresh){const toast=document.querySelector('.update-toast');toast.replaceChildren();const link=document.createElement('a');link.href=fresh.href;link.textContent=fresh.title+' · '+fresh.body;toast.append(link);toast.hidden=false;setTimeout(() => {toast.hidden=true;},7000);}
    latest=Math.max(latest,Number(data.latest||0));
    if(data.prompt && dialog && !dialog.open && !promptInFlight){
      promptId=data.prompt.id;dialog.querySelector('[data-review-title]').textContent=data.prompt.title;dialog.querySelector('[data-review-body]').textContent=data.prompt.body;dialog.querySelector('[data-review-link]').href=data.prompt.href;
      if(typeof dialog.showModal==='function')dialog.showModal();
    }
  };
  const poll=async() => {
    if(document.hidden||polling)return;polling=true;
    try{
      if(account){const response=await fetch(account.dataset.url,{credentials:'same-origin',headers:{Accept:'application/json'}});if(response.ok)renderUpdates(await response.json());}
      for(const panel of document.querySelectorAll('[data-track-panel]')){
        if(panel.contains(document.activeElement))continue;
        const response=await fetch(panel.dataset.trackUrl,{credentials:'same-origin',headers:{Accept:'application/json'}});if(response.ok){const data=await response.json();panel.innerHTML=data.html;if(data.location)viewMaps.get(String(data.order_id))?.update(data.location);}
      }
    }catch(error){/* Keep the current UI during a temporary connection failure. */}finally{polling=false;}
  };
  if(account || document.querySelector('[data-track-panel]')){poll();setInterval(poll,15000);document.addEventListener('visibilitychange',() => {if(!document.hidden)poll();});}
})();
