<?php if (!isset($privateAccess, $operatorPanel) || !$privateAccess->allowed) { http_response_code(404); exit; } ?>
<section class="mm-card<?= $operatorPanel['service_ready'] && $operatorPanel['can_manage'] ? '' : ' d-none' ?>" id="p-ag-operadores">

          <div class="head"><h5><span class="material-symbols-rounded title-ico" aria-hidden="true">support_agent</span>Mi Agenda · Administrar Operadores</h5></div>

          <div class="body">
            <div class="mx-ag-workspace-shell mx-ag-workspace-shell--agenda" aria-label="Submenú operativo de agenda">
              <button type="button" class="mx-ag-workspace-btn" data-ag-work-panel="p-ag-admin">
                <span class="material-symbols-rounded" aria-hidden="true">event</span>
                Agenda semanal
              </button>
              <button type="button" class="mx-ag-workspace-btn" data-ag-work-action="next-available">
                <span class="material-symbols-rounded" aria-hidden="true">calendar_clock</span>
                Buscar cita
              </button>
                            <?php if ($operatorPanel['can_configure_agenda']): ?><button type="button" class="mx-ag-workspace-btn" data-ag-work-panel="p-ag-ajustes"><span class="material-symbols-rounded" aria-hidden="true">settings</span>Configuración</button><?php endif; ?>
              <button type="button" class="mx-ag-workspace-btn is-active" data-ag-work-panel="p-ag-operadores">
                <span class="material-symbols-rounded" aria-hidden="true">support_agent</span>
                Operadores
              </button>
            </div>
            <div class="mx-ag-ops-shell" id="ag_ops_shell">
              <?php if (!$operatorPanel['service_ready']): ?>
              <div class="alert alert-info" id="ag_ops_readiness" role="status">
                <strong>Invitaciones canónicas no disponibles</strong>
                <p class="mb-1"><?= $html($operatorPanel['message']) ?></p>
                <p class="mb-0"><?= $html($operatorPanel['access_message']) ?></p>
              </div>
              <?php endif; ?>
              <div class="mx-ag-ops-layout" id="ag_ops_layout">
                <section class="mx-ag-ops-main-panel" aria-labelledby="ag_ops_list_title">
                  <header class="mx-ag-ops-main-head">
                    <div>
                      <h6 id="ag_ops_list_title">Operadores registrados</h6>
                      <p>Administra aquí los operadores con acceso a tu consultorio.</p>
                    </div>
                    <button type="button" class="btn btn-primary mx-ag-ops-add-btn" id="ag_ops_add_btn" <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?>>
                      <span class="material-symbols-rounded" aria-hidden="true">person_add</span>
                      Agregar operador
                    </button>
                  </header>

                  <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="ag_ops_refresh" <?= !$operatorPanel['service_ready'] ? 'disabled title="Servicio canónico no disponible"' : '' ?>>Actualizar equipo</button>                  <div id="ag_ops_legacy_summary" class="form-text mb-2" aria-live="polite"></div>
                  <div class="mx-ag-ops-list" id="ag_ops_list" role="list"></div>

                  <section class="mx-ag-ops-archived" id="ag_ops_archived_section" aria-labelledby="ag_ops_archived_title">
                    <button type="button" class="mx-ag-ops-archived-toggle" id="ag_ops_archived_toggle" aria-expanded="false" aria-controls="ag_ops_archived_panel">
                      <span class="mx-ag-ops-archived-link">
                        <strong id="ag_ops_archived_title">Operadores archivados</strong>
                        <span class="mx-ag-ops-archived-count" id="ag_ops_archived_count">No disponible</span>
                      </span>
                      <span class="material-symbols-rounded mx-ag-ops-archived-chevron" aria-hidden="true">expand_more</span>
                    </button>
                    <div class="mx-ag-ops-archived-panel d-none" id="ag_ops_archived_panel" role="region" aria-live="polite">
                      <div class="mx-ag-ops-archived-list" id="ag_ops_archived_list"></div>
                    </div>
                  </section>

                  <section class="mx-ag-ops-summary-secondary" aria-labelledby="ag_ops_summary_title">
                    <h6 id="ag_ops_summary_title">Resumen de operadores</h6>
                    <div class="mx-ag-ops-summary-grid">
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Plazas canónicas ocupadas</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_active_value">No disponible</strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Cupo disponible</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_available_value">No disponible</strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Modelo canónico</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_max_allowed_value">2 operadores</strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Invitaciones pendientes</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_pending_value">No disponible</strong>
                      </article>
                    </div>
                    <p class="mx-ag-ops-summary-alert" id="ag_ops_limit_notice" aria-live="polite"></p>
                  </section>
                </section>

                <aside class="mx-ag-ops-side-panel" aria-labelledby="ag_ops_form_title">
                  <div class="mx-ag-ops-form-head">
                    <span class="mx-ag-ops-form-icon material-symbols-rounded" aria-hidden="true">person_add</span>
                    <h5 id="ag_ops_form_title">Invitar operador</h5>
                  </div>
                  <div class="mx-ag-ops-form-body">
                    <div class="mx-ag-ops-modal-error d-none" id="ag_ops_modal_error" role="alert"></div>
                    <div class="mx-ag-ops-accordion" id="ag_ops_modal_accordion">
                      <article class="mx-ag-ops-accordion-item is-active" data-ops-accordion-item="general">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="general">
                          <span class="mx-ag-ops-accordion-index">1</span>
                          <span class="mx-ag-ops-accordion-copy">
                            <strong>Datos generales</strong>
                            <small>Información básica del operador.</small>
                          </span>
                          <span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span>
                        </button>
                        <div class="mx-ag-ops-accordion-body">
                          <input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="hidden" id="ag_ops_edit_id">
                          <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="ag_ops_email">Correo del invitado</label><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> class="form-control" type="email" id="ag_ops_email" autocomplete="off" placeholder="operador@correo.com"></div>
                            <div class="col-12"><label class="form-label" for="ag_ops_role">Tipo de integrante</label><select <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> class="form-select" id="ag_ops_role"><option value="collaborator">Operador</option><option value="administrator">Administrador</option></select><div class="form-text">Ambos tipos ocupan una de las dos plazas. Los permisos se seleccionan individualmente.</div></div>
                          </div>
                        </div>
                      </article>

                      <article class="mx-ag-ops-accordion-item" data-ops-accordion-item="access">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="access"><span class="mx-ag-ops-accordion-index">2</span><span class="mx-ag-ops-accordion-copy"><strong>Invitación de acceso</strong><small>Cuenta personal de Identity y aceptación explícita.</small></span><span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span></button>
                        <div class="mx-ag-ops-accordion-body d-none"><p>La invitación dura siete días y reserva una plaza. La persona inicia sesión con su propia cuenta verificada y acepta los permisos ofrecidos.</p><p>La entrega requiere un servicio habilitado. No se generan contraseñas compartidas.</p></div>
                      </article>
                      <article class="mx-ag-ops-accordion-item" data-ops-accordion-item="permissions">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="permissions">
                          <span class="mx-ag-ops-accordion-index">3</span>
                          <span class="mx-ag-ops-accordion-copy">
                            <strong>Permisos</strong>
                            <small>Cada permiso de Agenda se concede por separado.</small>
                          </span>
                          <span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span>
                        </button>
                        <div class="mx-ag-ops-accordion-body d-none" id="ag_ops_canonical_permissions"><fieldset class="mx-ag-ops-permissions-grid"><legend class="visually-hidden">Permisos individuales de Agenda</legend>
                          <label class="mx-ag-ops-permission-card"><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="checkbox" data-operator-grant="agenda.appointments.read"><span class="mx-ag-ops-permission-copy"><strong>Consultar citas</strong></span></label>
                          <label class="mx-ag-ops-permission-card"><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="checkbox" data-operator-grant="agenda.appointments.create"><span class="mx-ag-ops-permission-copy"><strong>Crear citas</strong></span></label>
                          <label class="mx-ag-ops-permission-card"><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="checkbox" data-operator-grant="agenda.appointments.reschedule"><span class="mx-ag-ops-permission-copy"><strong>Reprogramar citas</strong></span></label>
                          <label class="mx-ag-ops-permission-card"><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="checkbox" data-operator-grant="agenda.appointments.cancel"><span class="mx-ag-ops-permission-copy"><strong>Cancelar citas</strong></span></label>
                          <label class="mx-ag-ops-permission-card"><input <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?> type="checkbox" data-operator-grant="agenda.availability.read"><span class="mx-ag-ops-permission-copy"><strong>Consultar disponibilidad</strong></span></label>
                        </fieldset><p class="form-text">Todos los permisos comienzan apagados. Un permiso no incluye los demás.</p></div>
                      </article>
                    </div>
                                      </div>
                  <div class="mx-ag-ops-form-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ag_ops_form_cancel_btn">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="ag_ops_save_btn" <?= !$operatorPanel['can_manage'] ? 'disabled' : '' ?>>Enviar invitación</button>
                  </div>
                </aside>
              </div>
            </div>


            <div class="modal fade mx-ag-ops-history-modal" id="ag_ops_history_modal" tabindex="-1" aria-labelledby="ag_ops_history_title" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <div class="mx-ag-ops-history-header-copy">
                      <h5 class="modal-title" id="ag_ops_history_title">Historial de acciones</h5>
                      <p class="mx-ag-ops-history-subtitle" id="ag_ops_history_subtitle">Consulta las acciones recientes del operador.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mx-ag-ops-history-summary">
                      <strong id="ag_ops_history_operator_name">Operador</strong>
                      <span id="ag_ops_history_count">0 acciones</span>
                    </div>
                    <div class="mx-ag-ops-history-list" id="ag_ops_history_list" role="list"></div>
                    <p class="mx-ag-ops-history-empty d-none" id="ag_ops_history_empty">No hay acciones registradas para este operador.</p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
