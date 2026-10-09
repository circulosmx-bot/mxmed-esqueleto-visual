/* DOC-CORE03: shared presentation and timing only. Clinical authority stays in each document flow. */
(function (global) {
  'use strict';

  const focusStatus = (node) => {
    if (!node || !node.getClientRects().length) return;
    if (!node.hasAttribute('tabindex')) node.setAttribute('tabindex', '-1');
    node.focus({ preventScroll: true });
    node.scrollIntoView({ block: 'nearest' });
  };

  const review = ({ content, continueButton, state, html = '', message = '', retry = null, focus = true }) => {
    if (!content) return;
    content.dataset.documentReviewState = state;
    content.setAttribute('role', state === 'error' ? 'alert' : 'status');
    content.setAttribute('aria-live', 'polite');
    content.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    if (continueButton) continueButton.disabled = state !== 'ready';
    if (state === 'ready') content.innerHTML = html;
    else {
      content.textContent = message;
      if (state === 'error' && typeof retry === 'function') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary btn-sm mt-2 d-block';
        button.textContent = 'Reintentar';
        button.addEventListener('click', retry, { once: true });
        content.append(button);
      }
    }
    if (focus && (state === 'ready' || state === 'error')) requestAnimationFrame(() => focusStatus(content));
  };

  const signatureState = ({ status, hasSignature = false, replacing = false }) => {
    if (replacing) return 'pending';
    if (status === 'valid_bound_signature') return 'valid';
    if (status === 'legacy_unbound' || status === 'legacy_unverified_binding') return 'legacy';
    if (hasSignature && (status === 'stale_or_unverified' || status === 'stale_or_unverified_signature')) return 'stale';
    return hasSignature ? 'pending' : 'empty';
  };

  const signature = ({ block, statusEl, image, canvas, cancel, imageData = '', status = '',
    hasSignature = false, replacing = false, text = '', manageSurface = false }) => {
    const visual = signatureState({ status, hasSignature, replacing });
    if (block) block.dataset.documentSignatureState = visual;
    if (statusEl) {
      if (text) statusEl.textContent = text;
      statusEl.dataset.documentSignatureState = visual;
      statusEl.classList.toggle('text-success', !!hasSignature && !replacing && status === 'valid_bound_signature');
      statusEl.classList.toggle('text-warning', !!hasSignature && (replacing || status !== 'valid_bound_signature'));
    }
    if (manageSurface) {
      const visible = !replacing && /^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(imageData);
      if (image) {
        if (visible && image.src !== imageData) image.src = imageData;
        if (!visible) image.removeAttribute('src');
        image.classList.toggle('d-none', !visible);
        image.classList.toggle('is-stale', visible && visual !== 'valid');
      }
      canvas?.classList.toggle('d-none', visible);
      cancel?.classList.toggle('d-none', !replacing);
    }
    return visual;
  };

  const createPoller = ({ poll, intervalMs }) => {
    let timer = 0;
    let generation = 0;
    return {
      start() {
        this.stop();
        const current = generation;
        timer = global.setInterval(() => {
          if (current === generation) void poll();
        }, intervalMs);
      },
      stop() {
        generation += 1;
        if (timer) global.clearInterval(timer);
        timer = 0;
      },
      get active() { return !!timer; }
    };
  };

  const handoff = ({ modal, adapter, waiting, received, status, receivedHeading, preview }) => {
    for (const method of ['createSession', 'getStatus', 'receive', 'mapTerminal']) {
      if (typeof adapter?.[method] !== 'function') throw new Error(`Document handoff adapter requires ${method}`);
    }
    return {
      adapter,
      phase(phase, { message = '', imageData = '', signer = '' } = {}) {
        const done = phase === 'received';
        waiting?.classList.toggle('d-none', done);
        received?.classList.toggle('d-none', !done);
        if (status) {
          status.textContent = message;
          status.dataset.documentHandoffState = phase;
          status.classList.toggle('visually-hidden', done);
        }
        if (done) {
          if (receivedHeading) receivedHeading.textContent = '✓ Firma recibida correctamente';
          if (preview && imageData) preview.src = imageData;
          const signerNode = received?.querySelector('[data-document-handoff-signer]');
          if (signerNode) signerNode.textContent = signer;
          requestAnimationFrame(() => focusStatus(receivedHeading || status));
        }
      },
      createPoller(poll, intervalMs) {
        const controller = createPoller({ poll, intervalMs });
        modal?.addEventListener('hidden.bs.modal', () => controller.stop());
        return controller;
      }
    };
  };

  global.mxmedDocumentUiPrimitives = Object.freeze({ review, signature, signatureState, handoff, createPoller });
})(window);
