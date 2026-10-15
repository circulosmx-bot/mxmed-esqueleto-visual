<?php if (!isset($privateAccess) || !$privateAccess->allowed) { http_response_code(404); exit; } ?>
        <section class="mm-card<?= $operatorUi03 && $operatorOwner ? '' : ' d-none' ?>" id="p-ag-operadores">

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
              <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-workspace-btn" data-ag-work-panel="p-ag-ajustes">
                <span class="material-symbols-rounded" aria-hidden="true">settings</span>
                Configuración
              </button><?php endif; ?>
              <button type="button" class="mx-ag-workspace-btn is-active" data-ag-work-panel="p-ag-operadores">
                <span class="material-symbols-rounded" aria-hidden="true">support_agent</span>
                Operadores
              </button>
            </div>
            <div class="mx-ag-ops-shell" id="ag_ops_shell">
              <div class="mx-ag-ops-layout" id="ag_ops_layout">
                <section class="mx-ag-ops-main-panel" aria-labelledby="ag_ops_list_title">
                  <header class="mx-ag-ops-main-head">
                    <div>
                      <h6 id="ag_ops_list_title">Operadores registrados</h6>
                      <p>Administra aquí los operadores con acceso a tu consultorio.</p>
                    </div>
                    <button type="button" class="btn btn-primary mx-ag-ops-add-btn" id="ag_ops_add_btn">
                      <span class="material-symbols-rounded" aria-hidden="true">person_add</span>
                      Agregar operador
                    </button>
                  </header>

                  <?php if ($operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="ag_ops_refresh">Actualizar equipo</button><?php endif; ?>
                  <div class="mx-ag-ops-list" id="ag_ops_list" role="list"></div>

                  <section class="mx-ag-ops-archived" id="ag_ops_archived_section" aria-labelledby="ag_ops_archived_title">
                    <button type="button" class="mx-ag-ops-archived-toggle" id="ag_ops_archived_toggle" aria-expanded="false" aria-controls="ag_ops_archived_panel">
                      <span class="mx-ag-ops-archived-link">
                        <strong id="ag_ops_archived_title">Operadores eliminados</strong>
                        <span class="mx-ag-ops-archived-count" id="ag_ops_archived_count">0</span>
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
                        <span class="mx-ag-ops-kpi-label">Operadores activos</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_active_value"><?= $operatorUi03 ? 'No disponible' : '0/2' ?></strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Cupo disponible</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_available_value"><?= $operatorUi03 ? 'No disponible' : '0' ?></strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Máximo permitido</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_max_allowed_value"><?= $operatorUi03 ? '2' : '3' ?></strong>
                      </article>
                      <article class="mx-ag-ops-kpi-card">
                        <span class="mx-ag-ops-kpi-label">Invitaciones pendientes</span>
                        <strong class="mx-ag-ops-kpi-value" id="ag_ops_pending_value"><?= $operatorUi03 ? 'No disponible' : '0' ?></strong>
                      </article>
                    </div>
                    <p class="mx-ag-ops-summary-alert" id="ag_ops_limit_notice" aria-live="polite"></p>
                  </section>
                </section>

                <aside class="mx-ag-ops-side-panel" aria-labelledby="ag_ops_form_title">
                  <div class="mx-ag-ops-form-head">
                    <span class="mx-ag-ops-form-icon material-symbols-rounded" aria-hidden="true">person_add</span>
                    <h5 id="ag_ops_form_title">Agregar operador</h5>
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
                          <input type="hidden" id="ag_ops_edit_id">
<?php if ($operatorUi03): ?>
                          <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="ag_ops_email">Correo del invitado</label><input class="form-control" type="email" id="ag_ops_email" autocomplete="off" placeholder="operador@correo.com"></div>
                            <div class="col-12"><label class="form-label" for="ag_ops_role">Tipo de integrante</label><select class="form-select" id="ag_ops_role"><option value="collaborator">Operador</option><option value="administrator">Administrador</option></select><div class="form-text">Ambos tipos ocupan una de las dos plazas. Los permisos se seleccionan individualmente.</div></div>
                          </div>
<?php else: ?>                          <div class="row g-3">
                            <div class="col-md-7">
                              <label class="form-label" for="ag_ops_full_name">Nombre completo</label>
                              <input type="text" class="form-control" id="ag_ops_full_name" placeholder="Nombre y apellidos">
                            </div>
                            <div class="col-md-5">
                              <label class="form-label" for="ag_ops_gender">Género</label>
                              <select class="form-select" id="ag_ops_gender">
                                <option value="">Selecciona</option>
                                <option value="femenino">Femenino</option>
                                <option value="masculino">Masculino</option>
                                <option value="otro">Otro</option>
                              </select>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_phone">Teléfono móvil</label>
                              <input type="tel" class="form-control" id="ag_ops_phone" placeholder="999 123 4567">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_email">Correo electrónico</label>
                              <input type="email" class="form-control" id="ag_ops_email" placeholder="operador@correo.com">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_alias">Alias (Nombre corto para este operador · 1 palabra / ejemplo: MARY)</label>
                              <input type="text" class="form-control" id="ag_ops_alias" placeholder="Operador 01">
                              <div class="form-text">Usa 3 a 15 caracteres: letras, números o guion. Sin espacios.</div>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_role">Rol</label>
                              <select class="form-select" id="ag_ops_role" disabled aria-disabled="true" tabindex="-1">
                                <option value="operator">Operador</option>
                              </select>
                            </div>
                          </div>
<?php endif; ?>
                        </div>
                      </article>

<?php if ($operatorUi03): ?>
                      <article class="mx-ag-ops-accordion-item" data-ops-accordion-item="access">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="access"><span class="mx-ag-ops-accordion-index">2</span><span class="mx-ag-ops-accordion-copy"><strong>Invitación de acceso</strong><small>Cuenta personal de Identity y aceptación explícita.</small></span><span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span></button>
                        <div class="mx-ag-ops-accordion-body d-none"><p>La invitación dura siete días y reserva una plaza. La persona inicia sesión con su propia cuenta verificada y acepta los permisos ofrecidos.</p><p>En este entorno, el mensaje se entrega únicamente al buzón local de la persona invitada.</p></div>
                      </article>
<?php else: ?>                      <article class="mx-ag-ops-accordion-item" data-ops-accordion-item="access">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="access">
                          <span class="mx-ag-ops-accordion-index">2</span>
                          <span class="mx-ag-ops-accordion-copy">
                            <strong>Acceso al sistema</strong>
                            <small>Usuario y contraseña para acceso.</small>
                          </span>
                          <span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span>
                        </button>
                        <div class="mx-ag-ops-accordion-body d-none">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_login">Usuario login</label>
                              <input type="text" class="form-control" id="ag_ops_login" placeholder="usuario.operador">
                              <div class="form-text">Solo minúsculas: letras, números, punto o guion.</div>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="ag_ops_temp_password">Contraseña temporal</label>
                              <input type="text" class="form-control" id="ag_ops_temp_password" placeholder="Temporal segura">
                              <div class="mx-ag-ops-access-tools">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_ops_regen_password">Generar nueva contraseña</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_ops_copy_password">Copiar</button>
                              </div>
                              <div class="form-text">Mínimo 10 caracteres con mayúscula, minúscula, número y símbolo.</div>
                              <div class="form-text mx-ag-ops-access-feedback d-none" id="ag_ops_access_feedback"></div>
                            </div>
                            <div class="col-12">
                              <label class="form-check mx-ag-ops-check">
                                <input class="form-check-input" type="checkbox" id="ag_ops_force_change" checked>
                                <span class="form-check-label">Solicitar cambio de contraseña al primer acceso</span>
                              </label>
                            </div>
                          </div>
                        </div>
                      </article>

<?php endif; ?>
                      <article class="mx-ag-ops-accordion-item" data-ops-accordion-item="permissions">
                        <button type="button" class="mx-ag-ops-accordion-toggle" data-ops-accordion-toggle="permissions">
                          <span class="mx-ag-ops-accordion-index">3</span>
                          <span class="mx-ag-ops-accordion-copy">
                            <strong>Permisos</strong>
                            <small>Selecciona los módulos a los que tendrá acceso.</small>
                          </span>
                          <span class="material-symbols-rounded mx-ag-ops-accordion-chevron" aria-hidden="true">expand_more</span>
                        </button>
<?php if ($operatorUi03): ?>
                        <div class="mx-ag-ops-accordion-body d-none" id="ag_ops_canonical_permissions"><fieldset class="mx-ag-ops-permissions-grid"><legend class="visually-hidden">Permisos individuales de Agenda</legend>
<?php foreach (['read'=>'Consultar citas','create'=>'Crear citas','reschedule'=>'Reprogramar citas','cancel'=>'Cancelar citas','availability'=>'Consultar disponibilidad'] as $key=>$label): ?>
                          <label class="mx-ag-ops-permission-card"><input type="checkbox" data-operator-grant="<?= $key==='availability'?'agenda.availability.read':'agenda.appointments.'.$key ?>"><span class="mx-ag-ops-permission-copy"><strong><?= $label ?></strong></span></label>
<?php endforeach; ?>
                        </fieldset><p class="form-text">Todos los permisos comienzan apagados. Un permiso no incluye los demás.</p></div>
<?php else: ?>                        <div class="mx-ag-ops-accordion-body d-none">
                          <div class="mx-ag-ops-permissions-grid">
                            <label class="mx-ag-ops-permission-card">
                              <span class="mx-ag-ops-permission-copy">
                                <strong>Agenda</strong>
                                <small>Citas y bloqueos de horario</small>
                              </span>
                              <input type="checkbox" id="ag_ops_perm_agenda" checked>
                            </label>
                            <label class="mx-ag-ops-permission-card">
                              <span class="mx-ag-ops-permission-copy">
                                <strong>Pacientes</strong>
                                <small>Datos generales</small>
                              </span>
                              <input type="checkbox" id="ag_ops_perm_patients" checked>
                            </label>
                            <label class="mx-ag-ops-permission-card">
                              <span class="mx-ag-ops-permission-copy">
                                <strong>Facturación</strong>
                                <small>De Honorarios Médicos a Pacientes</small>
                              </span>
                              <input type="checkbox" id="ag_ops_perm_billing">
                            </label>
                            <label class="mx-ag-ops-permission-card">
                              <span class="mx-ag-ops-permission-copy">
                                <strong>Comprobante de Pago</strong>
                                <small>Acreditar pago de anualidad Perfil Médico</small>
                              </span>
                              <input type="checkbox" id="ag_ops_perm_payment_proof">
                            </label>
                          </div>
                        </div><?php endif; ?>
                      </article>
                    </div>
                    <?php if (!$operatorUi03): ?><section class="mx-ag-ops-send-step d-none" id="ag_ops_send_step" aria-live="polite">
                      <div class="mx-ag-ops-send-step-head">
                        <span class="material-symbols-rounded" aria-hidden="true">mark_email_read</span>
                        <div>
                          <h6>Operador guardado correctamente.</h6>
                          <p>Envía las credenciales de acceso al correo del operador.</p>
                        </div>
                      </div>
                      <div class="mx-ag-ops-send-step-grid">
                        <article class="mx-ag-ops-send-step-item">
                          <span>Correo del operador</span>
                          <strong id="ag_ops_send_email">-</strong>
                        </article>
                        <article class="mx-ag-ops-send-step-item">
                          <span>Usuario login</span>
                          <strong id="ag_ops_send_login">-</strong>
                        </article>
                        <article class="mx-ag-ops-send-step-item">
                          <span>Contraseña temporal</span>
                          <strong id="ag_ops_send_password">-</strong>
                        </article>
                      </div>
                      <div class="mx-ag-ops-send-step-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_ops_send_copy_credentials">
                          Copiar credenciales
                        </button>
                        <p class="mx-ag-ops-send-step-feedback d-none" id="ag_ops_send_feedback" role="status"></p>
                      </div>
                    </section><?php endif; ?>
                  </div>
                  <div class="mx-ag-ops-form-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ag_ops_form_cancel_btn">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="ag_ops_save_btn"><?= $operatorUi03 ? 'Enviar invitación local' : 'Siguiente' ?></button>
                  </div>
                </aside>
              </div>
            </div>

            <?php if (!$operatorUi03): ?><div class="modal fade mx-ag-ops-archive-verify-modal" id="ag_ops_archive_verify_modal" tabindex="-1" aria-labelledby="ag_ops_archive_verify_title" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="ag_ops_archive_verify_title">Confirmar eliminación de operador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <p class="mx-ag-ops-archive-verify-copy" id="ag_ops_archive_verify_copy">
                      Este operador dejará de tener acceso al sistema y será movido a Operadores archivados.
                    </p>
                    <p class="mx-ag-ops-archive-verify-channel" id="ag_ops_archive_verify_channel">
                      Ingresa el código de 6 dígitos enviado por SMS o correo electrónico.
                    </p>
                    <div class="otp-inputs mx-ag-ops-archive-otp" data-op-archive-otp-group>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" data-op-archive-otp-input>
                    </div>
                    <p class="mx-ag-ops-archive-verify-hint" id="ag_ops_archive_verify_hint">Ingresa un código numérico de 6 dígitos.</p>
                    <p class="mx-ag-ops-archive-verify-error d-none" id="ag_ops_archive_verify_error" role="alert">Ingresa un código válido de 6 dígitos para continuar.</p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="ag_ops_archive_verify_confirm_btn" disabled>Confirmar eliminación</button>
                  </div>
                </div>
              </div>
            </div><?php endif; ?>

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
