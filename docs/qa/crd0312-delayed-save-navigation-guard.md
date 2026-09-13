# CRD03.12 — Delayed explicit save and scoped navigation guard

Datos Generales detects dirty state immediately and reveals the existing plain-text warning and green save button after 2,000 ms of relevant editing inactivity. Subsequent edits reset the pending timer; scrolling/mouse movement do not. A revealed reminder remains stable until clean. Returning to baseline cancels it without focus or scroll changes. Desktop margins are 32 px; mobile retains 16 px and safe-area support. Content padding is reserved only while the reminder is visible.

The opt-in `mxmedCreateExplicitSaveSurface` lifecycle accepts dirty/active/saving callbacks, existing save, discard, rendering and dialog adapters. It is registered only for the current profile explicit-save fields. It does not replace the dirty tracker, router or grouped PATCH contract.

## Navigation authority audit

- Información uses Bootstrap pills; outgoing Formación, Servicios, Enfermedades and Fotos are guarded, including the public Tab API.
- Sidebar destinations use `data-panel`. Agenda's group opens its first child when entering it. These outgoing controls are captured and replayed unchanged after save/discard.
- Opening the current Mi Perfil flyout, collapsing a group and non-navigation dropdowns do not require confirmation.
- The Header logo changes to Inicio and is guarded. Session logout leaves the editor and is narrowly guarded without altering the logout implementation.
- “Ver perfil público” has `target="_blank"` and preserves the editor; it remains unblocked, as do verified-data/help modals and media actions.
- The existing new-patient beforeunload guard remains untouched. The new native protection is registered only for this surface's unsaved fields; no unload autosave occurs.

## Modal lifecycle

The modal uses the exact approved title, body and ordered actions: Guardar y continuar / Salir sin guardar / Seguir editando. Save awaits the existing transaction and a clean baseline; error or newer unsaved edits block navigation and retain the intended destination. Duplicate actions are disabled while working. Discard restores the persisted form baseline without any PATCH, then returns through server hydration on re-entry. Continue editing/Escape preserves edits, restores focus and reveals the reminder without a second delay. Pending reminder timers are cancelled while the guard is open. Only one modal/intent exists.

## Validation

- `ExplicitSaveSurfaceTest.mjs`: controlled clock at 0/1999/2000 ms, timer reset, revert cancellation, stable visibility, scope, original intent, discard without save, failed save/retry, duplicate/close locking and dirty-only unload decisions.
- `ExplicitSaveNavigationBrowserTest.mjs`: real Leticia reads with controlled surface clock; immediate dirty, typing and unchanged-input debounce, unrelated activity, no passive focus/scroll change, outgoing tabs/Sidebar/Header/session exit, non-navigation exemptions, keyboard/Escape, exact destination, discard/no PATCH, error/retry/duplicate lock, native reload cancellation and clean unload, all three viewports.
- Explicitly gated real theme save-and-continue against port 18143 passed. Leticia's original theme was restored through existing controls; the original timestamp was restored with a conditional local QA update. All 14 table counts/hashes and full private profile data match the initial snapshot exactly. No permanent QA mutation remains.
- Existing grouped-save browser, real-runtime hydration/revert, dirty-tracker, inline-media and profile-utility regressions passed. The grouped payload and name-policy/hydration code are unchanged, as is all application code outside the profile bridge. Bio 150, theme authority, admin contacts, media, Header/Sidebar implementations and credentials remain unchanged. Administrative-contact migration and SIG01 remain deferred.
- Screenshots stay in `/tmp/mxmed-crd0312/`; none are committed. Desktop 1440×900 and 1366×768 have 32 px right/bottom margins; 390×844 has 16 px margins. No horizontal overflow or browser errors introduced.

Review: http://127.0.0.1:18143/index.html?review=crd0312&qa_tools=hide
