(() => {
  const drop = document.getElementById('fotos-drop');
  const grid = document.getElementById('fotos-grid');
  const input = document.getElementById('fotos-input');
  const count = document.getElementById('fotos-count');
  const message = document.getElementById('fotos-msg');
  const orderToolbar = document.getElementById('fotos-order-toolbar');
  const orderActions = orderToolbar?.querySelector('.fotos-order-actions');
  const orderSave = document.getElementById('fotos-order-save');
  const orderReset = document.getElementById('fotos-order-reset');
  const orderMessage = document.getElementById('fotos-order-msg');
  if (!drop || !grid || !input || !count) return;

  const endpoint = '/api/media/gallery.php';
  const TOUCH_DRAG_DELAY_MS = 250;
  const TOUCH_DRAG_MOVEMENT_THRESHOLD = 10;
  let csrf = '';
  let busy = false;
  let savedOrder = [];
  let pointerOrder = null;
  let touchOrder = null;
  input.accept = 'image/jpeg,image/png,image/webp';
  message?.setAttribute('role', 'status');

  const notify = text => {
    if (!message) return;
    message.textContent = text;
    message.classList.toggle('show', Boolean(text));
  };
  const orderNotify = (text, error = false) => {
    if (!orderMessage) return;
    orderMessage.textContent = text;
    orderMessage.classList.toggle('is-error', error);
  };
  const publicCards = () => Array.from(grid.querySelectorAll(':scope > .foto-item[data-gallery-media-id]'));
  const currentOrder = () => publicCards().map(card => card.dataset.galleryMediaId);
  const sameOrder = (left, right) => left.length === right.length && left.every((id, index) => id === right[index]);
  const firstCandidate = () => grid.querySelector(':scope > [data-review-candidate]');

  const updateOrderState = (announce = '') => {
    const cards = publicCards();
    cards.forEach((card, index) => {
      const position = index + 1;
      const badge = card.querySelector('.foto-order-position');
      card.setAttribute('aria-label', `Fotografía ${position} de ${cards.length}. Posición ${position}. Usa las flechas para reordenarla.`);
      if (badge) badge.textContent = String(position);
      if (badge) badge.hidden = cards.length < 2;
    });
    const dirty = !sameOrder(currentOrder(), savedOrder);
    if (orderToolbar) orderToolbar.hidden = cards.length < 2 || !dirty;
    if (orderActions) orderActions.hidden = !dirty;
    if (orderSave) orderSave.disabled = !dirty || busy;
    if (orderReset) orderReset.disabled = !dirty || busy;
    if (announce) orderNotify(announce);
    else if (!dirty) orderNotify('');
    grid.classList.toggle('is-order-dirty', dirty);
    return dirty;
  };

  const placeCards = cards => {
    const anchor = firstCandidate();
    cards.forEach(card => grid.insertBefore(card, anchor));
  };

  const moveCard = (card, targetIndex, announce = true) => {
    const cards = publicCards();
    const from = cards.indexOf(card);
    if (from < 0) return false;
    const to = Math.max(0, Math.min(cards.length - 1, targetIndex));
    if (from === to) return false;
    cards.splice(from, 1);
    cards.splice(to, 0, card);
    placeCards(cards);
    updateOrderState(announce ? `Fotografía movida a la posición ${to + 1}. Guarda el orden para publicarlo.` : '');
    return true;
  };

  const columnCount = () => {
    const cards = publicCards();
    if (cards.length < 2) return 1;
    const top = Math.round(cards[0].getBoundingClientRect().top);
    return Math.max(1, cards.filter(card => Math.round(card.getBoundingClientRect().top) === top).length);
  };

  const render = images => {
    // Refresh public thumbnails without discarding inline review candidates.
    grid.querySelectorAll(':scope > .foto-item:not([data-review-candidate])').forEach(item => item.remove());
    const publicItems = document.createDocumentFragment();
    count.textContent = images.length;
    drop.classList.toggle('has-items', images.length > 0);
    document.getElementById('t-info-fotos')?.classList.toggle('has-items', images.length > 0);
    count.parentElement?.classList.toggle('max', images.length >= 16);
    images.forEach((asset, index) => {
      const wrap = document.createElement('div');
      wrap.className = 'foto-item';
      wrap.dataset.galleryMediaId = asset.media_id;
      wrap.tabIndex = 0;
      wrap.setAttribute('role', 'group');
      wrap.setAttribute('aria-roledescription', 'fotografía reordenable');
      wrap.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight ArrowUp ArrowDown Home End');

      const img = document.createElement('img');
      img.src = asset.public_url;
      img.alt = asset.alt_text || '';
      img.draggable = false;

      const position = document.createElement('span');
      position.className = 'foto-order-position';
      position.textContent = String(index + 1);
      position.setAttribute('aria-hidden', 'true');

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'foto-x';
      remove.textContent = '×';
      remove.setAttribute('aria-label', 'Eliminar imagen');
      remove.addEventListener('click', () => run(() => request('DELETE', null, asset.media_id)));
      wrap.append(img, position, remove);
      publicItems.append(wrap);
    });
    grid.prepend(publicItems);
    savedOrder = images.map(asset => asset.media_id);
    updateOrderState();
  };

  async function request(method = 'GET', body = null, id = '') {
    const headers = method === 'GET' ? {} : {'X-Gallery-CSRF': csrf};
    if (method === 'PATCH') headers['Content-Type'] = 'application/json';
    const response = await fetch(endpoint + (id ? '?media_id=' + encodeURIComponent(id) : ''), {
      method,
      body,
      credentials: 'same-origin',
      headers,
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw Error(result.message || 'No se pudieron cargar las fotos.');
    csrf = result.data.csrf_token;
    render(result.data.images);
  }

  async function run(action) {
    if (busy) return;
    busy = true;
    drop.setAttribute('aria-busy', 'true');
    notify('');
    updateOrderState();
    try {
      await action();
    } catch (error) {
      notify(error.message);
    } finally {
      busy = false;
      drop.removeAttribute('aria-busy');
      input.value = '';
      updateOrderState();
    }
  }

  const upload = files => run(async () => {
    if (!csrf) await request();
    for (const file of files) await window.mxmedMediaReview.upload('gallery', file);
    notify('Pendiente de enviar');
  });

  const saveOrder = async () => {
    if (busy || sameOrder(currentOrder(), savedOrder)) return;
    busy = true;
    orderToolbar?.setAttribute('aria-busy', 'true');
    orderNotify('Guardando orden…');
    updateOrderState();
    try {
      await request('PATCH', JSON.stringify({media_ids: currentOrder()}));
      notify('Orden guardado. Así se mostrará en tu perfil público.');
    } catch (error) {
      orderNotify(error.message, true);
    } finally {
      busy = false;
      orderToolbar?.removeAttribute('aria-busy');
      updateOrderState();
    }
  };

  orderSave?.addEventListener('click', saveOrder);
  orderReset?.addEventListener('click', () => {
    const byId = new Map(publicCards().map(card => [card.dataset.galleryMediaId, card]));
    placeCards(savedOrder.map(id => byId.get(id)).filter(Boolean));
    updateOrderState();
  });

  grid.addEventListener('keydown', event => {
    const card = event.target.closest('.foto-item[data-gallery-media-id]');
    if (!card || event.target !== card || busy) return;
    const cards = publicCards();
    const index = cards.indexOf(card);
    const columns = columnCount();
    const target = ({ArrowLeft: index - 1, ArrowRight: index + 1, ArrowUp: index - columns, ArrowDown: index + columns, Home: 0, End: cards.length - 1})[event.key];
    if (target === undefined) return;
    event.preventDefault();
    if (moveCard(card, target)) card.focus();
  });

  grid.addEventListener('pointerdown', event => {
    if (busy || event.pointerType === 'touch' || (event.pointerType === 'mouse' && event.button !== 0)) return;
    if (event.target.closest('button,a,input,select,textarea,[role="button"]')) return;
    const card = event.target.closest('.foto-item[data-gallery-media-id]');
    if (!card) return;
    event.preventDefault();
    card.focus({preventScroll: true});
    card.setPointerCapture(event.pointerId);
    pointerOrder = {card, pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, moved: false};
  });

  grid.addEventListener('pointermove', event => {
    if (!pointerOrder || pointerOrder.pointerId !== event.pointerId) return;
    if (!pointerOrder.moved && Math.hypot(event.clientX - pointerOrder.startX, event.clientY - pointerOrder.startY) < 6) return;
    pointerOrder.moved = true;
    pointerOrder.card.classList.add('is-ordering');
    const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-gallery-media-id]');
    if (!target || target === pointerOrder.card || target.parentElement !== grid) return;
    const cards = publicCards();
    const from = cards.indexOf(pointerOrder.card);
    let to = cards.indexOf(target);
    const rect = target.getBoundingClientRect();
    const after = event.clientY > rect.top + rect.height / 2 ||
      (Math.abs(event.clientY - (rect.top + rect.height / 2)) < rect.height / 3 && event.clientX > rect.left + rect.width / 2);
    if (after) to += 1;
    if (from < to) to -= 1;
    moveCard(pointerOrder.card, to, false);
  });

  const finishPointerOrder = event => {
    if (!pointerOrder || pointerOrder.pointerId !== event.pointerId) return;
    const {card, moved} = pointerOrder;
    card.classList.remove('is-ordering');
    pointerOrder = null;
    if (moved) {
      const position = publicCards().indexOf(card) + 1;
      updateOrderState(`Fotografía movida a la posición ${position}. Guarda el orden para publicarlo.`);
    }
  };
  grid.addEventListener('pointerup', finishPointerOrder);
  grid.addEventListener('pointercancel', finishPointerOrder);

  const touchByIdentifier = (touches, identifier) => Array.from(touches).find(touch => touch.identifier === identifier);
  const clearTouchOrder = () => {
    if (!touchOrder) return;
    clearTimeout(touchOrder.timer);
    touchOrder.card.classList.remove('is-ordering');
    grid.classList.remove('is-touch-ordering');
    touchOrder = null;
  };
  const finishTouchOrder = event => {
    if (!touchOrder) return;
    const {card, active} = touchOrder;
    if (active) {
      event.preventDefault();
      const position = publicCards().indexOf(card) + 1;
      updateOrderState(`Fotografía movida a la posición ${position}. Guarda el orden para publicarlo.`);
    }
    clearTouchOrder();
  };

  grid.addEventListener('touchstart', event => {
    if (busy || touchOrder || event.touches.length !== 1 || event.target.closest('button,a,input,select,textarea,[role="button"]')) return;
    const card = event.target.closest('.foto-item[data-gallery-media-id]');
    const touch = event.touches[0];
    if (!card || !touch) return;
    touchOrder = {
      card,
      identifier: touch.identifier,
      startX: touch.clientX,
      startY: touch.clientY,
      active: false,
      timer: setTimeout(() => {
        if (!touchOrder || touchOrder.card !== card) return;
        touchOrder.active = true;
        card.classList.add('is-ordering');
        grid.classList.add('is-touch-ordering');
        card.focus({preventScroll: true});
      }, TOUCH_DRAG_DELAY_MS),
    };
  }, {passive: true});

  grid.addEventListener('touchmove', event => {
    if (!touchOrder) return;
    const touch = touchByIdentifier(event.touches, touchOrder.identifier);
    if (!touch) return;
    const distance = Math.hypot(touch.clientX - touchOrder.startX, touch.clientY - touchOrder.startY);
    if (!touchOrder.active) {
      if (distance > TOUCH_DRAG_MOVEMENT_THRESHOLD) clearTouchOrder();
      return;
    }
    event.preventDefault();
    const target = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('.foto-item[data-gallery-media-id]');
    if (!target || target === touchOrder.card || target.parentElement !== grid) return;
    const cards = publicCards();
    const from = cards.indexOf(touchOrder.card);
    let to = cards.indexOf(target);
    const rect = target.getBoundingClientRect();
    const after = touch.clientY > rect.top + rect.height / 2 ||
      (Math.abs(touch.clientY - (rect.top + rect.height / 2)) < rect.height / 3 && touch.clientX > rect.left + rect.width / 2);
    if (after) to += 1;
    if (from < to) to -= 1;
    moveCard(touchOrder.card, to, false);
  }, {passive: false});

  grid.addEventListener('touchend', finishTouchOrder, {passive: false});
  grid.addEventListener('touchcancel', finishTouchOrder, {passive: false});

  drop.addEventListener('click', event => {
    if (event.target.closest('.fotos-browse') && !busy) input.click();
  });
  input.addEventListener('change', () => upload(Array.from(input.files || [])));
  drop.addEventListener('dragover', event => {
    event.preventDefault();
    drop.classList.add('dragover');
  });
  drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
  drop.addEventListener('drop', event => {
    event.preventDefault();
    drop.classList.remove('dragover');
    upload(Array.from(event.dataTransfer?.files || []));
  });
  document.getElementById('t-info-fotos-tab')?.addEventListener('shown.bs.tab', () => run(() => request()));
  run(() => request());
})();
