(() => {
  document.querySelectorAll('[data-carousel]').forEach(root => {
    const slides = [...root.querySelectorAll('.zq-slide')];
    if (slides.length < 2) return;
    const heading = root.querySelector('[data-carousel-heading]');
    const description = root.querySelector('[data-carousel-description]');
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    root.dataset.carouselEngine = 'autoplay-3s-sliding';
    let current = 0, timer, gesture = null, suppressClick = false;
    let animations = [], revision = 0;
    const preload = index => {
      const img = slides[(index + slides.length) % slides.length].querySelector('img');
      if (img) img.loading = 'eager';
    };
    const show = (index, direction = 1, animate = true) => {
      const next = (index + slides.length) % slides.length;
      const previous = current;
      const ticket = ++revision;
      animations.forEach(animation => animation.cancel());
      animations = [];
      current = next;
      if (heading) heading.textContent = slides[current].dataset.heading;
      if (description) description.textContent = slides[current].dataset.description;
      slides.forEach((slide, i) => {
        slide.hidden = i !== current;
        slide.setAttribute('aria-hidden', String(i !== current));
      });
      dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === current)));
      preload(current);
      preload(current + 1);
      if (!animate || next === previous || typeof slides[next].animate !== 'function') return;
      const outgoing = slides[previous], incoming = slides[next];
      outgoing.hidden = false;
      const timing = { duration: 500, easing: 'cubic-bezier(.22,.61,.36,1)', fill: 'both' };
      animations.push(
        outgoing.animate([{ transform: 'translateX(0)' }, { transform: `translateX(${-direction * 100}%)` }], timing),
        incoming.animate([{ transform: `translateX(${direction * 100}%)` }, { transform: 'translateX(0)' }], timing)
      );
      [heading, description].filter(Boolean).forEach(element => {
        if (typeof element.animate === 'function') animations.push(element.animate([
          { opacity: 0, transform: `translateX(${direction * 16}px)` },
          { opacity: 1, transform: 'translateX(0)' }
        ], { duration: 450, easing: 'ease-out', fill: 'both' }));
      });
      Promise.allSettled(animations.map(animation => animation.finished)).then(() => {
        if (ticket !== revision) return;
        outgoing.hidden = true;
        animations.forEach(animation => animation.cancel());
        animations = [];
      });
    };
    const sync = () => {
      clearInterval(timer);
      // Hover and retained touch/dot focus must not stop autoplay.
      root.setAttribute('aria-live', 'off');
      if (!gesture && !document.hidden) {
        timer = setInterval(() => show(current + 1), 3000);
      }
    };
    const move = index => { show(index, index < current ? -1 : 1); sync(); };
    dots.forEach((dot, i) => dot.addEventListener('click', () => move(i)));
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
        show(current + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
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
    root.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault(); move(current + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
    document.addEventListener('visibilitychange', sync);
    show(0, 1, false); sync();
  });
})();
