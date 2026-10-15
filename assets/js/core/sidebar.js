/* Original VIS25 sidebar: one implementation, initialized in its original app position. */
(() => {
  let started=false;
  window.mxmedInitializeSidebar=()=>{if(started)return;started=true;
// VIS25: explicit pin preference plus transient hover/focus expansion.
(function(){
  const grid = document.querySelector('.mm-grid.page') || document.querySelector('.mm-grid');
  const sidebar = document.getElementById('mmSidebar');
  const body = document.body;
  if(!grid || !sidebar || !body) return;

  const STORAGE_KEY = 'mxmed.sidebar.state';
  const LEGACY_STORAGE_KEY = 'mxmed.shell.sidebar.expanded';
  const sidebarToggleBtn = sidebar.querySelector('[data-action="sidebar-toggle"]');
  const headerToggleBtn = document.querySelector('[data-action="sidebar-toggle-proxy"]');
  const toggleButtons = [sidebarToggleBtn, headerToggleBtn].filter(Boolean);
  const PUSH_MIN_VIEWPORT_PX = 1340;
  const desktopQuery = window.matchMedia(`(min-width: ${PUSH_MIN_VIEWPORT_PX}px)`);
  const hoverQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
  const OPEN_DELAY_MS = 175;
  const CLOSE_DELAY_MS = 300;
  let pinned = false;
  let temporary = false;
  let mobileOpen = false;
  let mobileGroupNavigation = false;
  let keyboardFocus = false;
  let openTimer = null;
  let closeTimer = null;
  let expandedScrollTop = 0;
  let resizeEmitTimer = null;

  const isDesktop = ()=> desktopQuery.matches && hoverQuery.matches;
  const canHover = ()=> isDesktop();
  const isExpanded = ()=> pinned || temporary;
  const clearTimers = ()=>{
    window.clearTimeout(openTimer);
    window.clearTimeout(closeTimer);
    openTimer = closeTimer = null;
  };
  const normalizedState = (raw)=>{
    const value = String(raw || '').trim().toLowerCase();
    return value === 'expanded' ? 'expanded' : 'collapsed';
  };
  const readPersistedState = ()=>{
    try{
      const raw = String(window.localStorage?.getItem(STORAGE_KEY) || '').trim();
      if(raw){
        return normalizedState(raw);
      }
      const legacy = String(window.localStorage?.getItem(LEGACY_STORAGE_KEY) || '').trim().toLowerCase();
      if(legacy === '1' || legacy === 'true'){
        return 'expanded';
      }
      if(legacy === '0' || legacy === 'false'){
        return 'collapsed';
      }
    }catch(_){}
    return 'collapsed';
  };
  const persistState = (nextState)=>{
    try{
      window.localStorage?.setItem(STORAGE_KEY, nextState);
      window.localStorage?.setItem(LEGACY_STORAGE_KEY, nextState === 'expanded' ? '1' : '0');
    }catch(_){}
  };
  const updateNavTooltips = ()=>{
    sidebar.querySelectorAll('.menu-main, .menu-sub-btn').forEach((node)=>{
      if(!node) return;
      const currentTitle = String(node.getAttribute('data-label-title') || '').trim();
      if(!currentTitle){
        const derived = String(
          node.querySelector('.ttl')?.textContent
          || node.querySelector('.l1')?.textContent
          || node.textContent
          || ''
        ).trim();
        if(derived){
          node.setAttribute('data-label-title', derived);
        }
      }
      const label = String(node.getAttribute('data-label-title') || '').trim();
      if(!label) return;
      // SB01 owns the visual tooltip; native titles race with hover expansion.
      node.removeAttribute('title');
    });
  };
  const emitSidebarToggle = (layoutChanged = true)=>{
    if(resizeEmitTimer){
      window.clearTimeout(resizeEmitTimer);
    }
    resizeEmitTimer = window.setTimeout(()=>{
      window.dispatchEvent(new CustomEvent('mxmed:sidebar-toggled', {
        detail: {
          state: isExpanded() ? 'expanded' : 'collapsed',
          expanded: isExpanded(),
          pinned,
          layoutChanged
        }
      }));
    }, 120);
  };
  const syncClasses = ()=>{
    const expanded = isExpanded();
    const collapsed = !expanded;
    if(!isDesktop()){
      grid.classList.remove('is-sidebar-collapsed', 'is-sidebar-expanded');
      body.classList.remove('mx-sidebar-collapsed', 'mx-sidebar-expanded', 'mx-sidebar-temporary');
      body.classList.toggle('vis25-mobile-open', mobileOpen);
      toggleButtons.forEach((btn)=>{
        btn.setAttribute('aria-expanded', String(mobileOpen));
        btn.setAttribute('aria-label', mobileOpen ? 'Cerrar menú lateral' : 'Abrir menú lateral');
      });
      updateNavTooltips();
      return;
    }
    grid.classList.toggle('is-sidebar-expanded', expanded);
    grid.classList.toggle('is-sidebar-collapsed', collapsed);
    body.classList.toggle('mx-sidebar-expanded', expanded);
    body.classList.toggle('mx-sidebar-collapsed', collapsed);
    body.classList.toggle('mx-sidebar-temporary', temporary && !pinned);
    body.classList.remove('vis25-mobile-open');
    toggleButtons.forEach((btn)=>{
      btn.setAttribute('aria-expanded', String(pinned));
      btn.setAttribute('aria-pressed', String(pinned));
      btn.setAttribute('aria-label', pinned ? 'Contraer menú lateral' : 'Fijar menú lateral abierto');
      btn.setAttribute('title', pinned ? 'Contraer menú lateral' : 'Fijar menú lateral abierto');
    });
    updateNavTooltips();
  };
  const setPinned = (next, { persist = true, silent = false } = {})=>{
    const changed = !!next !== pinned || temporary;
    if(isExpanded()) expandedScrollTop = sidebar.scrollTop;
    pinned = !!next;
    temporary = false;
    clearTimers();
    syncClasses();
    if(isExpanded()) sidebar.scrollTop = expandedScrollTop;
    if(persist){
      persistState(pinned ? 'expanded' : 'collapsed');
    }
    if(!silent && changed){
      emitSidebarToggle();
    }
  };

  const setTemporary = (next)=>{
    if(!isDesktop() || pinned || temporary === !!next) return;
    if(temporary) expandedScrollTop = sidebar.scrollTop;
    temporary = !!next;
    syncClasses();
    if(temporary) sidebar.scrollTop = expandedScrollTop;
    emitSidebarToggle();
  };
  setPinned(readPersistedState() === 'expanded', { persist: false, silent: true });

  document.addEventListener('pointerdown', ()=>{ keyboardFocus = false; });
  document.addEventListener('keydown', event=>{
    if(event.key !== 'Escape') keyboardFocus = true;
  });

  sidebar.addEventListener('pointerenter', (event)=>{
    if(!canHover() || event.pointerType === 'touch' || pinned) return;
    window.clearTimeout(closeTimer);
    closeTimer = null;
    if(!temporary && !openTimer) openTimer = window.setTimeout(()=>{
      openTimer = null;
      if(sidebar.matches(':hover')) setTemporary(true);
    }, OPEN_DELAY_MS);
  });
  sidebar.addEventListener('pointerleave', (event)=>{
    if(!canHover() || event.pointerType === 'touch' || pinned) return;
    window.clearTimeout(openTimer);
    openTimer = null;
    window.clearTimeout(closeTimer);
    closeTimer = window.setTimeout(()=>{
      closeTimer = null;
      if(!(keyboardFocus && sidebar.contains(document.activeElement))) setTemporary(false);
    }, CLOSE_DELAY_MS);
  });
  sidebar.addEventListener('focusin', ()=>{
    if(!isDesktop() || pinned) return;
    clearTimers();
    setTemporary(true);
  });
  sidebar.addEventListener('focusout', ()=>window.setTimeout(()=>{
    if(!pinned && !sidebar.contains(document.activeElement) && !sidebar.matches(':hover')) setTemporary(false);
  }, 0));
  sidebar.addEventListener('click', event=>{
    if(!isDesktop() && event.target.closest('.menu-main[data-group]')) mobileGroupNavigation = true;
    else if(!isDesktop() && event.target.closest('[data-panel]')){
      mobileGroupNavigation = false;
      mobileOpen = false;
      syncClasses();
    }
  });

  toggleButtons.forEach((btn)=>{
    btn.addEventListener('click', (event)=>{
      event.preventDefault();
      event.stopPropagation();
      if(isDesktop()) setPinned(!pinned);
      else {
        mobileOpen = !mobileOpen;
        mobileGroupNavigation = false;
        syncClasses();
        if(mobileOpen) sidebar.querySelector('.menu-main:not(.d-none)')?.focus();
      }
    });
  });

  document.addEventListener('click', (event)=>{
    if(!isDesktop()){
      if(mobileOpen && !sidebar.contains(event.target) && !event.target.closest('[data-action="sidebar-toggle-proxy"]')){
        mobileOpen = false;
        mobileGroupNavigation = false;
        syncClasses();
      }
      return;
    }
    if(!temporary) return;
    if(sidebar.contains(event.target)) return;
    setTemporary(false);
  });

  // SB01: destination clicks preserve the user's compact preference.
  // Child navigation is presented by navigation.js in the existing submenu.

  document.addEventListener('keydown', (event)=>{
    if(event.key === 'Escape'){
      if(!isDesktop() && mobileOpen){ mobileOpen = false; mobileGroupNavigation = false; syncClasses(); headerToggleBtn?.focus(); }
      else if(isDesktop() && temporary){ setTemporary(false); headerToggleBtn?.focus(); }
    }
  });

  const onViewportChange = ()=>{
    clearTimers();
    temporary = false;
    mobileOpen = false;
    mobileGroupNavigation = false;
    syncClasses();
  };
  window.addEventListener('mxmed:workspace-mode', ()=>{
    if(!isDesktop() && mobileOpen && !mobileGroupNavigation){
      mobileOpen = false;
      syncClasses();
    }
  });
  if(typeof desktopQuery.addEventListener === 'function'){
    desktopQuery.addEventListener('change', onViewportChange);
  }else if(typeof desktopQuery.addListener === 'function'){
    desktopQuery.addListener(onViewportChange);
  }
  hoverQuery.addEventListener?.('change', onViewportChange);
})();


  };
})();
