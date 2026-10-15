<?php if (!isset($privateAccess) || !$privateAccess->allowed) { http_response_code(404); exit; } ?>
        <section class="mm-card<?= $operatorUi03 && !$operatorOwner ? '' : ' d-none' ?>" id="p-ag-admin">

          <div class="head"><h5><span class="material-symbols-rounded title-ico" aria-hidden="true">edit_calendar</span>Mi Agenda · Administración de Citas</h5></div>

          <div class="body">

            <div class="mx-ag-workspace-shell mx-ag-workspace-shell--agenda" aria-label="Submenú operativo de agenda">
              <button type="button" class="mx-ag-workspace-btn is-active" data-ag-work-panel="p-ag-admin">
                <span class="material-symbols-rounded" aria-hidden="true">calendar_month</span>
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
              <?php if (!$operatorUi03 || $operatorOwner): ?><button type="button" class="mx-ag-workspace-btn" data-ag-work-panel="p-ag-operadores">
                <span class="material-symbols-rounded" aria-hidden="true">support_agent</span>
                Operadores
              </button><?php endif; ?>
            </div>

            <?php if ($operatorUi03): ?><p class="small text-muted" id="ag_operator_permission_hint"></p><button class="btn btn-primary mb-2" type="button" id="ag_granted_create_btn" hidden>Nueva cita</button><?php endif; ?>
            <div class="mx-ag-view-head">
              <div class="mx-ag-view-switch" id="ag_view_mode_switch" role="group" aria-label="Cambiar vista de agenda">
                <button type="button" class="mx-ag-view-pill" data-ag-view-mode="day" data-ag-view-disabled-title="Vista en preparación" aria-pressed="false" aria-disabled="true">Día</button>
                <button type="button" class="mx-ag-view-pill is-active" data-ag-view-mode="week" aria-pressed="true">Semana</button>
                <button type="button" class="mx-ag-view-pill" data-ag-view-mode="month" data-ag-view-disabled-title="Vista en preparación" aria-pressed="false" aria-disabled="true">Mes</button>
              </div>
              <div class="d-flex flex-wrap gap-3 small text-muted mx-ag-origin-catalog-row" id="ag_origin_catalog">
                <span class="mx-ag-origin-label">Origen de la cita:</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--user"></span>Usuario / Médico</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--operator-01"></span>Operador 01</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--operator-02"></span>Operador 02</span>
                <span class="mx-ag-origin-item d-none" id="ag_origin_item_operator_fallback"><span class="mx-ag-origin-dot mx-ag-origin-dot--operator"></span>Operadora</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--web-patient"></span>Paciente vía perfil web</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--ai"></span>Operador IA</span>
                <span class="mx-ag-origin-item"><span class="mx-ag-origin-dot mx-ag-origin-dot--call-center"></span>Call Center</span>
              </div>
            </div>

            <div class="row g-2 mx-agenda-v1" id="agendaFrontendV1">
              <div class="col-12 d-none">
                <input type="hidden" id="ag_doctor_filter">
              </div>
              <div class="col-md-6">
                <select class="form-select" id="ag_consultorio_filter" aria-label="Filtro de consultorio">
                  <option value="">Consultorio</option>
                </select>
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <button type="button" class="btn btn-primary me-2" id="ag_refresh_btn">
                  <i class="bi bi-arrow-clockwise"></i> Refrescar agenda
                </button>
                <span class="small text-muted d-none" id="ag_filters_hint" aria-hidden="true"></span>
              </div>
              <div class="col-12">
                <div id="ag_error_box" class="alert alert-danger d-none py-2 mb-2"></div>
                <div id="ag_next_focus_hint" class="alert alert-primary d-none py-2 mb-2"></div>
                <div id="ag_loading_box" class="small text-muted d-none mb-2" aria-hidden="true"></div>
                <div id="ag_calendar_wrap" class="mx-agenda-calendar-wrap">
                  <div id="ag_calendar"></div>
                  <div id="ag_cell_menu" class="mx-ag-cell-menu d-none" role="menu" aria-label="Acciones de celda disponible">
                    <button type="button" class="mx-ag-cell-menu-btn" id="ag_cell_menu_new_appointment">Nueva cita</button>
                    <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-cell-menu-btn" id="ag_cell_menu_block_slot">Bloquear</button><?php endif; ?>
                  </div>
                  <div id="ag_day_head_menu" class="mx-ag-day-head-menu d-none" role="menu" aria-label="Acciones del día">
                    <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-day-head-menu-btn" id="ag_day_head_block_btn">Bloquear todo el día</button><?php endif; ?>
                    <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-day-head-menu-btn" id="ag_day_head_view_btn">Bloquear de horario a horario</button><?php endif; ?>
                    <button type="button" class="mx-ag-day-head-menu-btn is-muted" id="ag_day_head_close_btn">Cerrar</button>
                  </div>
                  <div id="ag_custom_slot_menu" class="mx-ag-day-head-menu mx-ag-slot-quick-menu d-none" role="menu" aria-label="Acciones de horario disponible">
                    <button type="button" class="mx-ag-day-head-menu-btn" id="ag_custom_slot_new_btn">Nueva cita</button>
                    <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-day-head-menu-btn" id="ag_custom_slot_block_btn">Bloquear</button><?php endif; ?>
                    <?php if (!$operatorUi03): ?><button type="button" class="mx-ag-day-head-menu-btn d-none" id="ag_custom_slot_unblock_btn">Desbloquear horario</button><?php endif; ?>
                    <button type="button" class="mx-ag-day-head-menu-btn is-muted" id="ag_custom_slot_close_btn">Cerrar</button>
                  </div>
                </div>
                <div class="mx-ag-hours-toggle-wrap">
                  <button type="button" class="mx-ag-hours-toggle" id="ag_hours_toggle_btn" aria-expanded="false">
                    Ver más horas
                  </button>
                </div>
              </div>
            </div>

            <?php if (!$operatorUi03): ?><section class="agf01-followups" id="agf01-followups" aria-labelledby="agf01-title">
              <div class="agf01-head"><h6 id="agf01-title">Seguimientos</h6><span class="alr01-attention-summary" data-alr01-summary hidden></span></div>
              <p class="agf02-feedback d-none" data-agf02-feedback role="alert"></p>
              <p class="agf01-status" data-agf01-status role="status">Cargando seguimientos…</p>
              <div class="agf01-groups d-none" data-agf01-groups>
                <section class="agf01-group agf01-group--overdue" data-agf01-group="overdue"><h6>Vencidos</h6><div data-agf01-items="overdue"></div></section>
                <section class="agf01-group agf01-group--today" data-agf01-group="today"><h6>Para hoy</h6><div data-agf01-items="today"></div></section>
                <section class="agf01-group" data-agf01-group="upcoming"><h6>Próximos</h6><div data-agf01-items="upcoming"></div></section>
                <section class="agf01-group agf01-group--no-due" data-agf01-group="no_due"><h6>Sin fecha límite</h6><div data-agf01-items="no_due"></div></section>
              </div>
            </section><?php endif; ?>
            <?php if (!$operatorUi03): ?><dialog class="agf02-dialog" id="agf02-dialog" aria-labelledby="agf02-dialog-title">
              <form data-agf02-form>
                <h4 id="agf02-dialog-title" data-agf02-title></h4>
                <p data-agf02-description></p>
                <label for="agf02-reason">Nota de resolución</label>
                <textarea id="agf02-reason" data-agf02-reason rows="3" maxlength="255" required autocomplete="off"></textarea>
                <p class="agf02-dialog-error d-none" data-agf02-error role="alert" tabindex="-1"></p>
                <div class="agf02-dialog-actions">
                  <button type="button" class="btn btn-outline-secondary" data-agf02-back></button>
                  <button type="submit" class="btn btn-primary" data-agf02-confirm></button>
                </div>
              </form>
            </dialog><?php endif; ?>

            <div class="modal fade" id="ag_new_appointment_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="ag_create_modal_title">Nueva cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <div id="ag_create_error_box" class="alert alert-danger d-none py-2 mb-3"></div>
                    <div id="ag_create_flow_notice" class="alert alert-info d-none py-2 mb-3">Esto no representa una cita confirmada.</div>
                    <div class="row g-3">
                      <div class="col-12 d-none">
                        <input type="hidden" id="ag_patient_id_input">
                      </div>
                      <div class="col-12">
                        <section class="mx-ag-create-context-card" aria-label="Contexto de la cita">
                          <div class="mx-ag-create-context-grid">
                            <div class="mx-ag-create-context-item">
                              <div class="mx-ag-create-context-label">Fecha</div>
                              <div class="fw-semibold ag-create-start-date" id="ag_start_info_date">--</div>
                            </div>
                            <div class="mx-ag-create-context-item">
                              <div class="mx-ag-create-context-label">Hora</div>
                              <div class="fw-semibold ag-create-start-time" id="ag_start_info_time">--:--</div>
                            </div>
                          </div>
                          <div class="mx-ag-create-context-grid is-secondary">
                            <div class="mx-ag-create-context-item">
                              <label class="form-label" for="ag_modality_input">Modalidad</label>
                              <select class="form-select no-check" id="ag_modality_input">
                                <option value="in_person">Presencial</option>
                                <option value="videoconsulta">Videoconsulta</option>
                              </select>
                            </div>
                            <div class="mx-ag-create-context-item">
                              <label class="form-label" for="ag_consultorio_modal_info">Consultorio</label>
                              <input type="text" class="form-control no-check" id="ag_consultorio_modal_info" readonly>
                              <select class="form-select d-none no-check" id="ag_consultorio_modal" aria-hidden="true" tabindex="-1">
                                <option value="" disabled selected>No hay consultorios disponibles</option>
                              </select>
                            </div>
                          </div>
