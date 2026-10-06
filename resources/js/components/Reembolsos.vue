<template>
  <div class="reembolso-container">
    <!-- Alertas globales -->
    <div v-if="mensajeExito" class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
      <div class="d-flex align-items-center">
        <span class="fs-4 me-2">✅</span>
        <div>
          <h5 class="alert-heading mb-1 fw-bold">¡Reembolso Registrado Exitosamente!</h5>
          <p class="mb-0">{{ mensajeExito }}</p>
        </div>
      </div>
      <div class="mt-3 pt-2 border-top border-success-subtle d-flex flex-wrap gap-2">
        <button v-if="ultimoResultado?.ingreso?.id" type="button" class="btn btn-sm btn-success" @click="descargarPdfIngreso">
          📄 Descargar PDF Nota Ingreso
        </button>
        <button type="button" class="btn btn-sm btn-outline-success" @click="abrirComprobanteImprimible">
          🖨️ Ver / Imprimir Comprobante Oficial
        </button>
        <button type="button" class="btn btn-sm btn-light ms-auto" @click="reiniciarFormulario">
          ➕ Registrar Otro Reembolso
        </button>
      </div>
      <button type="button" class="btn-close" aria-label="Cerrar" @click="mensajeExito = ''"></button>
    </div>

    <div v-if="errorGeneral" class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
      <div class="d-flex align-items-center">
        <span class="fs-4 me-2">⚠️</span>
        <div>
          <h6 class="alert-heading mb-1 fw-bold">Error al procesar el reembolso</h6>
          <p class="mb-0">{{ errorGeneral }}</p>
        </div>
      </div>
      <button type="button" class="btn-close" aria-label="Cerrar" @click="errorGeneral = ''"></button>
    </div>

    <form class="reembolso-card" @submit.prevent="solicitarConfirmacion" novalidate>
      <!-- Cabecera Hero -->
      <div class="reembolso-hero">
        <div class="hero-content">
          <div class="badge-modulo">MÓDULO DE ALMACÉN · FIN DE MES</div>
          <h2>Reembolsos Mensuales de Medicamentos</h2>
          <p>
            Registro de facturas por compras externas de pacientes. Genera formalmente el <strong>Ingreso</strong>
            línea por línea y la <strong>Salida consolidada</strong> con Costo Promedio Ponderado (CPP), sin alterar el stock físico real.
          </p>
        </div>
        <div class="hero-badge-box">
          <span class="badge-label">TIPO DE PROCESO</span>
          <strong>REEMBOLSO CONTABLE</strong>
          <small class="text-white-50">Cálculo Automático CPP</small>
        </div>
      </div>

      <div class="reembolso-body p-4">
        <!-- SECCIÓN 1: DATOS GENERALES DEL MOVIMIENTO -->
        <section class="mb-4">
          <div class="section-title-wrap mb-3 pb-2 border-bottom">
            <span class="section-kicker text-uppercase fw-semibold">DATOS DE LA NOTA</span>
            <h4 class="mb-0 fw-bold text-csc-blue">1. Encabezado del Reembolso</h4>
          </div>

          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">Fecha de registro / fin de mes <span class="text-danger">*</span></label>
              <input
                v-model="form.ingreso.fecha_ingreso"
                type="date"
                class="form-control"
                required
              >
              <small class="text-muted">Fecha contable asignada al mes.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Almacén de origen <span class="text-danger">*</span></label>
              <input
                v-model.trim="form.ingreso.almacen"
                type="text"
                class="form-control"
                required
                maxlength="150"
              >
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Responsable de Almacén <span class="text-danger">*</span></label>
              <input
                v-model.trim="form.ingreso.recibido_por"
                type="text"
                class="form-control"
                required
                placeholder="Nombre de la responsable"
              >
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Identificador de Proveedor / Grupo</label>
              <input
                v-model.trim="form.proveedor.nombre"
                type="text"
                class="form-control"
                required
                placeholder="VARIOS PACIENTES"
              >
              <small class="text-muted">Por defecto: VARIOS PACIENTES</small>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">N.º de Informe / Trámite / Respaldo (Opcional)</label>
              <input
                v-model.trim="form.ingreso.numero_remision"
                type="text"
                class="form-control"
                placeholder="Ej. INF-ALM-045/2026 o Carpeta Reembolsos Octubre"
              >
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Observaciones generales de respaldo ante la Caja</label>
              <textarea
                v-model.trim="form.ingreso.observacion"
                class="form-control"
                rows="2"
                placeholder="Respaldo ante auditoría: facturas y recetas de medicamentos adquiridos externamente por pacientes..."
              ></textarea>
            </div>
          </div>
        </section>

        <!-- SECCIÓN 2: REGISTRO ÍTEM POR ÍTEM (FACTURAS DE PACIENTES) -->
        <section class="mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <div>
              <span class="section-kicker text-uppercase fw-semibold">DETALLE POR FACTURA / PACIENTE</span>
              <h4 class="mb-0 fw-bold text-csc-blue">2. Facturas de Medicamentos Adquiridos</h4>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge bg-secondary rounded-pill px-3 py-2">
                {{ form.items.length }} {{ form.items.length === 1 ? 'factura registrada' : 'facturas registradas' }}
              </span>
              <button
                type="button"
                class="btn btn-csc-orange btn-sm text-white px-3 fw-semibold shadow-sm"
                @click="agregarFila"
              >
                + Agregar Factura / Ítem
              </button>
            </div>
          </div>

          <div class="table-responsive bg-white rounded-3 border shadow-sm">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-csc-header text-white">
                <tr>
                  <th style="width: 40px;" class="text-center">#</th>
                  <th style="min-width: 280px;">Medicamento (Búsqueda predictiva) <span class="text-warning">*</span></th>
                  <th style="width: 140px;">Partida / LINAME</th>
                  <th style="min-width: 180px;">N.º Factura / Paciente / Recibo</th>
                  <th style="width: 120px;" class="text-center">Cant. <span class="text-warning">*</span></th>
                  <th style="width: 140px;" class="text-end">P. Unit. (Bs.) <span class="text-warning">*</span></th>
                  <th style="width: 140px;" class="text-end">Subtotal (Bs.)</th>
                  <th style="width: 50px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="item.idFila">
                  <td class="text-center text-muted fw-bold">{{ idx + 1 }}</td>

                  <!-- Búsqueda predictiva -->
                  <td class="position-relative">
                    <input
                      :id="`input-med-${idx}`"
                      v-model="item.busqueda"
                      type="text"
                      class="form-control form-control-sm"
                      :class="{ 'is-invalid': intentoGuardar && !item.producto_id }"
                      placeholder="Escriba código o nombre..."
                      autocomplete="off"
                      @input="buscarMedicamentos(item, idx)"
                      @focus="buscarMedicamentos(item, idx)"
                      @keydown.escape="cerrarResultados(item)"
                    >

                    <!-- Desplegable flotante Teleport -->
                    <Teleport to="body">
                      <div
                        v-if="item.resultados?.length"
                        class="reembolso-dropdown-resultados shadow-lg"
                        :style="item.dropdownStyle"
                      >
                        <div class="px-3 py-1 bg-light border-bottom small fw-bold text-muted d-flex justify-content-between">
                          <span>Medicamentos encontrados ({{ item.resultados.length }})</span>
                          <button type="button" class="btn-close btn-close-sm" @click="cerrarResultados(item)"></button>
                        </div>
                        <button
                          v-for="med in item.resultados"
                          :key="med.id"
                          type="button"
                          class="dropdown-item p-2 border-bottom text-wrap"
                          @click="seleccionarMedicamento(item, med)"
                        >
                          <div class="fw-bold text-csc-blue">{{ med.codigo }} - {{ med.nombre }}</div>
                          <div class="small text-muted">
                            <span v-if="med.forma_farmaceutica" class="badge bg-light text-dark border me-1">{{ med.forma_farmaceutica }}</span>
                            <span v-if="med.concentracion" class="me-2">{{ med.concentracion }}</span>
                            <span v-if="med.partida_presupuestaria?.codigo" class="text-secondary">Partida: {{ med.partida_presupuestaria.codigo }}</span>
                          </div>
                        </button>
                      </div>
                    </Teleport>

                    <div v-if="item.producto_id" class="small text-success mt-1 d-flex align-items-center">
                      <span class="me-1">✓</span> {{ item.producto_nombre }}
                    </div>
                  </td>

                  <!-- Partida / Código LINAME -->
                  <td>
                    <span class="badge bg-light text-dark border d-block text-truncate mb-1">
                      {{ item.producto_partida || '—' }}
                    </span>
                    <small class="text-muted font-monospace">{{ item.producto_codigo || '—' }}</small>
                  </td>

                  <!-- N.º Factura / Paciente / Recibo -->
                  <td>
                    <input
                      v-model.trim="item.codigo_lote_referencia"
                      type="text"
                      class="form-control form-control-sm"
                      placeholder="Ej. Fact. 4022 / Carlos Ruiz"
                      maxlength="100"
                    >
                  </td>

                  <!-- Cantidad -->
                  <td>
                    <input
                      v-model.number="item.cantidad"
                      type="number"
                      min="1"
                      step="1"
                      class="form-control form-control-sm text-center fw-semibold"
                      :class="{ 'is-invalid': intentoGuardar && (!item.cantidad || item.cantidad <= 0) }"
                      @input="recalcular"
                    >
                  </td>

                  <!-- Precio Unitario de Compra -->
                  <td>
                    <input
                      v-model.number="item.precio_unitario"
                      type="number"
                      min="0"
                      step="0.01"
                      class="form-control form-control-sm text-end fw-semibold"
                      :class="{ 'is-invalid': intentoGuardar && (item.precio_unitario === null || item.precio_unitario < 0) }"
                      @input="recalcular"
                    >
                  </td>

                  <!-- Subtotal -->
                  <td class="text-end fw-bold text-csc-blue font-monospace">
                    {{ formatearMoneda(subtotalItem(item)) }}
                  </td>

                  <!-- Quitar fila -->
                  <td class="text-center">
                    <button
                      type="button"
                      class="btn btn-outline-danger btn-sm border-0 rounded-circle"
                      title="Eliminar fila"
                      :disabled="form.items.length === 1"
                      @click="quitarFila(idx)"
                    >
                      ✕
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot class="table-light border-top">
                <tr>
                  <td colspan="4" class="text-end fw-bold py-3 text-secondary">
                    Total {{ form.items.length }} ítem(s) de compra ingresados:
                  </td>
                  <td class="text-center fw-bold py-3 text-dark">
                    {{ totalUnidadesIngresadas }} u.
                  </td>
                  <td></td>
                  <td class="text-end fw-bold py-3 fs-6 text-csc-blue font-monospace">
                    {{ formatearMoneda(totalMonetarioGeneral) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-2 d-flex justify-content-between align-items-center">
            <button
              type="button"
              class="btn btn-outline-primary btn-sm"
              @click="agregarFila"
            >
              + Añadir otra factura / medicamento
            </button>
            <small class="text-muted">
              * Puede ingresar el mismo medicamento varias veces con diferentes facturas y precios; el sistema calculará el promedio ponderado.
            </small>
          </div>
        </section>

        <!-- SECCIÓN 3: PANEL DE PREVISUALIZACIÓN Y CONSOLIDACIÓN (CPP) -->
        <section class="mb-4">
          <div class="consolidation-card border rounded-3 p-3 shadow-sm bg-light">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <span class="badge bg-csc-blue text-white mb-1">AUDITORÍA Y CONTROL PREVIO</span>
                <h4 class="mb-0 fw-bold text-csc-blue">
                  3. Previsualización de la Salida Consolidada (CPP)
                </h4>
              </div>
              <div class="text-end">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                  {{ resumenConsolidado.length }} {{ resumenConsolidado.length === 1 ? 'Línea de egreso' : 'Líneas de egreso' }}
                </span>
              </div>
            </div>

            <p class="text-muted small mb-3">
              La salida agrupará los medicamentos repetidos calculando el <strong>Costo Promedio Ponderado (CPP)</strong>:
              <code>CPP = Σ(Cantidad × Precio) / Σ(Cantidad)</code>.
              El stock físico de los lotes regulares de almacén se mantendrá intacto.
            </p>

            <div v-if="resumenConsolidado.length === 0" class="alert alert-secondary text-center py-4 my-2">
              Seleccione medicamentos y cantidades en la tabla superior para visualizar la consolidación automática.
            </div>

            <div v-else class="table-responsive bg-white rounded-2 border">
              <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Código</th>
                    <th>Medicamento Consolidado</th>
                    <th class="text-center">Facturas Asoc.</th>
                    <th class="text-center">Cantidad Total</th>
                    <th class="text-end">CPP Calculado (Bs.)</th>
                    <th class="text-end">Importe Total (Bs.)</th>
                    <th>Estado de Agrupación</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="grupo in resumenConsolidado" :key="grupo.medicamento_id">
                    <td class="font-monospace fw-bold">{{ grupo.codigo }}</td>
                    <td class="fw-semibold text-dark">{{ grupo.nombre }}</td>
                    <td class="text-center">
                      <span
                        class="badge rounded-pill"
                        :class="grupo.numFacturas > 1 ? 'bg-primary' : 'bg-secondary'"
                      >
                        {{ grupo.numFacturas }} {{ grupo.numFacturas === 1 ? 'factura' : 'facturas' }}
                      </span>
                    </td>
                    <td class="text-center fw-bold fs-6">{{ grupo.cantidadTotal }} u.</td>
                    <td class="text-end font-monospace fw-bold text-csc-orange">
                      {{ grupo.cpp.toFixed(4) }} Bs.
                    </td>
                    <td class="text-end font-monospace fw-bold text-csc-blue">
                      {{ formatearMoneda(grupo.importeTotal) }}
                    </td>
                    <td>
                      <span v-if="grupo.numFacturas > 1" class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                        🔄 Consolidado ({{ grupo.numFacturas }} compras agrupadas)
                      </span>
                      <span v-else class="badge bg-light text-muted border">
                        Línea directa
                      </span>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="table-group-divider">
                  <tr class="fw-bold bg-light">
                    <td colspan="3" class="text-end text-uppercase">Gran Total Consolidado a Liquidar:</td>
                    <td class="text-center text-dark fs-6">{{ totalUnidadesIngresadas }} u.</td>
                    <td></td>
                    <td class="text-end text-csc-blue fs-5 font-monospace">
                      {{ formatearMoneda(totalMonetarioGeneral) }}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Gran Total en Palabras -->
            <div class="mt-3 p-3 bg-white border rounded-2 d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted d-block text-uppercase fw-semibold">Monto total expresado en letras:</small>
                <strong class="text-csc-blue">{{ totalEnLetras }} BOLIVIANOS</strong>
              </div>
              <div class="text-end">
                <span class="fs-4 fw-bold text-csc-blue font-monospace">{{ formatearMoneda(totalMonetarioGeneral) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- SECCIÓN 4: ACCIONES Y CONFIRMACIÓN -->
        <section class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top">
          <button
            type="button"
            class="btn btn-outline-secondary"
            :disabled="procesando"
            @click="reiniciarFormulario"
          >
            Limpiar Formulario
          </button>

          <div class="d-flex gap-2">
            <button
              type="submit"
              class="btn btn-csc-orange text-white px-4 py-2 fs-6 fw-bold shadow"
              :disabled="procesando || !esFormularioValido"
            >
              <span v-if="procesando" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              {{ procesando ? 'Procesando Reembolso…' : 'Procesar y Liquidar Reembolso' }}
            </button>
          </div>
        </section>
      </div>
    </form>

    <!-- MODAL DE CONFIRMACIÓN PREVIA -->
    <div v-if="mostrarModalConfirmacion" class="csc-modal-backdrop" @click.self="mostrarModalConfirmacion = false">
      <div class="modal-dialog modal-dialog-centered csc-modal-box">
        <div class="modal-content shadow-lg border-0 rounded-3">
          <div class="modal-header bg-csc-blue text-white">
            <h5 class="modal-title fw-bold">Confirmar Liquidación de Reembolso</h5>
            <button type="button" class="btn-close btn-close-white" @click="mostrarModalConfirmacion = false"></button>
          </div>
          <div class="modal-body p-4">
            <p class="mb-3">
              ¿Está segura de registrar el reembolso mensual para <strong>{{ form.ingreso.almacen }}</strong>
              con fecha <strong>{{ form.ingreso.fecha_ingreso }}</strong>?
            </p>

            <div class="bg-light p-3 rounded-2 border mb-3">
              <ul class="list-unstyled mb-0 small">
                <li class="mb-1"><strong>Facturas ingresadas:</strong> {{ form.items.length }} ítems</li>
                <li class="mb-1"><strong>Líneas consolidadas en egreso:</strong> {{ resumenConsolidado.length }} medicamentos</li>
                <li class="mb-1"><strong>Total unidades:</strong> {{ totalUnidadesIngresadas }} unidades</li>
                <li class="mb-1"><strong>Importe total:</strong> <span class="fw-bold text-csc-blue font-monospace">{{ formatearMoneda(totalMonetarioGeneral) }}</span></li>
                <li><strong>Responsable:</strong> {{ form.ingreso.recibido_por }}</li>
              </ul>
            </div>

            <div class="alert alert-info py-2 px-3 small mb-0">
              ℹ️ Se generará la Nota de Ingreso y el Egreso consolidado automático con CPP en una sola transacción atómica.
            </div>
          </div>
          <div class="modal-footer bg-light border-top">
            <button type="button" class="btn btn-outline-secondary" @click="mostrarModalConfirmacion = false">
              Revisar detalles
            </button>
            <button
              type="button"
              class="btn btn-csc-orange text-white fw-bold px-4"
              :disabled="procesando"
              @click="ejecutarGuardado"
            >
              <span v-if="procesando" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              {{ procesando ? 'Guardando en BD…' : 'Sí, Liquidar Reembolso' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DE COMPROBANTE OFICIAL IMPRIMIBLE -->
    <div v-if="mostrarComprobanteModal" class="csc-modal-backdrop" @click.self="mostrarComprobanteModal = false">
      <div class="modal-dialog modal-xl modal-dialog-scrollable csc-modal-box">
        <div class="modal-content shadow-lg border-0 rounded-3">
          <div class="modal-header bg-csc-blue text-white d-print-none">
            <h5 class="modal-title fw-bold">Comprobante Oficial de Reembolso Mensual</h5>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light btn-sm fw-bold" @click="imprimirComprobante">
                🖨️ Imprimir
              </button>
              <button type="button" class="btn-close btn-close-white" @click="mostrarComprobanteModal = false"></button>
            </div>
          </div>
          <div class="modal-body p-4 comprobante-imprimible-body">
            <!-- Membrete Oficial -->
            <div class="text-center border-bottom pb-3 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-start">
                  <h6 class="mb-0 fw-bold">CAJA DE SALUD DE CAMINOS Y R.A.</h6>
                  <small class="text-muted">DEPARTAMENTO DE ALMACENES Y FARMACIA</small>
                </div>
                <div class="text-end">
                  <span class="badge bg-dark fs-6">{{ ultimoResultado?.ingreso?.numero_nota || 'RMB' }}</span>
                  <div class="small text-muted">Salida N.º {{ ultimoResultado?.salida?.numero_salida }}</div>
                </div>
              </div>
              <h4 class="fw-bold mb-1 text-uppercase text-csc-blue">Liquidación y Reembolso Mensual de Medicamentos</h4>
              <p class="small text-muted mb-0">Consolidación con Costo Promedio Ponderado (CPP) · Compras de Pacientes</p>
            </div>

            <!-- Datos Informativos -->
            <div class="row g-2 mb-3 small bg-light p-3 rounded border">
              <div class="col-sm-4"><strong>Almacén:</strong> {{ form.ingreso.almacen }}</div>
              <div class="col-sm-4"><strong>Fecha:</strong> {{ form.ingreso.fecha_ingreso }}</div>
              <div class="col-sm-4"><strong>Responsable:</strong> {{ form.ingreso.recibido_por }}</div>
              <div class="col-sm-6"><strong>Proveedor / Origen:</strong> {{ form.proveedor.nombre }}</div>
              <div class="col-sm-6"><strong>N.º Remisión / Trámite:</strong> {{ form.ingreso.numero_remision || '—' }}</div>
              <div class="col-12" v-if="form.ingreso.observacion"><strong>Observaciones:</strong> {{ form.ingreso.observacion }}</div>
            </div>

            <!-- Tabla de Consolidación -->
            <h6 class="fw-bold border-bottom pb-1 text-csc-blue">Resumen de Egresos Consolidados (CPP)</h6>
            <table class="table table-sm table-bordered align-middle mb-3 small">
              <thead class="table-light">
                <tr>
                  <th>Código</th>
                  <th>Medicamento</th>
                  <th class="text-center">Cant. Total</th>
                  <th class="text-end">CPP (Bs.)</th>
                  <th class="text-end">Importe Total (Bs.)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="grupo in resumenConsolidado" :key="grupo.medicamento_id">
                  <td class="font-monospace">{{ grupo.codigo }}</td>
                  <td>{{ grupo.nombre }}</td>
                  <td class="text-center fw-bold">{{ grupo.cantidadTotal }}</td>
                  <td class="text-end font-monospace">{{ grupo.cpp.toFixed(4) }}</td>
                  <td class="text-end font-monospace fw-bold">{{ formatearMoneda(grupo.importeTotal) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="fw-bold table-light">
                  <td colspan="2" class="text-end">TOTAL CONSOLIDADO:</td>
                  <td class="text-center">{{ totalUnidadesIngresadas }} u.</td>
                  <td></td>
                  <td class="text-end font-monospace fs-6">{{ formatearMoneda(totalMonetarioGeneral) }}</td>
                </tr>
              </tfoot>
            </table>

            <!-- Tabla Detalle por Factura -->
            <h6 class="fw-bold border-bottom pb-1 text-csc-blue">Detalle de Facturas Registradas (Ingreso Línea por Línea)</h6>
            <table class="table table-sm table-bordered align-middle mb-4 small">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Medicamento</th>
                  <th>N.º Factura / Paciente</th>
                  <th class="text-center">Cant.</th>
                  <th class="text-end">P. Unit. (Bs.)</th>
                  <th class="text-end">Subtotal (Bs.)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="item.idFila">
                  <td class="text-center">{{ idx + 1 }}</td>
                  <td>{{ item.producto_nombre || item.busqueda }}</td>
                  <td>{{ item.codigo_lote_referencia || '—' }}</td>
                  <td class="text-center">{{ item.cantidad }}</td>
                  <td class="text-end font-monospace">{{ formatearMoneda(item.precio_unitario) }}</td>
                  <td class="text-end font-monospace">{{ formatearMoneda(subtotalItem(item)) }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Firmas de Conformidad -->
            <div class="row pt-5 mt-4 text-center small">
              <div class="col-4">
                <div class="border-top pt-2">
                  <strong>{{ form.ingreso.recibido_por }}</strong><br>
                  <span>Responsable de Almacén</span>
                </div>
              </div>
              <div class="col-4">
                <div class="border-top pt-2">
                  <strong>Dra. Carmen</strong><br>
                  <span>Farmacia / Almacenes Regional</span>
                </div>
              </div>
              <div class="col-4">
                <div class="border-top pt-2">
                  <strong>Auditoría / Contabilidad</strong><br>
                  <span>Caja de Salud de Caminos</span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light d-print-none">
            <button type="button" class="btn btn-secondary" @click="mostrarComprobanteModal = false">Cerrar</button>
            <button type="button" class="btn btn-primary" @click="imprimirComprobante">Imprimir Comprobante</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  recibidoPor: {
    type: String,
    default: 'Dra. Carmen',
  },
  regional: {
    type: String,
    default: 'La Paz',
  },
});

let idContador = 1;

const nuevaFila = () => ({
  idFila: idContador++,
  producto_id: null,
  producto_codigo: '',
  producto_nombre: '',
  producto_partida: '',
  busqueda: '',
  codigo_lote_referencia: '',
  fecha_vencimiento: null,
  cantidad: 1,
  precio_unitario: 0.00,
  resultados: [],
  dropdownStyle: {},
});

const nombreAlmacenRegional = () => {
  const reg = String(props.regional || 'La Paz').trim();
  return reg ? `REGIONAL ${reg.toUpperCase()}` : 'REGIONAL LA PAZ';
};

const fechaHoy = () => new Date().toISOString().slice(0, 10);

const nuevoFormulario = () => ({
  proveedor: {
    nombre: 'VARIOS PACIENTES',
    telefono: '',
  },
  ingreso: {
    almacen: nombreAlmacenRegional(),
    fecha_ingreso: fechaHoy(),
    numero_remision: '',
    numero_factura: '',
    observacion: 'Reembolso mensual de facturas adquiridas por pacientes externamente.',
    recibido_por: props.recibidoPor || 'Dra. Carmen',
  },
  items: [nuevaFila()],
});

const form = ref(nuevoFormulario());
const procesando = ref(false);
const intentoGuardar = ref(false);
const mensajeExito = ref('');
const errorGeneral = ref('');
const ultimoResultado = ref(null);
const mostrarModalConfirmacion = ref(false);
const mostrarComprobanteModal = ref(false);

let searchTimer = null;

// Métodos de Tabla y Búsqueda
const agregarFila = () => {
  form.value.items.push(nuevaFila());
};

const quitarFila = (index) => {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1);
  }
};

const subtotalItem = (item) => {
  const c = Number(item.cantidad) || 0;
  const p = Number(item.precio_unitario) || 0;
  return Math.round(c * p * 100) / 100;
};

const posicionarDropdown = async (item, indice) => {
  await nextTick();
  const input = document.getElementById(`input-med-${indice}`);
  if (!input || !item.resultados?.length) return;
  const rect = input.getBoundingClientRect();
  const margen = 8;
  const alturaEstimada = Math.min(260, Math.max(72, item.resultados.length * 64));
  const ancho = Math.min(Math.max(rect.width, 360), window.innerWidth - (margen * 2));
  const left = Math.min(Math.max(rect.left, margen), window.innerWidth - ancho - margen);
  const espacioAbajo = window.innerHeight - rect.bottom;
  const mostrarArriba = espacioAbajo < alturaEstimada + margen && rect.top > alturaEstimada + margen;
  const top = mostrarArriba ? Math.max(margen, rect.top - alturaEstimada - 4) : rect.bottom + 4;

  item.dropdownStyle = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    width: `${ancho}px`,
    zIndex: 1060,
  };
};

const cerrarResultados = (item) => {
  item.resultados = [];
  item.dropdownStyle = {};
};

const buscarMedicamentos = (item, indice) => {
  clearTimeout(searchTimer);
  const q = String(item.busqueda || '').trim();
  if (q.length < 2) {
    cerrarResultados(item);
    return;
  }

  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/medicamentos', { params: { buscar: q } });
      item.resultados = data;
      await posicionarDropdown(item, indice);
    } catch {
      cerrarResultados(item);
    }
  }, 220);
};

const seleccionarMedicamento = (item, med) => {
  item.producto_id = med.id;
  item.producto_codigo = med.codigo;
  item.producto_nombre = med.nombre;
  item.producto_partida = med.partida_presupuestaria?.codigo || '';
  item.busqueda = `${med.codigo} - ${med.nombre}`;
  cerrarResultados(item);
};

const recalcular = () => {
  // Forzar reactividad del resumen
};

// Resumen Consolidado con Costo Promedio Ponderado (CPP)
const resumenConsolidado = computed(() => {
  const mapa = new Map();

  form.value.items.forEach(item => {
    if (!item.producto_id) return;

    const mid = item.producto_id;
    const cant = Number(item.cantidad) || 0;
    const pu   = Number(item.precio_unitario) || 0;
    const imp  = cant * pu;

    if (!mapa.has(mid)) {
      mapa.set(mid, {
        medicamento_id: mid,
        codigo: item.producto_codigo,
        nombre: item.producto_nombre,
        partida: item.producto_partida,
        cantidadTotal: 0,
        importeTotal: 0,
        numFacturas: 0,
      });
    }

    const reg = mapa.get(mid);
    reg.cantidadTotal += cant;
    reg.importeTotal  += imp;
    reg.numFacturas   += 1;
  });

  return Array.from(mapa.values()).map(g => {
    const cpp = g.cantidadTotal > 0 ? (g.importeTotal / g.cantidadTotal) : 0;
    return {
      ...g,
      cpp,
    };
  });
});

const totalUnidadesIngresadas = computed(() => {
  return form.value.items.reduce((acc, item) => acc + (Number(item.cantidad) || 0), 0);
});

const totalMonetarioGeneral = computed(() => {
  return form.value.items.reduce((acc, item) => acc + subtotalItem(item), 0);
});

const esFormularioValido = computed(() => {
  if (!form.value.ingreso.fecha_ingreso || !form.value.ingreso.almacen || !form.value.ingreso.recibido_por) {
    return false;
  }
  if (!form.value.items.length) return false;

  return form.value.items.every(i =>
    Boolean(i.producto_id) &&
    Number(i.cantidad) > 0 &&
    Number(i.precio_unitario) >= 0
  );
});

// Conversión a Moneda y Letras
const formatearMoneda = (val) => {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
  }).format(val || 0);
};

