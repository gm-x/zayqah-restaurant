/* Progressive enhancement for the restaurant site and owner panel. Every feature also works without JS. */
(function () {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    /* ---------- Toasts ---------- */
    let toastTimer;
    function toast(message, href) {
        const el = $('[data-live-toast]');
        if (!el || !message) return;
        el.textContent = '';
        if (href) {
            const a = document.createElement('a');
            a.href = href;
            a.textContent = message;
            el.append(a);
        } else {
            el.textContent = message;
        }
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.hidden = true; }, 5000);
    }
    $$('[data-toast]').forEach((el) => setTimeout(() => el.remove(), 4500));

    /* ---------- Navigation toggles ---------- */
    const menuToggle = $('.menu-toggle');
    const mobileNav = $('#mobile-nav');
    menuToggle?.addEventListener('click', () => {
        const open = mobileNav.hidden;
        mobileNav.hidden = !open;
        menuToggle.setAttribute('aria-expanded', String(open));
    });
    const ownerToggle = $('.owner-nav-toggle');
    const ownerNav = $('#owner-nav');
    ownerToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = !ownerNav.classList.contains('is-open');
        ownerNav.classList.toggle('is-open', open);
        ownerToggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (event) => {
        if (ownerNav?.classList.contains('is-open') && !ownerNav.contains(event.target)) ownerNav.classList.remove('is-open');
        $$('details.dropdown[open]').forEach((d) => { if (!d.contains(event.target)) d.open = false; });
    });

    /* ---------- Small form helpers ---------- */
    document.addEventListener('change', (event) => {
        const el = event.target;
        if (el.matches('[data-autosubmit]')) el.form?.requestSubmit ? el.form.requestSubmit() : el.form?.submit();
        if (el.matches('[data-toggle-target]')) {
            const target = $(el.dataset.toggleTarget);
            if (target) target.hidden = !el.checked;
        }
        if (el.matches('[data-photo-input]') && el.files?.[0]) {
            const preview = $('[data-photo-preview]', el.closest('form'));
            if (preview) preview.src = URL.createObjectURL(el.files[0]);
        }
    });

    $$('[data-stepper]').forEach((stepper) => {
        const input = $('input', stepper);
        $$('[data-step]', stepper).forEach((btn) => btn.addEventListener('click', () => {
            input.value = Math.min(20, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.step, 10)));
        }));
    });

    $$('tr[data-href]').forEach((row) => row.addEventListener('click', (event) => {
        if (!event.target.closest('a, button, form, select')) window.location = row.dataset.href;
    }));

    $$('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
        const input = $('[data-copy-source]', btn.parentElement);
        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand('copy');
        }
        toast('Link copied.');
    }));

    /* ---------- Cart (add / change quantity without a page reload) ---------- */
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (form.matches('[data-confirm]') && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        if (!form.matches('[data-add-to-cart], [data-cart-update]') || !window.fetch) return;
        event.preventDefault();
        const button = $('button[type="submit"]', form);
        if (button) button.disabled = true;
        try {
            // getAttribute: an <input name="action"> in the form shadows the form.action property.
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Request failed');
            const data = await response.json();
            $$('[data-cart-count]').forEach((el) => {
                el.textContent = data.count;
                el.hidden = !data.count;
            });
            const panel = $('[data-cart-panel]');
            if (panel) panel.innerHTML = data.panel;
            if (data.message) toast(data.message);
            if (form.matches('[data-cart-update]') && !panel) window.location.reload();
        } catch {
            form.submit();
        } finally {
            if (button) button.disabled = false;
        }
    });

    /* ---------- Home carousel ---------- */
    const carousel = $('[data-carousel]');
    if (carousel) {
        const slides = $$('.hero-slide', carousel);
        const dots = $$('[data-slide]', carousel);
        const heading = $('[data-hero-heading]');
        const text = $('[data-hero-text]');
        let index = 0;
        let timer;
        const show = (i) => {
            index = (i + slides.length) % slides.length;
            slides.forEach((s, n) => s.classList.toggle('is-active', n === index));
            dots.forEach((d, n) => d.setAttribute('aria-current', String(n === index)));
            const slide = slides[index];
            if (heading && slide.dataset.heading) heading.textContent = slide.dataset.heading;
            if (text) text.textContent = slide.dataset.text || '';
        };
        const start = () => {
            clearInterval(timer);
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => show(index + 1), 6000);
        };
        dots.forEach((d) => d.addEventListener('click', () => { show(parseInt(d.dataset.slide, 10)); start(); }));
        carousel.addEventListener('mouseenter', () => clearInterval(timer));
        carousel.addEventListener('mouseleave', start);
        start();
    }

    /* ---------- Menu category scrollspy ---------- */
    const spy = $('[data-scrollspy]');
    if (spy && 'IntersectionObserver' in window) {
        const links = $$('a', spy);
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                links.forEach((a) => {
                    const active = a.getAttribute('href') === '#' + entry.target.id;
                    a.classList.toggle('is-active', active);
                    if (active) a.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                });
            });
        }, { rootMargin: '-35% 0px -60% 0px' });
        $$('.menu-section').forEach((s) => observer.observe(s));
    }

    /* ---------- Checkout: collection vs delivery ---------- */
    const checkout = $('[data-checkout]');
    if (checkout) {
        const fee = parseInt(checkout.dataset.fee, 10) || 0;
        const subtotal = parseInt(checkout.dataset.subtotal, 10) || 0;
        const currency = checkout.dataset.currency;
        const money = (minor) => currency + (minor / 100).toFixed(2);
        const sync = () => {
            const method = $('input[name="fulfillment"]:checked', checkout)?.value || 'pickup';
            $$('[data-show-for]', checkout).forEach((el) => { el.hidden = el.dataset.showFor !== method; });
            $$('[data-required-for]', checkout).forEach((el) => { el.required = el.dataset.requiredFor === method; });
            const deliveryFee = method === 'delivery' ? fee : 0;
            $('[data-fee-label]', checkout).textContent = deliveryFee ? money(deliveryFee) : 'Free';
            $('[data-total]', checkout).textContent = money(subtotal + deliveryFee);
            if (method === 'delivery') window.dispatchEvent(new Event('resize')); // let a newly shown map size itself
        };
        $$('input[name="fulfillment"]', checkout).forEach((r) => r.addEventListener('change', sync));
        sync();
    }

    /* ---------- Maps (Leaflet + OpenStreetMap) ---------- */
    function pinIcon(kind) {
        return window.L.divIcon({ className: 'map-pin map-pin-' + kind, html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 28] });
    }
    function baseMap(el, lat, lng, zoom) {
        const map = window.L.map(el, { scrollWheelZoom: false }).setView([lat, lng], zoom);
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        // Maps inside hidden sections need a size recalculation when they appear.
        new ResizeObserver(() => map.invalidateSize()).observe(el);
        return map;
    }

    function initPicker(picker) {
        const el = $('[data-location-map]', picker);
        const latInput = $('[data-location-lat]', picker);
        const lngInput = $('[data-location-lng]', picker);
        const status = $('[data-location-status]', picker);
        const hasPin = latInput.value !== '' && lngInput.value !== '';
        const lat = hasPin ? parseFloat(latInput.value) : parseFloat(el.dataset.defaultLat);
        const lng = hasPin ? parseFloat(lngInput.value) : parseFloat(el.dataset.defaultLng);
        const map = baseMap(el, lat, lng, hasPin ? 16 : 12);
        let marker = null;
        const place = (point, message) => {
            const p = window.L.latLng(point);
            latInput.value = p.lat.toFixed(7);
            lngInput.value = p.lng.toFixed(7);
            if (!marker) {
                marker = window.L.marker(p, { draggable: true, icon: pinIcon('home') }).addTo(map);
                marker.on('dragend', () => place(marker.getLatLng(), 'Pin moved.'));
            } else {
                marker.setLatLng(p);
            }
            if (status) status.textContent = message;
        };
        if (hasPin) place([lat, lng], 'Pin loaded — drag it to adjust.');
        map.on('click', (e) => place(e.latlng, 'Pin placed — drag it to fine-tune.'));
        $('[data-location-gps]', picker)?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                status.textContent = 'Location is not available in this browser. Tap the map instead.';
                return;
            }
            status.textContent = 'Finding your location…';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    place([pos.coords.latitude, pos.coords.longitude], 'Pin placed at your location — drag to adjust.');
                    map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                },
                () => { status.textContent = 'Could not get your location. Tap the map to place the pin.'; },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        });
    }

    const viewMaps = [];
    function initViewMap(el) {
        const dest = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
        const map = baseMap(el, dest[0], dest[1], 15);
        window.L.marker(dest, { icon: pinIcon('home') }).addTo(map);
        const entry = { el, map, rider: null };
        entry.setRider = (point) => {
            if (!point) return;
            if (!entry.rider) entry.rider = window.L.marker(point, { icon: pinIcon('rider') }).addTo(map);
            else entry.rider.setLatLng(point);
            map.fitBounds([dest, point], { padding: [40, 40], maxZoom: 16 });
        };
        if (el.dataset.riderLat) entry.setRider([parseFloat(el.dataset.riderLat), parseFloat(el.dataset.riderLng)]);
        viewMaps.push(entry);
    }

    window.addEventListener('load', () => {
        if (!window.L) return;
        $$('[data-location-picker]').forEach(initPicker);
        $$('[data-view-map]').forEach(initViewMap);
    });

    /* ---------- Live order tracking ---------- */
    $$('[data-track]').forEach((panel) => {
        let status = panel.dataset.status;
        if (['completed', 'cancelled'].includes(status)) return;
        const poll = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(panel.dataset.trackUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const data = await response.json();
                if (data.status !== status) {
                    status = data.status;
                    panel.innerHTML = data.html;
                    toast('Order update: ' + ($('h2', panel)?.textContent || status));
                    if (status !== 'placed') $$('[data-cancel-order]').forEach((f) => f.remove());
                    // The owner's action buttons depend on the status, so refresh that page.
                    if (document.body.classList.contains('owner')) window.location.reload();
                }
                if (data.rider) viewMaps.forEach((m) => m.setRider(data.rider));
                if (['completed', 'cancelled'].includes(status)) clearInterval(timer);
            } catch { /* try again on the next tick */ }
        };
        const timer = setInterval(poll, 15000);
    });

    /* ---------- Notification bell ---------- */
    const bell = $('[data-notifications]');
    if (bell && window.fetch) {
        const badge = $('[data-unread]', bell);
        let lastId = null;
        const check = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(bell.dataset.poll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const data = await response.json();
                badge.textContent = data.unread > 99 ? '99+' : data.unread;
                badge.hidden = !data.unread;
                const newest = data.latest[0];
                if (lastId !== null && newest && newest.id > lastId) toast(newest.title + ' — ' + newest.body, newest.href);
                lastId = newest ? newest.id : 0;
            } catch { /* ignore network hiccups */ }
        };
        check();
        setInterval(check, document.body.classList.contains('owner') ? 20000 : 45000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    }

    /* ---------- Owner auto-refresh for live lists ---------- */
    const auto = $('[data-autorefresh]');
    if (auto) {
        const seconds = parseInt(auto.dataset.autorefresh, 10) || 30;
        setInterval(() => {
            const busy = document.activeElement && document.activeElement.matches('input, textarea, select');
            if (!document.hidden && !busy) window.location.reload();
        }, seconds * 1000);
    }
})();