<?php if ($operatorUi03): ?><div id="ag_proposed_time_fields" class="row g-2" hidden><p class="small text-muted">Propón el horario. Tu permiso no incluye consultar disponibilidad; el servidor validará el horario y los conflictos al guardar.</p><div class="col-md-6"><label class="form-label" for="ag_start_input">Inicio propuesto</label><input class="form-control" type="datetime-local" id="ag_start_input"></div><div class="col-md-6"><label class="form-label" for="ag_end_input">Fin propuesto</label><input class="form-control" type="datetime-local" id="ag_end_input"></div></div><?php else: ?><input type="hidden" id="ag_start_input">
                          <input type="hidden" id="ag_end_input"><?php endif; ?>
                        </section>
                      </div>
                      <div class="col-12">
                        <section class="mx-ag-patient-type-shell" aria-label="Tipo de paciente para la cita">
                          <div class="mx-ag-patient-type-title">Tipo de paciente</div>
                          <div class="mx-ag-patient-type-subtitle">Selecciona el tipo de paciente para continuar.</div>

                          <?php if (!$operatorUi03): ?><article class="mx-ag-patient-accordion-item is-accent-blue" id="ag_patient_new_accordion">
                            <button
                              type="button"
                              class="mx-ag-patient-accordion-toggle"
                              id="ag_patient_mode_new_btn"
                              data-ag-patient-mode="new"
                              aria-selected="false"
                              aria-expanded="false"
                              aria-controls="ag_patient_new_wrap">
                              <span class="mx-ag-patient-accordion-badge">1</span>
                              <span class="mx-ag-patient-accordion-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                              <span class="mx-ag-patient-accordion-copy">
                                <span class="mx-ag-patient-accordion-title">Paciente nuevo</span>
                                <span class="mx-ag-patient-accordion-desc">Registrar un paciente que aún no existe en el sistema.</span>
                              </span>
                              <span class="mx-ag-patient-accordion-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>
                            <div class="mx-ag-patient-accordion-body d-none" id="ag_patient_new_wrap">
                              <div class="row g-2">
                                <div class="col-md-4">
                                  <label class="form-label mb-1" for="ag_patient_new_first_name">Nombre(s)</label>
                                  <input type="text" class="form-control" id="ag_patient_new_first_name" placeholder="Nombre(s)">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label mb-1" for="ag_patient_new_last_name_1">Primer apellido</label>
                                  <input type="text" class="form-control" id="ag_patient_new_last_name_1" placeholder="Primer apellido">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label mb-1" for="ag_patient_new_last_name_2">Segundo apellido</label>
                                  <input type="text" class="form-control" id="ag_patient_new_last_name_2" placeholder="Segundo apellido">
                                </div>
                                <div class="col-md-4">
                                  <select class="form-select" id="ag_patient_new_sex" aria-label="Sexo del paciente">
                                    <option value="" selected>Sexo</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                  </select>
                                </div>
                                <div class="col-md-4">
                                  <input type="text" class="form-control" id="ag_patient_new_phone" placeholder="Teléfono">
                                </div>
                                <div class="col-md-4">
                                  <input type="email" class="form-control" id="ag_patient_new_email" placeholder="Correo electrónico (opcional)">
                                </div>
                              </div>
                              <div class="row g-2 mt-2 d-none" id="ag_patient_birthdate_row">
                                <div class="col-12">
                                  <label class="form-label mb-1">Fecha de nacimiento</label>
                                </div>
                                <div class="col-md-4 col-12">
                                  <select class="form-select" id="ag_patient_birth_day">
                                    <option value="">Día</option>
                                  </select>
                                </div>
                                <div class="col-md-4 col-12">
                                  <select class="form-select" id="ag_patient_birth_month">
                                    <option value="">Mes</option>
                                  </select>
                                </div>
                                <div class="col-md-4 col-12">
                                  <select class="form-select" id="ag_patient_birth_year">
                                    <option value="">Año</option>
                                  </select>
                                </div>
                                <input type="hidden" id="ag_patient_birthdate_input">
                                <div id="ag_patient_birthdate_hint" class="form-text d-none"></div>
                              </div>
                              <div id="ag_patient_birthdate_info" class="small text-muted mt-2 d-none"></div>
                              <div class="mt-3">
                                <label class="form-label" for="ag_appointment_reason_input">Motivo de la cita <span class="text-muted">(opcional)</span></label>
                                <textarea
                                  class="form-control no-check"
                                  id="ag_appointment_reason_input"
                                  rows="2"
                                  maxlength="600"
                                  placeholder="Describe brevemente el motivo de la consulta"
                                ></textarea>
                              </div>
                            </div>
                          </article><?php endif; ?>

                          <article class="mx-ag-patient-accordion-item is-accent-purple" id="ag_patient_existing_accordion">
                            <button
                              type="button"
                              class="mx-ag-patient-accordion-toggle"
                              id="ag_patient_mode_existing_btn"
                              data-ag-patient-mode="existing"
                              aria-selected="false"
                              aria-expanded="false"
                              aria-controls="ag_patient_existing_wrap">
                              <span class="mx-ag-patient-accordion-badge">2</span>
                              <span class="mx-ag-patient-accordion-icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
                              <span class="mx-ag-patient-accordion-copy">
                                <span class="mx-ag-patient-accordion-title">Paciente registrado</span>
                                <span class="mx-ag-patient-accordion-desc">Buscar un paciente que ya existe en el sistema.</span>
                              </span>
                              <span class="mx-ag-patient-accordion-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>
                            <div class="mx-ag-patient-accordion-body d-none" id="ag_patient_existing_wrap">
                              <div id="ag_active_patient_box" class="d-none">
                                <div class="alert alert-warning py-2 mb-2">
                                  <div class="fw-semibold mb-2" id="ag_active_patient_text">Paciente activo detectado.</div>
                                  <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="ag_use_active_patient_yes">Sí, usar paciente activo</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ag_use_active_patient_no">No, capturar otro paciente</button>
                                  </div>
                                </div>
                              </div>
                              <label class="form-label" for="ag_patient_search_query">Buscar paciente registrado</label>
                              <div class="input-group">
                                <input type="text" class="form-control no-check" data-no-check="1" id="ag_patient_search_query" placeholder="<?= $operatorUi03 ? 'Nombre o apellido' : 'Nombre, Apellido ó Teléfono' ?>">
                                <button type="button" class="btn mx-ag-btn-new-patient" id="ag_patient_search_btn">Filtrar</button>
                              </div>
                              <div id="ag_patient_search_results" class="list-group mt-2"></div>
                              <div id="ag_patient_search_msg" class="small text-muted mt-1 d-none"></div>
                            </div>
                          </article>
                        </section>
                      </div>
                      <div class="col-12 d-none">
                        <input type="hidden" id="ag_channel_input" value="agenda_frontend_v1">
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="ag_create_submit_btn">Guardar cita</button>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!$operatorUi03): ?><div class="modal fade" id="ag_patient_shared_phone_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header mx-ag-shared-phone-header">
                    <div class="mx-ag-shared-phone-header-copy">
                      <div class="mx-ag-shared-phone-number" id="ag_patient_shared_phone_number">--</div>
                      <div class="mx-ag-shared-phone-header-title">Este teléfono ya ha sido asignado a los siguientes pacientes</div>
                      <div class="mx-ag-shared-phone-header-subtitle">Selecciona un paciente de la lista o agrega uno nuevo a este número.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body pt-2 pb-3">
                    <div id="ag_patient_shared_phone_notice" class="mx-ag-shared-phone-notice d-none" role="status" aria-live="polite">
                      <div class="mx-ag-shared-phone-title">Pacientes vinculados a este número</div>
                      <div id="ag_patient_shared_phone_list" class="mx-ag-shared-phone-list"></div>
                      <div class="mx-ag-shared-phone-continue-wrap">
                        <button type="button" class="btn btn-outline-primary w-100" id="ag_patient_shared_phone_continue_btn">El paciente que estoy registrando también usa este número</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div><?php endif; ?>

            <div class="modal fade" id="ag_patient_lookup_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header mx-ag-shared-phone-header">
                    <div class="mx-ag-shared-phone-header-copy">
                      <div class="mx-ag-shared-phone-header-title mx-ag-patient-lookup-header-title">Pacientes encontrados</div>
                      <div class="mx-ag-shared-phone-header-subtitle">Selecciona el paciente correcto para asignarlo a la cita.</div>
                      <div id="ag_patient_lookup_context" class="mx-ag-patient-lookup-context d-none"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body pt-2 pb-3">
                    <div id="ag_patient_lookup_notice" class="mx-ag-shared-phone-notice d-none" role="status" aria-live="polite">
                      <div id="ag_patient_lookup_list" class="mx-ag-shared-phone-list"></div>
                      <?php if (!$operatorUi03): ?><div id="ag_patient_lookup_reason_wrap" class="mt-3 d-none">
                        <div class="fw-semibold">Motivo de la cita</div>
                        <div class="small text-muted mb-2">Puedes agregar el motivo de la consulta ahora o continuar sin motivo.</div>
                        <textarea
                          class="form-control no-check"
                          id="ag_patient_lookup_reason_input"
                          rows="2"
                          maxlength="600"
                          placeholder="Describe brevemente el motivo de la consulta"
                        ></textarea>
                        <div id="ag_patient_lookup_reason_validation" class="form-text text-muted d-none">Escribe un motivo o usa 'Agendar sin motivo'.</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                          <button type="button" class="btn btn-primary btn-sm" id="ag_patient_lookup_reason_with_btn">Agendar con motivo</button>
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_patient_lookup_reason_without_btn">Agendar sin motivo</button>
                        </div>
                      </div><?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!$operatorUi03): ?><div class="modal fade" id="ag_block_slot_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Bloquear horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <div id="ag_block_error_box" class="alert alert-danger d-none py-2 mb-3"></div>
                    <div id="ag_block_help_box" class="alert alert-info d-none py-2 mb-3"></div>
                    <div class="row g-3">
                      <div class="col-12">
                        <div class="mx-ag-block-base-label">Horario base</div>
                        <div class="fw-semibold mx-ag-block-base-value" id="ag_block_range_base">--</div>
                      </div>
                      <div class="col-12">
                        <label class="form-label d-block mb-2">Tipo de bloqueo</label>
                        <div class="mx-ag-block-type-stack">
                          <div class="mx-ag-block-type-card mx-ag-block-type-card--range" data-ag-block-card="until">
                            <label class="mx-ag-block-type-head" for="ag_block_mode_until">
                              <input type="radio" name="ag_block_mode" value="until" id="ag_block_mode_until">
                              <div class="mx-ag-block-type-copy">
                                <div class="mx-ag-block-type-title">Bloquear rango de horario</div>
                                <p class="mx-ag-block-type-desc">Bloquea desde la hora seleccionada hasta el final de la última ventana operativa del día.</p>
                              </div>
                            </label>
                            <div class="mx-ag-block-range-wrap d-none" id="ag_block_end_wrap">
                              <label class="visually-hidden" for="ag_block_end_select">Hora final</label>
                              <div class="mx-ag-block-range-row">
                                <span class="mx-ag-block-range-prefix" id="ag_block_mode_until_label">Desde --:-- hasta</span>
                                <select class="form-select" id="ag_block_end_select"></select>
                                <span class="mx-ag-block-range-suffix">(Hora final)</span>
                              </div>
                            </div>
                          </div>
                          <div class="mx-ag-block-type-divider" aria-hidden="true"><span>o</span></div>
                          <div class="mx-ag-block-type-card mx-ag-block-type-card--fullday" data-ag-block-card="single">
                            <label class="mx-ag-block-type-head" for="ag_block_mode_single">
                              <input type="radio" name="ag_block_mode" value="single" id="ag_block_mode_single" checked>
                              <div class="mx-ag-block-type-copy">
                                <div class="mx-ag-block-type-title" id="ag_block_mode_single_label">Bloquear todo el día</div>
                                <p class="mx-ag-block-type-desc">Bloquea todas las ventanas operativas del día seleccionado.</p>
                              </div>
                              <span class="mx-ag-block-type-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                            </label>
                          </div>
                        </div>
                      </div>
                      <div class="col-12">
                        <label class="form-label d-block mb-2">Motivo</label>
                        <div class="mx-ag-block-modal-chips" id="ag_block_reason_chips"></div>
                      </div>
                      <div class="col-12 d-none" id="ag_block_other_wrap">
                        <input type="text" class="form-control" id="ag_block_other_input" maxlength="60" placeholder="Especifica motivo">
                      </div>
                      <div class="col-12 d-none" id="ag_block_warning_wrap">
                        <div class="alert alert-warning py-2 mb-2" id="ag_block_warning_text">Este rango incluye citas existentes</div>
                        <div class="d-flex flex-wrap gap-2">
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_block_adjust_btn">Ajustar rango</button>
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_block_view_conflicts_btn">Ver citas</button>
                        </div>
                      </div>
                      <div class="col-12 d-none" id="ag_block_conflicts_wrap">
                        <div class="small text-uppercase fw-semibold text-muted mb-2">Citas afectadas</div>
                        <div id="ag_block_conflicts_list" class="mx-ag-slot-block-conflicts-list"></div>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ag_block_cancel_btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="ag_block_confirm_btn">Confirmar bloqueo</button>
                  </div>
                </div>
              </div>
            </div><?php endif; ?>

            <div class="modal fade" id="ag_next_slots_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content mx-ag-next-slots-modal">
                  <div class="modal-header mx-ag-next-slots-modal-header">
                    <div class="mx-ag-next-slots-header-main">
                      <span class="mx-ag-next-slots-header-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                      <div class="mx-ag-next-slots-header-copy">
                        <h5 class="modal-title">Siguiente cita disponible</h5>
                        <div class="mx-ag-next-slots-header-subtitle">Selecciona el horario que mejor se ajuste para tu paciente.</div>
                      </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body mx-ag-next-slots-modal-body">
                    <div id="ag_next_slots_waitlist_feedback" class="alert alert-success d-none py-2 mb-2"></div>
                    <div id="ag_next_slots_error" class="alert alert-danger d-none py-2 mb-2"></div>
                    <div id="ag_next_slots_loading" class="small text-muted d-none mb-2">Buscando disponibilidad real…</div>
                    <div id="ag_next_slots_results" class="mx-ag-next-slots-results"></div>
                    <div id="ag_next_slots_empty" class="small text-muted d-none mt-2">No se encontraron opciones disponibles.</div>
                    <?php if (!$operatorUi03): ?><div class="mx-ag-next-slots-waitlist-cta" id="ag_next_slots_waitlist_cta">
                      <div class="mx-ag-next-slots-waitlist-question">¿No encontraste un horario adecuado?</div>
                      <button type="button" class="btn btn-outline-primary btn-sm" id="ag_next_slots_waitlist_btn">Inscribir en lista de espera</button>
                    </div><?php endif; ?>
                    <div class="mx-ag-next-slots-info-note">
                      <i class="bi bi-info-circle" aria-hidden="true"></i>
                      <span>Las citas mostradas están basadas en tu configuración actual de consultorio y duración.</span>
                    </div>
                  </div>
                  <div class="modal-footer mx-ag-next-slots-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ag_next_slots_prev_btn">Ver 3 anteriores</button>
                    <button type="button" class="btn btn-outline-secondary" id="ag_next_slots_more_btn">Ver siguientes 3</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!$operatorUi03): ?><div class="modal fade" id="ag_waitlist_terms_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Lista de espera</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <p class="mb-2">Si se libera un espacio por cancelación o reprogramación, podremos considerar al paciente para ocupar ese horario.</p>
                    <p class="mb-2"><strong>Entrar en lista de espera no representa una cita confirmada ni garantiza disponibilidad anticipada.</strong></p>
                    <p class="mb-0 text-muted">Confirma con el paciente que entiende que será contactado únicamente si aparece una oportunidad compatible.</p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="ag_waitlist_terms_continue_btn">Entiendo, continuar</button>
                  </div>
                </div>
              </div>
            </div><?php endif; ?>

            <div class="modal fade" id="ag_event_actions_modal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header mx-ag-event-header">
                    <div class="mx-ag-event-header-main">
                      <div class="mx-ag-event-title-cluster">
                        <span class="mx-ag-event-title-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                        <h5 class="modal-title mb-0">Detalle de cita</h5>
                      </div>
                      <span class="badge text-bg-secondary" id="ag_event_action_status_badge">--</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <div id="ag_event_action_error_box" class="alert alert-danger d-none py-2 mb-3"></div>
                    <div id="ag_event_detail_wrap" class="mx-ag-modal-stack" data-view="detail">
                      <section class="mx-ag-event-patient-hero">
                        <div class="mx-ag-event-patient-avatar" aria-hidden="true">
                          <img id="ag_event_action_avatar_img" class="mx-ag-event-patient-avatar-image" alt="Avatar de paciente" />
                        </div>
                        <div class="mx-ag-event-patient-summary">
                          <div class="mx-ag-event-overline">Paciente</div>
                          <div class="mx-ag-event-patient-name" id="ag_event_action_patient">--</div>
                          <div class="mx-ag-event-patient-meta">
                            <span class="mx-ag-event-meta-chip"><i class="bi bi-telephone"></i><span id="ag_event_action_phone">--</span></span>
                            <span class="mx-ag-event-meta-chip"><i class="bi bi-calendar3"></i><span id="ag_event_action_birth">--</span></span>
                          </div>
                        </div>
                        <div class="mx-ag-event-datetime-card mx-ag-event-detail-field--datetime">
                          <div class="mx-ag-event-overline">Fecha y hora</div>
                          <div class="mx-ag-event-detail-value" id="ag_event_action_time">--</div>
                        </div>
                      </section>
                      <section class="mx-ag-event-kpi-grid">
                        <article id="ag_event_action_consultorio_wrap" class="mx-ag-event-kpi-card">
                          <div class="mx-ag-event-kpi-icon" aria-hidden="true"><i class="bi bi-hospital"></i></div>
                          <div class="mx-ag-event-kpi-body">
                            <div class="mx-ag-event-kpi-label">Consultorio</div>
                            <div class="mx-ag-event-kpi-value" id="ag_event_action_consultorio">--</div>
                          </div>
                        </article>
                        <article class="mx-ag-event-kpi-card">
                          <div class="mx-ag-event-kpi-icon" aria-hidden="true"><i class="bi bi-diagram-2"></i></div>
                          <div class="mx-ag-event-kpi-body">
                            <div class="mx-ag-event-kpi-label">Origen de la cita</div>
                            <div class="mx-ag-event-kpi-value mx-ag-event-origin-value">
                              <span class="mx-ag-origin-dot mx-ag-origin-dot--user" id="ag_event_action_origin_dot"></span>
                              <span id="ag_event_action_origin">--</span>
                            </div>
                          </div>
                        </article>
                        <article class="mx-ag-event-kpi-card">
                          <div class="mx-ag-event-kpi-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></div>
                          <div class="mx-ag-event-kpi-body">
                            <div class="mx-ag-event-kpi-label">Estado</div>
                            <div class="mx-ag-event-kpi-value" id="ag_event_action_status">--</div>
                          </div>
                        </article>
                      </section>
                      <div id="ag_event_action_reason_wrap" class="mx-ag-event-reason-card d-none">
                        <div class="mx-ag-event-reason-label"><i class="bi bi-chat-left-text"></i> Motivo</div>
                        <div class="mx-ag-event-reason-value" id="ag_event_action_reason">--</div>
                      </div>
                      <?php if (!$operatorUi03): ?><section id="ag_event_identity_review_wrap" class="mx-ag-identity-review d-none" aria-live="polite">
                        <div class="mx-ag-identity-review-head">
                          <div>
                            <div class="mx-ag-identity-review-title"><i class="bi bi-person-vcard"></i> Identidad del paciente</div>
                            <div class="mx-ag-identity-review-copy">La declaración de la persona y la resolución de identidad se revisan por separado.</div>
                          </div>
                        </div>
                        <div class="mx-ag-identity-review-statuses">
                          <div class="mx-ag-identity-review-status"><span>Declaración del paciente</span><strong id="ag_event_identity_patient_type">--</strong></div>
                          <div class="mx-ag-identity-review-status"><span>Resolución de identidad</span><strong id="ag_event_identity_status">--</strong></div>
                        </div>
                        <div id="ag_event_identity_warning" class="mx-ag-identity-review-warning d-none">
                          <strong>Revisión de identidad requerida</strong>
                          <span>La información proporcionada coincide con más de un posible paciente o no permite identificar un expediente de forma única.</span>
                        </div>
                        <div id="ag_event_identity_candidates" class="mx-ag-identity-candidates"></div>
                        <div id="ag_event_identity_actions" class="mx-ag-identity-actions d-none">
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_identity_keep_new_btn">Mantener como paciente nuevo</button>
                        </div>
                        <div id="ag_event_identity_cleanup_wrap" class="mx-ag-identity-cleanup d-none">
                          <div id="ag_event_identity_cleanup_status" class="small"></div>
                          <div id="ag_event_identity_cleanup_action" class="d-none">
                            <div class="small text-muted mb-2">Este registro fue creado durante la reserva y ya no tiene citas ni información clínica asociada.</div>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="ag_event_identity_cleanup_btn">Desactivar paciente duplicado</button>
                          </div>
                        </div>
                        <div id="ag_event_identity_feedback" class="small d-none"></div>
                      </section><?php endif; ?>
                      <?php if (!$operatorUi03): ?><div id="ag_event_timeline_wrap" class="mx-ag-event-timeline-wrap">
                        <div class="mx-ag-event-timeline-title"><i class="bi bi-clock-history"></i> Historial de la cita</div>
                        <div id="ag_event_timeline_loading" class="small text-muted d-none">Cargando historial…</div>
                        <div id="ag_event_timeline_error" class="small text-danger d-none">No fue posible cargar el historial de esta cita.</div>
                        <div id="ag_event_timeline_empty" class="small text-muted d-none">Sin eventos registrados.</div>
                        <div id="ag_event_timeline_list" class="mx-ag-event-timeline-list"></div>
                      </div><?php endif; ?>
                      <div id="ag_event_resolution_wrap" class="d-none">
                        <div class="small text-muted mb-1" id="ag_event_resolution_title">Resolución de cita</div>
                        <div class="mx-ag-event-resolution-actions">
                          <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_mark_in_progress_btn">Marcar en curso</button><?php endif; ?>
                          <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_mark_finished_btn">Paciente atendido</button><?php endif; ?>
                          <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_mark_cancel_btn">Paciente canceló tarde</button><?php endif; ?>
                          <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_mark_no_show_btn">Paciente no asistió</button><?php endif; ?>
                        </div>
                        <div id="ag_event_resolution_note" class="small text-muted mt-2 d-none"></div>
                      </div>
                    </div>

                    <div id="ag_event_reschedule_wrap" class="d-none" data-view="reschedule"><?php if ($operatorUi03): ?><div id="ag_reschedule_proposal_fields" hidden><label class="form-label" for="ag_reschedule_proposed_start">Nuevo inicio propuesto</label><input class="form-control" type="datetime-local" id="ag_reschedule_proposed_start"><p class="small text-muted">Sin permiso de disponibilidad. El horario propuesto se validará al confirmar.</p></div><?php endif; ?>
                      <div id="ag_event_reschedule_note" class="alert d-none py-2 mb-0"></div>
                      <div class="row g-3">
                        <div class="col-12">
                          <div class="mx-ag-reschedule-calendar">
                            <div class="mx-ag-reschedule-calendar-head">
                              <div class="mx-ag-reschedule-calendar-month" id="ag_event_reschedule_month_label">--</div>
                              <div class="mx-ag-reschedule-calendar-nav">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_reschedule_month_prev">Mes anterior</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_reschedule_month_next">Siguiente mes</button>
                              </div>
                            </div>
                            <div class="mx-ag-reschedule-calendar-weekdays" aria-hidden="true">
                              <span>dom</span><span>lun</span><span>mar</span><span>mié</span><span>jue</span><span>vie</span><span>sáb</span>
                            </div>
                            <div id="ag_event_reschedule_days" class="mx-ag-reschedule-days"></div>
                          </div>
                        </div>
                        <div class="col-12">
                          <label class="form-label mb-2">Horas disponibles</label>
                          <div id="ag_event_reschedule_hours" class="mx-ag-reschedule-hours"></div>
                        </div>
                      </div>
                      <div id="ag_event_reschedule_confirm_wrap" class="mt-2">
                        <div class="mx-ag-event-reschedule-summary d-none" id="ag_event_reschedule_confirm_copy"></div>
                        <div class="d-flex justify-content-end gap-2">
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_reschedule_back_btn">Cancelar</button>
                          <button type="button" class="btn btn-primary btn-sm" id="ag_event_reschedule_confirm_btn" disabled>Confirmar reprogramación</button>
                        </div>
                      </div>
                    </div>

                    <div id="ag_event_cancel_wrap" class="d-none mt-3" data-view="cancel">
                      <div id="ag_event_cancel_prompt" class="alert alert-warning py-2 mb-2">¿Confirmas cancelar esta cita?</div>
                      <div id="ag_event_cancel_post_hint" class="alert alert-info py-2 mt-3 mb-2 d-none">
                        Se liberó un espacio. ¿Deseas resolver este hueco?
                      </div>
                      <div id="ag_event_cancel_post_actions" class="d-none">
                        <div class="d-flex flex-wrap gap-2">
                          <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_cancel_post_waitlist_btn">Actualizar candidatos</button><?php endif; ?>
                          <button type="button" class="btn btn-outline-primary btn-sm" id="ag_event_cancel_post_next_available_btn">Siguiente cita disponible</button>
                          <button type="button" class="btn btn-primary btn-sm" id="ag_event_cancel_post_new_btn">Nueva cita en este horario</button>
                        </div>
                      </div>
                      <?php if (!$operatorUi03): ?><div id="ag_event_cancel_waitlist_wrap" class="d-none mt-3">
                        <div id="ag_event_cancel_resolve_title" class="fw-semibold mb-2">Resolver hueco</div>
                        <div id="ag_event_cancel_slot_summary" class="small border rounded-3 bg-light px-3 py-2 mb-2 d-none"></div>
                        <div class="small text-muted mb-2">Pacientes en lista de espera que podrían ocupar este espacio</div>
                        <div id="ag_event_cancel_waitlist_loading" class="small text-muted d-none mb-2">Cargando lista de espera…</div>
                        <div id="ag_event_cancel_waitlist_meta" class="small text-muted d-none mb-2"></div>
                        <div id="ag_event_cancel_waitlist_empty" class="small text-muted d-none mb-2">No hay pacientes compatibles en lista de espera para este hueco.</div>
                        <div id="ag_event_cancel_waitlist_empty_actions" class="d-none d-flex flex-wrap gap-2 mb-2">
                          <button type="button" class="btn btn-outline-primary btn-sm" id="ag_event_cancel_waitlist_empty_next_btn">Buscar siguiente cita disponible</button>
                          <button type="button" class="btn btn-outline-secondary btn-sm" id="ag_event_cancel_waitlist_empty_close_btn">Cerrar</button>
                        </div>
                        <div id="ag_event_cancel_waitlist_list" class="list-group"></div>
                      </div><?php endif; ?>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ag_event_close_btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>
                    <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-primary d-none" id="ag_event_confirm_btn"><i class="bi bi-check-circle"></i> Confirmar cita</button><?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" id="ag_event_cancel_btn"><i class="bi bi-trash3"></i> Cancelar cita</button>
                    <button type="button" class="btn btn-primary" id="ag_event_reschedule_btn"><i class="bi bi-calendar2-plus"></i> Reprogramar</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="ag_event_cancel_back_btn">Volver</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="ag_event_cancel_confirm_no_flag_btn">Cancelar sin marcar paciente</button>
                    <?php if (!$operatorUi03): ?><button type="button" class="btn btn-outline-dark d-none" id="ag_event_cancel_confirm_black_btn">Cancelar y marcar lista negra</button><?php endif; ?>
                    <button type="button" class="btn btn-primary d-none" id="ag_event_cancel_confirm_btn">Sí, cancelar</button>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </section>