// Algoritmo para número a letras en Bolivianos
const totalEnLetras = computed(() => {
  const monto = totalMonetarioGeneral.value;
  const enteros = Math.floor(monto);
  const centavos = String(Math.round((monto - enteros) * 100)).padStart(2, '0');

  const unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
  const decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
  const especiales = {
    11: 'ONCE', 12: 'DOCE', 13: 'TRECE', 14: 'CATORCE', 15: 'QUINCE',
    16: 'DIECISÉIS', 17: 'DIECISIETE', 18: 'DIECIOCHO', 19: 'DIECINUEVE',
    21: 'VEINTIUNO', 22: 'VEINTIDÓS', 23: 'VEINTITRÉS', 24: 'VEINTICUATRO',
    25: 'VEINTICINCO', 26: 'VEINTISÉIS', 27: 'VEINTISIETE', 28: 'VEINTIOCHO', 29: 'VEINTINUEVE',
  };
  const centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

  function convertirCentenas(num) {
    if (num === 100) return 'CIEN';
    let c = Math.floor(num / 100);
    let d = Math.floor((num % 100) / 10);
    let u = num % 10;
    let res = '';
    if (c > 0) res += centenas[c] + ' ';
    let resto = num % 100;
    if (especiales[resto]) {
      res += especiales[resto];
    } else {
      if (d > 0) {
        res += decenas[d];
        if (u > 0) res += ' Y ' + unidades[u];
      } else if (u > 0) {
        res += unidades[u];
      }
    }
    return res.trim();
  }

  function convertirMiles(num) {
    if (num === 0) return 'CERO';
    if (num < 1000) return convertirCentenas(num);
    let miles = Math.floor(num / 1000);
    let resto = num % 1000;
    let txtMiles = miles === 1 ? 'MIL' : convertirCentenas(miles) + ' MIL';
    let txtResto = resto > 0 ? ' ' + convertirCentenas(resto) : '';
    return (txtMiles + txtResto).trim();
  }

  const literal = enteros === 0 ? 'CERO' : convertirMiles(enteros);
  return `${literal} CON ${centavos}/100`;
});

