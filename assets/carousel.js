(() => {
  document.querySelectorAll('[data-carousel]').forEach(root => {
    const slides = [...root.querySelectorAll('.zq-slide')];
    if (slides.length < 2) return;
    const heading = root.querySelector('[data-carousel-heading]');
    const description = root.querySelector('[data-carousel-description]');
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0, hovered = false, timer, gesture = null, suppressClick = false;
    const show = index => {
      current = (index + slides.length) % slides.length;
      if (heading) heading.textContent = slides[current].dataset.heading;
      if (description) description.textContent = slides[current].dataset.description;
      slides.forEach((slide, i) => { slide.hidden = i !== current; });
      dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === current)));
    };
    const sync = () => {
      clearInterval(timer);
      root.setAttribute('aria-live', reduced.matches ? 'polite' : 'off');
      if (!reduced.matches && !hovered && !gesture && !document.hidden && !root.contains(document.activeElement)) {
        timer = setInterval(() => show(current + 1), 6000);
      }
    };
    const move = index => { show(index); sync(); };
    dots.forEach((dot, i) => dot.addEventListener('click', () => move(i)));
    root.addEventListener('pointerenter', event => {
      if (event.pointerType === 'mouse') { hovered = true; sync(); }
    });
    root.addEventListener('pointerleave', event => {
      if (event.pointerType === 'mouse') { hovered = false; sync(); }
    });
    root.addEventListener('pointerdown', event => {
      if (!event.isPrimary || event.button !== 0 || event.target.closest('a,button,input,select,textarea,summary')) return;
      suppressClick = false;
      gesture = { id: event.pointerId, x: event.clientX, y: event.clientY, vertical: false };
      root.setPointerCapture(event.pointerId);
      sync();
    });
    root.addEventListener('pointermove', event => {
      if (!gesture || event.pointerId !== gesture.id) return;
      const dx = Math.abs(event.clientX - gesture.x), dy = Math.abs(event.clientY - gesture.y);
      if (dy > 12 && dy > dx) gesture.vertical = true;
    });
    const finish = (event, cancelled = false) => {
      if (!gesture || event.pointerId !== gesture.id) return;
      const start = gesture;
      gesture = null;
      const dx = event.clientX - start.x, dy = event.clientY - start.y;
      const threshold = Math.max(32, Math.min(64, root.clientWidth * 0.08));
      if (!cancelled && !start.vertical && Math.abs(dx) >= threshold && Math.abs(dx) > Math.abs(dy) * 1.3) {
        suppressClick = true;
        setTimeout(() => { suppressClick = false; }, 0);
        show(current + (dx < 0 ? 1 : -1));
      }
      if (root.hasPointerCapture(event.pointerId)) root.releasePointerCapture(event.pointerId);
      sync();
    };
    root.addEventListener('pointerup', event => finish(event));
    root.addEventListener('pointercancel', event => finish(event, true));
    root.addEventListener('lostpointercapture', () => { gesture = null; sync(); });
    root.addEventListener('click', event => {
      if (suppressClick) { suppressClick = false; event.preventDefault(); event.stopPropagation(); }
    }, true);
    root.addEventListener('dragstart', event => { if (event.target.tagName === 'IMG') event.preventDefault(); });
    root.addEventListener('focusin', sync);
    root.addEventListener('focusout', () => setTimeout(sync, 0));
    root.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault(); move(current + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
    document.addEventListener('visibilitychange', sync);
    reduced.addEventListener('change', sync);
    show(0); sync();
  });
})();
