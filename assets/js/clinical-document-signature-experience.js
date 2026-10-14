/* SIGN-UX02: presentation and canvas input only; document bindings stay with each writer. */
(function (global) {
  'use strict';

  const copy = Object.freeze({
    direct: 'FIRMAR AQUÍ',
    instruction: 'Dibuja tu firma en el recuadro con el dedo, lápiz o mouse.',
    registered: 'USAR FIRMA REGISTRADA',
    clear: 'LIMPIAR DIBUJO',
    replace: 'VOLVER A FIRMAR',
    remote: 'FIRMAR EN OTRO DISPOSITIVO',
    remoteIntro: 'Abre el enlace o escanea el código en el otro dispositivo. Revisa el documento y firma allí.'
  });

  const capabilities = () => {
    const media = query => !!global.matchMedia?.(query).matches;
    const touch = (navigator.maxTouchPoints || 0) > 0 || media('(pointer: coarse)') || media('(any-pointer: coarse)');
    const hover = media('(hover: hover)') || media('(any-hover: hover)');
    const width = global.innerWidth || document.documentElement.clientWidth;
    return { pointerEvents: !!global.PointerEvent, touch, hover, compact: width < 600,
      mode: touch ? (hover ? 'hybrid' : 'direct-touch') : 'desktop-pointer' };
  };

  const resizeCanvas = (canvas, { preserve = true, minWidth = 1, minHeight = 1,
    strokeStyle = '#0f172a', lineWidth = 2 } = {}) => {
    if (!canvas) return null;
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(minWidth, Math.round(rect.width || canvas.clientWidth || canvas.width));
    const height = Math.max(minHeight, Math.round(rect.height || canvas.clientHeight || canvas.height));
    const ratio = Math.max(1, global.devicePixelRatio || 1);
    const pixelWidth = Math.round(width * ratio);
    const pixelHeight = Math.round(height * ratio);
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    if (canvas.width === pixelWidth && canvas.height === pixelHeight) return ctx;
    let snapshot = null;
    if (preserve && canvas.width && canvas.height) {
      snapshot = document.createElement('canvas');
      snapshot.width = canvas.width;
      snapshot.height = canvas.height;
      snapshot.getContext('2d')?.drawImage(canvas, 0, 0);
    }
    canvas.width = pixelWidth;
    canvas.height = pixelHeight;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = lineWidth;
    ctx.strokeStyle = strokeStyle;
    if (snapshot) ctx.drawImage(snapshot, 0, 0, width, height);
    return ctx;
  };

  const point = (canvas, event) => {
    const rect = canvas.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
  };

  // Adapters retain each document's state transitions and binding calls.
  const bindPointer = (canvas, { start, move, end, cancel } = {}) => {
    if (!canvas || canvas.dataset.signaturePointerBound === '1') return;
    canvas.style.touchAction = 'none';
    let active = null;
    const down = event => {
      if (active !== null || (event.pointerType === 'mouse' && event.button !== 0)) return;
      active = event.pointerId;
      try { canvas.setPointerCapture?.(event.pointerId); } catch (_) {}
      start?.(event);
    };
    const motion = event => { if (active === event.pointerId) move?.(event); };
    const finish = (event, cancelled = false) => {
      if (active !== event.pointerId) return;
      active = null;
      try { if (canvas.hasPointerCapture?.(event.pointerId)) canvas.releasePointerCapture(event.pointerId); } catch (_) {}
      (cancelled ? (cancel || end) : end)?.(event);
    };
    if (global.PointerEvent) {
      canvas.addEventListener('pointerdown', down);
      canvas.addEventListener('pointermove', motion);
      canvas.addEventListener('pointerup', event => finish(event));
      canvas.addEventListener('pointercancel', event => finish(event, true));
    } else {
      let mouse = false;
      canvas.addEventListener('mousedown', event => { mouse = true; start?.(event); });
      global.addEventListener('mousemove', event => { if (mouse) move?.(event); });
      global.addEventListener('mouseup', event => { if (mouse) { mouse = false; end?.(event); } });
      const touchEvent = event => ({ ...event, clientX: event.changedTouches?.[0]?.clientX,
        clientY: event.changedTouches?.[0]?.clientY, preventDefault: () => event.preventDefault() });
      canvas.addEventListener('touchstart', event => { start?.(touchEvent(event)); event.preventDefault(); }, { passive: false });
      canvas.addEventListener('touchmove', event => { move?.(touchEvent(event)); event.preventDefault(); }, { passive: false });
      canvas.addEventListener('touchend', event => end?.(touchEvent(event)), { passive: false });
      canvas.addEventListener('touchcancel', event => (cancel || end)?.(touchEvent(event)), { passive: false });
    }
    canvas.dataset.signaturePointerBound = '1';
  };

  const observeResize = (canvas, options = {}) => {
    const resize = () => resizeCanvas(canvas, options);
    if (global.ResizeObserver) {
      const observer = new ResizeObserver(resize);
      observer.observe(canvas);
      return () => observer.disconnect();
    }
    global.addEventListener('resize', resize);
    return () => global.removeEventListener('resize', resize);
  };

  const decorate = (root = document) => {
    const device = capabilities();
    document.documentElement.dataset.signatureInput = device.mode;
    document.documentElement.dataset.signatureCompact = device.compact ? 'true' : 'false';
    const choose = selector => root.querySelectorAll(selector);
    choose('.mx-signature-pad__canvas, #fd_signature_canvas, [data-mmc-canvas]').forEach(canvas => {
      canvas.style.touchAction = 'none';
      const pad = canvas.closest('.mx-signature-pad') || canvas.parentElement;
      if (!pad || canvas.dataset.signatureDecorated === '1') return;
      canvas.dataset.signatureDecorated = '1';
      if (!canvas.getAttribute('aria-label')) canvas.setAttribute('aria-label', 'Área para dibujar la firma');
      canvas.setAttribute('aria-describedby', `${canvas.id || 'signature'}-instructions`);
      const title = document.createElement('strong');
      title.className = 'mx-signature-direct-label';
      title.textContent = copy.direct;
      pad.insertBefore(title, canvas);
      const instruction = document.createElement('p');
      instruction.id = `${canvas.id || 'signature'}-instructions`;
      instruction.className = 'mx-signature-instruction';
      instruction.textContent = copy.instruction;
      pad.insertBefore(instruction, canvas);
    });
    const remoteSelectors = '[data-action="ci-signature-open-qr"], [data-action="ci-doctor-signature-open-qr"], #im_signature_qr_open, #ix_signature_qr_open, #nm_signature_qr_open, #am_signature_qr_open, #rm_signer_signature_qr, #rm_doctor_signature_qr, #cm_signature_qr_open, #ne_rx_remote_signature, [data-fd-open-qr], [data-mmc-qr]';
    choose(remoteSelectors).forEach(button => { button.textContent = copy.remote; button.classList.add('mx-signature-remote'); });
    choose('[data-mmc-draw], #ne_rx_apply_signature').forEach(button => { button.textContent = copy.direct; button.classList.add('mx-signature-direct'); });
    choose('[data-mmc-registered], [data-fd-use-registered], #ne_rx_registered_signature').forEach(button => { button.textContent = copy.registered; button.classList.add('mx-signature-registered'); });
    choose('[data-mmc-clear], [data-fd-clear-sign], #ne_rx_clear_signature').forEach(button => { button.textContent = copy.clear; });
    choose('.mx-signature-pad + .d-flex button, .mx-signature-pad ~ .d-flex button').forEach(button => {
      if (/^Volver a firmar$/i.test(button.textContent.trim())) button.textContent = copy.replace;
    });
    choose('[id$="_signature_status"], [data-mmc-signature-status], [data-fd-sign-status]').forEach(node => {
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
    });
  };

  global.addEventListener('resize', () => {
    const device = capabilities();
    document.documentElement.dataset.signatureInput = device.mode;
    document.documentElement.dataset.signatureCompact = device.compact ? 'true' : 'false';
  });

  global.mxmedSignatureExperience = Object.freeze({ copy, capabilities, resizeCanvas, observeResize, point, bindPointer, decorate });
})(window);