// Guardado y Envío
const solicitarConfirmacion = () => {
  intentoGuardar.value = true;
  errorGeneral.value = '';

  if (!esFormularioValido.value) {
    errorGeneral.value = 'Por favor complete todos los campos requeridos en la tabla (Medicamento, Cantidad mayor a 0 y Precio).';
    return;
  }

  mostrarModalConfirmacion.value = true;
};

const ejecutarGuardado = async () => {
  mostrarModalConfirmacion.value = false;
  procesando.value = true;
  errorGeneral.value = '';
  mensajeExito.value = '';

  const payload = {
    proveedor: {
      nombre: form.value.proveedor.nombre || 'VARIOS PACIENTES',
      telefono: form.value.proveedor.telefono || null,
    },
    ingreso: {
      almacen: form.value.ingreso.almacen,
      fecha_ingreso: form.value.ingreso.fecha_ingreso,
      numero_remision: form.value.ingreso.numero_remision || null,
      numero_factura: form.value.ingreso.numero_factura || null,
      observacion: form.value.ingreso.observacion || null,
      recibido_por: form.value.ingreso.recibido_por,
    },
    items: form.value.items.map(item => ({
      producto_id: item.producto_id,
      cantidad: Number(item.cantidad),
      precio_unitario: Number(item.precio_unitario),
      codigo_lote_referencia: item.codigo_lote_referencia || null,
      fecha_vencimiento: item.fecha_vencimiento || null,
    })),
  };

  try {
    const { data } = await axios.post('api/reembolsos', payload);

    ultimoResultado.value = data;
    mensajeExito.value = `${data.message} (Nota Ingreso: ${data.ingreso?.numero_nota}, Salida Consolidada N.º ${data.salida?.numero_salida}).`;

    // Scroll arriba para ver la confirmación
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    if (err.response?.data?.errors) {
      const errs = Object.values(err.response.data.errors).flat();
      errorGeneral.value = errs.join(' · ');
    } else {
      errorGeneral.value = err.response?.data?.message || 'Error al procesar el reembolso en el servidor.';
    }
  } finally {
    procesando.value = false;
  }
};

const descargarPdfIngreso = () => {
  const ingresoId = ultimoResultado.value?.ingreso?.id;
  if (!ingresoId) return;
  window.open(`api/ingresos/${ingresoId}/pdf`, '_blank');
};

const abrirComprobanteImprimible = () => {
  mostrarComprobanteModal.value = true;
};

const imprimirComprobante = () => {
  window.print();
};

const reiniciarFormulario = () => {
  form.value = nuevoFormulario();
  intentoGuardar.value = false;
  errorGeneral.value = '';
  // Mantenemos ultimoResultado para no perder botones si se desea volver a imprimir
};

// Listeners globales
const handleClickFuera = (e) => {
  if (!e.target.closest('.reembolso-dropdown-resultados') && !e.target.closest('input[id^="input-med-"]')) {
    form.value.items.forEach(cerrarResultados);
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickFuera);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickFuera);
  clearTimeout(searchTimer);
});
</script>

<style scoped>
.reembolso-container {
  max-width: 1400px;
  margin: 0 auto;
}

.reembolso-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(11, 61, 98, 0.08);
  border: 1px solid #e1e7ed;
  overflow: hidden;
}

.reembolso-hero {
  background: linear-gradient(135deg, var(--csc-blue-dark, #0b3d62) 0%, var(--csc-blue, #164f78) 100%);
  color: #ffffff;
  padding: 1.75rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  border-bottom: 4px solid var(--csc-orange, #e85d04);
}

.badge-modulo {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #ffd8a8;
  margin-bottom: 0.25rem;
}

.reembolso-hero h2 {
  font-size: 1.6rem;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.reembolso-hero p {
  font-size: 0.9rem;
  opacity: 0.9;
  max-width: 820px;
  margin-bottom: 0;
  line-height: 1.4;
}

.hero-badge-box {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 8px;
  padding: 0.75rem 1.25rem;
  text-align: center;
  min-width: 190px;
  backdrop-filter: blur(4px);
}

.badge-label {
  display: block;
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  color: #ffd8a8;
}

.hero-badge-box strong {
  display: block;
  font-size: 1.05rem;
  color: #ffffff;
}

.section-kicker {
  font-size: 0.75rem;
  color: var(--csc-orange, #e85d04);
  letter-spacing: 0.06em;
}

.text-csc-blue {
  color: var(--csc-blue, #164f78) !important;
}

.bg-csc-blue {
  background-color: var(--csc-blue, #164f78) !important;
}

.table-csc-header {
  background-color: var(--csc-blue, #164f78);
}

.btn-csc-orange {
  background-color: var(--csc-orange, #e85d04);
  border-color: var(--csc-orange, #e85d04);
  transition: all 0.2s ease-in-out;
}

.btn-csc-orange:hover:not(:disabled) {
  background-color: var(--csc-orange-hover, #d94f00);
  border-color: var(--csc-orange-hover, #d94f00);
  transform: translateY(-1px);
}

.consolidation-card {
  border-left: 5px solid var(--csc-blue, #164f78) !important;
}

.reembolso-dropdown-resultados {
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid #ced4da;
  max-height: 280px;
  overflow-y: auto;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.dropdown-item:hover {
  background-color: #f0f7fc;
  cursor: pointer;
}

.csc-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(11, 61, 98, 0.6);
  backdrop-filter: blur(3px);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.csc-modal-box {
  width: 100%;
  max-width: 900px;
}

@media print {
  body * {
    visibility: hidden;
  }
  .comprobante-imprimible-body,
  .comprobante-imprimible-body * {
    visibility: visible;
  }
  .comprobante-imprimible-body {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 1.5cm;
  }
}
</style>
