<template>
  <section class="reports-page">
    <div class="reports-hero">
      <h2>Reportes y documentos</h2>
      <p>Genere nuevamente documentos históricos, edite notas de salida no cerradas y descargue reportes del almacén.</p>
    </div>

    <!-- Alerta de éxito global -->
    <div v-if="mensajeExito" class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <strong>{{ mensajeExito }}</strong>
          <div v-if="salidaActualizadaId" class="mt-2 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" @click="descargar('api/reportes/salidas/' + salidaActualizadaId + '/pdf')">
              📄 Descargar PDF Salida Actualizada
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" @click="descargar('api/reportes/salidas/' + salidaActualizadaId + '/excel')">
              📊 Descargar Excel Salida Actualizada
            </button>
          </div>
          <div v-if="ingresoActualizadoId" class="mt-2 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" @click="descargar('api/ingresos/' + ingresoActualizadoId + '/pdf')">
              📄 Descargar PDF Ingreso Actualizado
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" @click="descargar('api/reportes/ingresos/' + ingresoActualizadoId + '/excel')">
              📊 Descargar Excel Ingreso Actualizado
            </button>
          </div>
        </div>
        <button type="button" class="btn-close" aria-label="Cerrar" @click="mensajeExito = ''"></button>
      </div>
    </div>

    <div class="report-grid">
      <!-- Tarjeta 1: Notas de ingreso -->
      <article class="report-card">
        <div class="report-title">Notas de ingreso</div>
        <p>Busque por N.º de nota o documento de remisión. Puede editar notas de periodos abiertos o descargar PDF/Excel.</p>
        <input v-model="busIngreso" @input="cargarIngresos" class="form-control" placeholder="Ej.: N.º 1 o remisión">
        <div class="results">
          <div v-for="i in ingresos" :key="i.id" class="result-row">
            <div>
              <strong>{{ i.numero_nota }}</strong>
              <span>{{ i.proveedor || 'Sin proveedor' }} · {{ i.fecha }}<template v-if="i.numero_remision"> · Remisión: {{ i.numero_remision }}</template></span>
            </div>
            <div class="result-actions">
              <button
                v-if="i.puede_editar"
                type="button"
                class="btn-edit"
                title="Editar esta nota de ingreso"
                @click="abrirEditarIngreso(i.id)"
              >
                ✏️ Editar
              </button>
              <span v-else-if="i.periodo_cerrado" class="badge-lock" title="Mes cerrado; no admite modificaciones">🔒 Cerrado</span>
              <span v-else-if="i.tipo_ingreso === 'reembolso' || i.tipo_ingreso === 'apertura'" class="badge-lock" title="Tipo no editable">{{ i.tipo_ingreso === 'reembolso' ? 'Reembolso' : 'Apertura' }}</span>
              <button class="btn-orange" @click="descargar('api/ingresos/' + i.id + '/pdf')">PDF</button>
              <button class="btn-outline" @click="descargar('api/reportes/ingresos/' + i.id + '/excel')">Excel</button>
            </div>
          </div>
          <div v-if="busIngreso && !ingresos.length" class="empty">Sin coincidencias.</div>
        </div>
      </article>

      <!-- Tarjeta 2: Notas de salida -->
      <article class="report-card">
        <div class="report-title">Notas de salida</div>
        <p>Busque por número de salida o pedido. Puede editar notas de periodos abiertos o descargar PDF/Excel.</p>
        <input v-model="busSalida" @input="cargarSalidas" class="form-control" placeholder="Ej.: 12 o N.º pedido">
        <div class="results">
          <div v-for="s in salidas" :key="s.id" class="result-row">
            <div>
              <strong>Salida N.º {{ s.numero_salida }}</strong>
              <span>{{ s.destino || 'Sin destino' }} · {{ s.fecha }}<template v-if="s.numero_pedido"> · Pedido: {{ s.numero_pedido }}</template></span>
            </div>
            <div class="result-actions">
              <button
                v-if="s.puede_editar"
                type="button"
                class="btn-edit"
                title="Editar esta nota de salida"
                @click="abrirEditarSalida(s.id)"
              >
                ✏️ Editar
              </button>
              <span v-else-if="s.periodo_cerrado" class="badge-lock" title="Mes cerrado; no admite modificaciones">🔒 Cerrado</span>
              <span v-else-if="s.tipo_salida === 'EGRESO_REEMBOLSO'" class="badge-lock" title="Egreso de reembolso contable">Reembolso</span>
              <button class="btn-orange" @click="descargar('api/reportes/salidas/' + s.id + '/pdf')">PDF</button>
              <button class="btn-outline" @click="descargar('api/reportes/salidas/' + s.id + '/excel')">Excel</button>
            </div>
          </div>
          <div v-if="busSalida && !salidas.length" class="empty">Sin coincidencias.</div>
        </div>
      </article>

      <!-- Tarjeta 3: Reporte de inventario -->
      <article class="report-card report-wide">
        <div class="report-title">Reporte de inventario</div>
        <p>Busque y agregue uno o varios productos para generar un reporte específico, o genere el inventario completo.</p>
        <div class="filter-row">
          <div class="search-wrap">
            <input v-model="inv.buscar" @input="buscarInventarioProductos" class="form-control" placeholder="Buscar producto / LINAME para agregar">
            <div v-if="invSugerencias.length" class="suggestions">
              <button v-for="p in invSugerencias" :key="p.id" @click="seleccionarInventarioProducto(p)">
                <strong>{{ p.codigo }}</strong> — {{ p.nombre }}
              </button>
            </div>
          </div>
        </div>
        <div v-if="productosInventario.length" class="inventory-selection">
          <div class="inventory-head">
            <strong>Productos seleccionados para el reporte</strong>
            <span>{{ productosInventario.length}} producto(s)</span>
          </div>
          <div class="inventory-table-wrap">
            <table class="inventory-table">
              <thead>
                <tr>
                  <th>LINAME / Código</th>
                  <th>Producto</th>
                  <th>Partida</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(p, index) in productosInventario" :key="p.id">
                  <td>{{ p.codigo }}</td>
                  <td v-if="editandoInventario !== index">{{ p.nombre }}</td>
                  <td v-else><input v-model="edicionInventario.nombre" class="table-input"></td>
                  <td>{{ p.partida_presupuestaria?.codigo || p.partida || '—' }}</td>
                  <td class="table-actions">
                    <button class="edit-btn" @click="editarInventario(index)">{{ editandoInventario === index ? 'Guardar' : 'Editar' }}</button>
                    <button class="delete-btn" @click="eliminarInventario(index)">Eliminar</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="actions" v-if="productosInventario.length">
          <button class="btn-orange" @click="generarSeleccionados('pdf')">Generar PDF seleccionados</button>
          <button class="btn-outline" @click="generarSeleccionados('excel')">Generar EXCEL seleccionados</button>
        </div>
        <div class="complete-actions">
          <strong>Inventario completo</strong>
          <span>Incluye todos los productos registrados en el inventario al momento de generar el reporte.</span>
          <div class="actions">
            <button class="btn-orange" @click="descargar('api/reportes/inventario/pdf')">Generar inventario completo PDF</button>
            <button class="btn-outline" @click="descargar('api/reportes/inventario/excel')">Generar inventario completo EXCEL</button>
          </div>
        </div>
      </article>

      <!-- Tarjeta 4: Kardex por producto -->
      <article class="report-card report-wide">
        <div class="report-title">Kardex por producto</div>
        <p>Seleccione un producto y, opcionalmente, un rango de fechas.</p>
        <div class="filter-row">
          <div class="search-wrap">
            <input v-model="textoProducto" @input="buscarProductos" class="form-control" placeholder="Buscar por LINAME o nombre">
            <div v-if="sugerencias.length" class="suggestions">
              <button v-for="p in sugerencias" :key="p.id" @click="seleccionarProducto(p)">
                <strong>{{ p.codigo }}</strong> — {{ p.nombre }}
              </button>
            </div>
          </div>
          <input v-model="desde" type="date" class="form-control">
          <input v-model="hasta" type="date" class="form-control">
        </div>
        <div class="selected" v-if="producto">{{ producto.codigo }} — {{ producto.nombre }}</div>
        <div class="actions">
          <button class="btn-orange" :disabled="!producto" @click="descargar('api/reportes/kardex/pdf', { producto_id: producto?.id, desde, hasta })">
            Generar Kardex PDF
          </button>
        </div>
      </article>
    </div>

    <!-- MODAL DE EDICIÓN DE NOTA DE SALIDA -->
    <div v-if="mostrarModalEdicion" class="modal-backdrop-custom" @click.self="cerrarEditarSalida">
      <div class="modal-dialog-custom shadow-lg">
        <div class="modal-header-custom bg-csc-blue text-white">
          <div class="d-flex align-items-center gap-2">
            <span class="fs-5">✏️</span>
            <div>
              <h5 class="m-0 fw-bold">Editar Nota de Salida N.º {{ formEdicion.numero_salida }}</h5>
              <small class="text-white-50">El número correlativo original se conserva intacto</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" @click="cerrarEditarSalida"></button>
        </div>

        <div class="modal-body-custom p-4">
          <div v-if="errorEdicion" class="alert alert-danger py-2 px-3 small mb-3">
            {{ errorEdicion }}
          </div>

          <div v-if="cargandoDetallesEdicion" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Cargando datos de la salida...</div>
          </div>

          <form v-else @submit.prevent="guardarEdicionSalida">
            <!-- Sección 1: Datos de Cabecera -->
            <div class="card p-3 mb-3 bg-light border">
              <div class="fw-bold text-csc-blue mb-2 small text-uppercase">1. Datos generales de la nota</div>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Fecha de salida</label>
                  <input v-model="formEdicion.fecha_salida" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">N.º de salida</label>
                  <input :value="'Salida N.º ' + formEdicion.numero_salida" type="text" class="form-control form-control-sm fw-bold bg-white" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Almacén de origen</label>
                  <input v-model.trim="formEdicion.almacen_origen" type="text" class="form-control form-control-sm" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Destino / Unidad solicitante</label>
                  <div class="position-relative">
                    <input
                      v-model="formEdicion.destino_establecimiento"
                      type="text"
                      class="form-control form-control-sm"
                      placeholder="Buscar o escribir unidad..."
                      autocomplete="off"
                      @focus="mostrarSugerenciasDestino = true"
                    >
                    <div v-if="mostrarSugerenciasDestino && unidadesFiltradas.length" class="suggestions-dropdown shadow">
                      <button
                        v-for="u in unidadesFiltradas"
                        :key="u"
                        type="button"
                        class="dropdown-item small py-1 px-2 border-bottom"
                        @mousedown.prevent="seleccionarUnidadDestino(u)"
                      >
                        {{ u }}
                      </button>
                    </div>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">N.º de pedido / documento</label>
                  <input v-model.trim="formEdicion.numero_pedido" type="text" class="form-control form-control-sm" placeholder="Ej. PED-2026-089">
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Solicitado por</label>
                  <input v-model.trim="formEdicion.solicitado_por" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Entregado a</label>
                  <input v-model.trim="formEdicion.entregado_a" type="text" class="form-control form-control-sm">
                </div>

                <div class="col-12">
                  <label class="form-label small fw-semibold">Observaciones</label>
                  <textarea v-model.trim="formEdicion.observaciones" class="form-control form-control-sm" rows="2" placeholder="Observaciones de la nota"></textarea>
                </div>
              </div>
            </div>

            <!-- Sección 2: Agregar nuevo ítem que faltaba -->
            <div class="card p-3 mb-3 border border-warning-subtle bg-white">
              <div class="fw-bold text-csc-orange mb-2 small text-uppercase">
                + Agregar ítem que faltaba en la salida
              </div>
              <div class="row g-2 align-items-end">
                <div class="col-md-5 position-relative">
                  <label class="form-label small fw-semibold">Buscar producto</label>
                  <input
                    v-model="busquedaMedEdicion"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Código o nombre..."
                    autocomplete="off"
                    @input="buscarMedicamentosEdicion"
                  >
                  <div v-if="sugerenciasMedEdicion.length" class="suggestions-dropdown shadow">
                    <button
                      v-for="m in sugerenciasMedEdicion"
                      :key="m.id"
                      type="button"
                      class="dropdown-item small p-2 border-bottom text-wrap"
                      @mousedown.prevent="seleccionarMedEdicion(m)"
                    >
                      <strong>{{ m.codigo }}</strong> - {{ m.nombre }}
                      <small v-if="m.concentracion" class="d-block text-muted">{{ m.concentracion }}</small>
                    </button>
                  </div>
                  <small v-if="medEdicionSeleccionado" class="text-success fw-bold d-block mt-1">
                    ✓ {{ medEdicionSeleccionado.nombre }}
                  </small>
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Lote disponible</label>
                  <select
                    v-model="loteEdicionId"
                    class="form-select form-select-sm"
                    :disabled="!medEdicionSeleccionado || cargandoLotesEdicion"
                  >
                    <option value="" disabled>{{ !medEdicionSeleccionado ? 'Seleccione medicamento' : cargandoLotesEdicion ? 'Cargando lotes...' : 'Seleccione lote...' }}</option>
                    <option v-for="l in lotesDisponiblesEdicion" :key="l.id" :value="l.id">
                      {{ l.codigo_lote }} — Disp: {{ l.cantidad_disponible ?? l.cantidad_actual }} u. — Vence: {{ l.fecha_vencimiento || 'N/A' }}
                    </option>
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Cantidad</label>
                  <input
                    v-model.number="cantidadNuevoItem"
                    type="number"
                    min="1"
                    :max="loteEdicionSeleccionado?.cantidad_disponible || loteEdicionSeleccionado?.cantidad_actual || undefined"
                    class="form-control form-control-sm text-center fw-bold"
                    :disabled="!loteEdicionId"
                  >
                </div>

                <div class="col-md-1">
                  <button
                    type="button"
                    class="btn btn-warning btn-sm w-100 fw-bold"
                    :disabled="!puedeAgregarItemEdicion"
                    @click="agregarItemEdicion"
                    title="Añadir a la lista"
                  >
                    + Añadir
                  </button>
                </div>
              </div>
            </div>

            <!-- Sección 3: Detalle de ítems de la salida -->
            <div class="card p-3 mb-3 border">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-csc-blue small text-uppercase">
                  2. Ítems incluidos en la salida ({{ formEdicion.detalles.length }})
                </span>
                <span class="badge bg-secondary">
                  Total: {{ totalUnidadesEdicion }} unidades
                </span>
              </div>

              <div class="table-responsive bg-white rounded border">
                <table class="table table-sm table-hover align-middle mb-0 small">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 35px;" class="text-center">#</th>
                      <th>Medicamento</th>
                      <th>Lote</th>
                      <th>Vencimiento</th>
                      <th class="text-end">P. Unitario</th>
                      <th style="width: 130px;" class="text-center">Cantidad</th>
                      <th class="text-end">Subtotal</th>
                      <th style="width: 45px;" class="text-center"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in formEdicion.detalles" :key="item.lote_id">
                      <td class="text-center text-muted fw-bold">{{ idx + 1 }}</td>
                      <td>
                        <strong>{{ item.medicamento?.nombre || item.nombre }}</strong>
                        <small class="d-block text-muted font-monospace">{{ item.medicamento?.codigo || item.codigo }}</small>
                      </td>
                      <td>{{ item.codigo_lote }}</td>
                      <td>{{ item.fecha_vencimiento || 'No aplica' }}</td>
                      <td class="text-end font-monospace">{{ monedaPrecio(item.precio_unitario) }}</td>
                      <td class="text-center">
                        <input
                          v-model.number="item.cantidad"
                          type="number"
                          min="1"
                          :max="item.stock_disponible || undefined"
                          class="form-control form-control-sm text-center fw-bold"
                          required
                        >
                        <small v-if="item.stock_disponible" class="text-muted d-block" style="font-size: 0.7rem;">
                          Máx: {{ item.stock_disponible }}
                        </small>
                      </td>
                      <td class="text-end font-monospace fw-bold text-csc-blue">
                        {{ moneda(round(item.cantidad * item.precio_unitario, 2)) }}
                      </td>
                      <td class="text-center">
                        <button
                          type="button"
                          class="btn btn-outline-danger btn-sm border-0 rounded-circle"
                          title="Quitar ítem"
                          :disabled="formEdicion.detalles.length === 1"
                          @click="eliminarItemEdicion(idx)"
                        >
                          ✕
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot class="table-light border-top fw-bold">
                    <tr>
                      <td colspan="5" class="text-end">TOTAL DE LA NOTA:</td>
                      <td class="text-center">{{ totalUnidadesEdicion }} u.</td>
                      <td class="text-end font-monospace text-csc-blue">{{ moneda(totalValorEdicion) }}</td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" @click="cerrarEditarSalida" :disabled="guardandoEdicion">
                Cancelar
              </button>
              <button type="submit" class="btn btn-csc-orange btn-sm text-white px-4 fw-bold shadow-sm" :disabled="guardandoEdicion || !formEdicion.detalles.length">
                <span v-if="guardandoEdicion" class="spinner-border spinner-border-sm me-1"></span>
                {{ guardandoEdicion ? 'Guardando cambios...' : 'Guardar y Actualizar Salida' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal para Editar Nota de Ingreso -->
    <div v-if="mostrarModalEdicionIngreso" class="modal-backdrop-custom">
      <div class="modal-card-custom shadow-lg">
        <div class="modal-header-custom d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <span class="fs-4">✏️</span>
            <div>
              <h5 class="mb-0 fw-bold">Editar nota de ingreso — {{ formEdicionIngreso.numero_nota }}</h5>
              <small class="text-white-50">Subsanar errores u omisiones de ítems sin alterar el correlativo original</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" @click="cerrarEditarIngreso"></button>
        </div>

        <div class="modal-body-custom p-4">
          <div v-if="errorEdicionIngreso" class="alert alert-danger py-2 px-3 small mb-3">
            {{ errorEdicionIngreso }}
          </div>

          <div v-if="cargandoDetallesEdicionIngreso" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-muted">Cargando datos de la nota de ingreso...</div>
          </div>

          <form v-else @submit.prevent="guardarEdicionIngreso">
            <!-- Sección 1: Datos de Cabecera -->
            <div class="card p-3 mb-3 bg-light border">
              <div class="fw-bold text-csc-blue mb-2 small text-uppercase">1. Datos generales de la nota</div>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Fecha de ingreso</label>
                  <input v-model="formEdicionIngreso.fecha_ingreso" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">N.º de nota (Correlativo)</label>
                  <input :value="formEdicionIngreso.numero_nota" type="text" class="form-control form-control-sm fw-bold bg-white" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Almacén de destino</label>
                  <input v-model.trim="formEdicionIngreso.almacen" type="text" class="form-control form-control-sm" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Procedencia / Proveedor</label>
                  <input
                    v-model.trim="formEdicionIngreso.proveedor_nombre"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Nombre del proveedor..."
                    required
                  >
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Teléfono proveedor</label>
                  <input v-model.trim="formEdicionIngreso.proveedor_telefono" type="text" class="form-control form-control-sm" placeholder="Opcional">
                </div>

                <div class="col-md-4">
                  <label class="form-label small fw-semibold">N.º de remisión</label>
                  <input v-model.trim="formEdicionIngreso.numero_remision" type="text" class="form-control form-control-sm" placeholder="Ej. REM-001">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">N.º de factura</label>
                  <input v-model.trim="formEdicionIngreso.numero_factura" type="text" class="form-control form-control-sm" placeholder="Ej. FAC-001">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">N.º Orden de Compra</label>
                  <input v-model.trim="formEdicionIngreso.numero_orden_compra" type="text" class="form-control form-control-sm" placeholder="Ej. OC-2026-01">
                </div>

                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Recibido por</label>
                  <input v-model.trim="formEdicionIngreso.recibido_por" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Observaciones</label>
                  <input v-model.trim="formEdicionIngreso.observacion" type="text" class="form-control form-control-sm" placeholder="Observaciones de la nota">
                </div>
              </div>
            </div>

            <!-- Sección 2: Agregar nuevo ítem que faltaba -->
            <div class="card p-3 mb-3 border border-warning-subtle bg-white">
              <div class="fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center gap-1">
                <span>➕</span> Agregar ítem omitido a esta nota
              </div>
              <div class="row g-2 align-items-end">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Buscar Medicamento / LINAME</label>
                  <div class="position-relative">
                    <input
                      v-model="busquedaMedIngreso"
                      type="text"
                      class="form-control form-control-sm"
                      placeholder="Escriba código o nombre..."
                      autocomplete="off"
                      @input="buscarMedicamentosIngreso"
                    >
                    <div v-if="sugerenciasMedIngreso.length" class="suggestions-dropdown shadow">
                      <button
                        v-for="m in sugerenciasMedIngreso"
                        :key="m.id"
                        type="button"
                        class="dropdown-item small py-1 px-2 border-bottom text-start"
                        @mousedown.prevent="seleccionarMedIngreso(m)"
                      >
                        <strong>{{ m.codigo }}</strong> — {{ m.nombre }} {{ m.concentracion }}
                      </button>
                    </div>
                  </div>
                </div>

                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Código Lote</label>
                  <input
                    v-model.trim="nuevoItemIngreso.codigo_lote"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Ej. LOT-123"
                    :disabled="!medIngresoSeleccionado"
                  >
                </div>

                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Vencimiento</label>
                  <input
                    v-model="nuevoItemIngreso.fecha_vencimiento"
                    type="date"
                    class="form-control form-control-sm"
                    :disabled="!medIngresoSeleccionado"
                  >
                </div>

                <div class="col-md-2">
                  <label class="form-label small fw-semibold">Cantidad</label>
                  <input
                    v-model.number="nuevoItemIngreso.cantidad"
                    type="number"
                    min="1"
                    class="form-control form-control-sm text-center fw-bold"
                    :disabled="!medIngresoSeleccionado"
                  >
                </div>

                <div class="col-md-2">
                  <label class="form-label small fw-semibold">P. Unitario (Bs)</label>
                  <div class="d-flex gap-1">
                    <input
                      v-model.number="nuevoItemIngreso.precio_unitario"
                      type="number"
                      step="any"
                      min="0"
                      class="form-control form-control-sm text-end"
                      :disabled="!medIngresoSeleccionado"
                    >
                    <button
                      type="button"
                      class="btn btn-warning btn-sm fw-bold px-3"
                      :disabled="!puedeAgregarItemIngreso"
                      @click="agregarItemEdicionIngreso"
                      title="Añadir ítem"
                    >
                      +
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sección 3: Detalle de ítems del ingreso -->
            <div class="card p-3 mb-3 border">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-csc-blue small text-uppercase">
                  2. Ítems incluidos en la nota de ingreso ({{ formEdicionIngreso.items.length }})
                </span>
                <span class="badge bg-secondary">
                  Total: {{ totalUnidadesEdicionIngreso }} unidades
                </span>
              </div>

              <div class="table-responsive bg-white rounded border">
                <table class="table table-sm table-hover align-middle mb-0 small">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 35px;" class="text-center">#</th>
                      <th>Medicamento</th>
                      <th style="width: 120px;">Lote</th>
                      <th style="width: 120px;">Vencimiento</th>
                      <th style="width: 120px;" class="text-center">Cantidad</th>
                      <th style="width: 130px;" class="text-end">P. Unitario</th>
                      <th style="width: 120px;" class="text-end">Subtotal</th>
                      <th style="width: 110px;" class="text-center">Despachos</th>
                      <th style="width: 45px;" class="text-center"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in formEdicionIngreso.items" :key="item.id || ('nuevo_' + idx)">
                      <td class="text-center text-muted fw-bold">{{ idx + 1 }}</td>
                      <td>
                        <strong>{{ item.medicamento?.nombre || item.nombre }}</strong>
                        <small class="d-block text-muted font-monospace">{{ item.medicamento?.codigo || item.codigo }}</small>
                      </td>
                      <td>
                        <input
                          v-model.trim="item.codigo_lote"
                          type="text"
                          class="form-control form-control-sm"
                          required
                        >
                      </td>
                      <td>
                        <input
                          v-model="item.fecha_vencimiento"
                          type="date"
                          class="form-control form-control-sm"
                        >
                      </td>
                      <td class="text-center">
                        <input
                          v-model.number="item.cantidad"
                          type="number"
                          :min="item.cantidad_despachada || 1"
                          class="form-control form-control-sm text-center fw-bold"
                          required
                        >
                        <small v-if="item.cantidad_despachada > 0" class="text-danger d-block" style="font-size: 0.7rem;">
                          Mínimo: {{ item.cantidad_despachada }} desp.
                        </small>
                      </td>
                      <td class="text-end">
                        <input
                          v-model.number="item.precio_unitario"
                          type="number"
                          step="any"
                          min="0"
                          class="form-control form-control-sm text-end font-monospace"
                          required
                        >
                      </td>
                      <td class="text-end font-monospace fw-bold text-csc-blue">
                        {{ moneda(round(item.cantidad * item.precio_unitario, 2)) }}
                      </td>
                      <td class="text-center">
                        <span v-if="item.tiene_salidas || item.cantidad_despachada > 0" class="badge bg-warning-subtle text-dark border border-warning" title="Este lote ya tiene despachos registrados">
                          {{ item.cantidad_despachada }} desp.
                        </span>
                        <span v-else class="badge bg-success-subtle text-success">
                          Sin salidas
                        </span>
                      </td>
                      <td class="text-center">
                        <button
                          type="button"
                          class="btn btn-outline-danger btn-sm border-0 rounded-circle"
                          :title="(item.tiene_salidas || item.cantidad_despachada > 0) ? 'No se puede eliminar porque tiene despachos' : 'Quitar ítem'"
                          :disabled="formEdicionIngreso.items.length === 1 || item.tiene_salidas || item.cantidad_despachada > 0"
                          @click="eliminarItemEdicionIngreso(idx)"
                        >
                          ✕
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot class="table-light border-top fw-bold">
                    <tr>
                      <td colspan="4" class="text-end">TOTAL DE LA NOTA:</td>
                      <td class="text-center">{{ totalUnidadesEdicionIngreso }} u.</td>
                      <td></td>
                      <td class="text-end font-monospace text-csc-blue">{{ moneda(totalValorEdicionIngreso) }}</td>
                      <td colspan="2"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" @click="cerrarEditarIngreso" :disabled="guardandoEdicionIngreso">
                Cancelar
              </button>
              <button type="submit" class="btn btn-csc-orange btn-sm text-white px-4 fw-bold shadow-sm" :disabled="guardandoEdicionIngreso || !formEdicionIngreso.items.length">
                <span v-if="guardandoEdicionIngreso" class="spinner-border spinner-border-sm me-1"></span>
                {{ guardandoEdicionIngreso ? 'Guardando cambios...' : 'Guardar y Actualizar Ingreso' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import { unidadesSolicitantes } from '../data/unidadesSolicitantes.js';

const ingresos = ref([]);
const salidas = ref([]);
const busIngreso = ref('');
const busSalida = ref('');
const textoProducto = ref('');
const sugerencias = ref([]);
const producto = ref(null);
const desde = ref('');
const hasta = ref('');
const invSugerencias = ref([]);
const inv = reactive({ buscar: '' });
const productosInventario = ref([]);
const editandoInventario = ref(null);
const edicionInventario = reactive({ nombre: '' });
const mensajeExito = ref('');
const salidaActualizadaId = ref(null);
const ingresoActualizadoId = ref(null);

let ti, ts, tp, tinv, tmedEdicion, tmedIngreso;

// Estado del Modal de Edición de Salida
const mostrarModalEdicion = ref(false);
const cargandoDetallesEdicion = ref(false);
const guardandoEdicion = ref(false);
const errorEdicion = ref('');
const mostrarSugerenciasDestino = ref(false);

const formEdicion = reactive({
  id: null,
  numero_salida: '',
  fecha_salida: '',
  almacen_origen: '',
  establecimiento_id: null,
  destino_establecimiento: '',
  numero_pedido: '',
  solicitado_por: '',
  entregado_a: '',
  observaciones: '',
  detalles: [],
});

// Campos para agregar nuevo ítem a la salida en edición
const busquedaMedEdicion = ref('');
const sugerenciasMedEdicion = ref([]);
const medEdicionSeleccionado = ref(null);
const lotesDisponiblesEdicion = ref([]);
const cargandoLotesEdicion = ref(false);
const loteEdicionId = ref('');
const cantidadNuevoItem = ref(1);

const unidadesFiltradas = computed(() => {
  const q = String(formEdicion.destino_establecimiento || '').trim().toLowerCase();
  if (!q) return unidadesSolicitantes.slice(0, 10);
  return unidadesSolicitantes.filter(u => u.toLowerCase().includes(q)).slice(0, 10);
});

const loteEdicionSeleccionado = computed(() => {
  return lotesDisponiblesEdicion.value.find(l => l.id === Number(loteEdicionId.value)) || null;
});

const puedeAgregarItemEdicion = computed(() => {
  if (!medEdicionSeleccionado.value || !loteEdicionId.value) return false;
  const cant = Number(cantidadNuevoItem.value);
  if (cant <= 0) return false;
  const maxStock = loteEdicionSeleccionado.value?.cantidad_disponible ?? loteEdicionSeleccionado.value?.cantidad_actual ?? 0;
  return cant <= maxStock;
});

const totalUnidadesEdicion = computed(() => {
  return formEdicion.detalles.reduce((acc, d) => acc + (Number(d.cantidad) || 0), 0);
});

const totalValorEdicion = computed(() => {
  return formEdicion.detalles.reduce((acc, d) => {
    return acc + ((Number(d.cantidad) || 0) * (Number(d.precio_unitario) || 0));
  }, 0);
});

// Estado del Modal de Edición de Ingreso
const mostrarModalEdicionIngreso = ref(false);
const cargandoDetallesEdicionIngreso = ref(false);
const guardandoEdicionIngreso = ref(false);
const errorEdicionIngreso = ref('');

const formEdicionIngreso = reactive({
  id: null,
  numero_nota: '',
  fecha_ingreso: '',
  almacen: '',
  proveedor_nombre: '',
  proveedor_telefono: '',
  numero_remision: '',
  numero_factura: '',
  numero_orden_compra: '',
  recibido_por: '',
  observacion: '',
  items: [],
});

const busquedaMedIngreso = ref('');
const sugerenciasMedIngreso = ref([]);
const medIngresoSeleccionado = ref(null);

const nuevoItemIngreso = reactive({
  codigo_lote: '',
  fecha_vencimiento: '',
  cantidad: 1,
  precio_unitario: 0,
});

const puedeAgregarItemIngreso = computed(() => {
  if (!medIngresoSeleccionado.value) return false;
  if (!nuevoItemIngreso.codigo_lote.trim()) return false;
  if (Number(nuevoItemIngreso.cantidad) <= 0) return false;
  return Number(nuevoItemIngreso.precio_unitario) >= 0;
});

const totalUnidadesEdicionIngreso = computed(() => {
  return formEdicionIngreso.items.reduce((acc, it) => acc + (Number(it.cantidad) || 0), 0);
});

const totalValorEdicionIngreso = computed(() => {
  return formEdicionIngreso.items.reduce((acc, it) => {
    return acc + ((Number(it.cantidad) || 0) * (Number(it.precio_unitario) || 0));
  }, 0);
});

const round = (num, dec = 2) => Math.round(num * Math.pow(10, dec)) / Math.pow(10, dec);

const monedaPrecio = n => new Intl.NumberFormat('es-BO', {
  style: 'currency',
  currency: 'BOB',
  minimumFractionDigits: 2,
  maximumFractionDigits: 6,
}).format(Number(n) || 0);

const moneda = n => new Intl.NumberFormat('es-BO', {
  style: 'currency',
  currency: 'BOB',
  minimumFractionDigits: 2,
}).format(Number(n) || 0);

const seleccionarUnidadDestino = u => {
  formEdicion.destino_establecimiento = u;
  mostrarSugerenciasDestino.value = false;
};

// Carga de Ingresos y Salidas
const cargarIngresos = () => {
  clearTimeout(ti);
  ti = setTimeout(async () => {
    const { data } = await axios.get('api/reportes/ingresos', { params: { buscar: busIngreso.value } });
    ingresos.value = data;
  }, 250);
};

const cargarSalidas = () => {
  clearTimeout(ts);
  ts = setTimeout(async () => {
    const { data } = await axios.get('api/reportes/salidas', { params: { buscar: busSalida.value } });
    salidas.value = data;
  }, 250);
};

// Abrir Edición de Salida
const abrirEditarSalida = async (id) => {
  errorEdicion.value = '';
  mostrarModalEdicion.value = true;
  cargandoDetallesEdicion.value = true;
  mostrarSugerenciasDestino.value = false;
  busquedaMedEdicion.value = '';
  sugerenciasMedEdicion.value = [];
  medEdicionSeleccionado.value = null;
  lotesDisponiblesEdicion.value = [];
  loteEdicionId.value = '';
  cantidadNuevoItem.value = 1;

  try {
    const { data } = await axios.get(`api/salidas/${id}`);
    formEdicion.id = data.id;
    formEdicion.numero_salida = data.numero_salida;
    formEdicion.fecha_salida = data.fecha_salida;
    formEdicion.almacen_origen = data.almacen_origen;
    formEdicion.establecimiento_id = data.establecimiento_id;
    formEdicion.destino_establecimiento = data.destino_establecimiento || '';
    formEdicion.numero_pedido = data.numero_pedido || '';
    formEdicion.solicitado_por = data.solicitado_por || '';
    formEdicion.entregado_a = data.entregado_a || '';
    formEdicion.observaciones = data.observaciones || '';
    formEdicion.detalles = Array.isArray(data.detalles) ? data.detalles.map(d => ({ ...d })) : [];
  } catch (err) {
    errorEdicion.value = err.response?.data?.message || 'No se pudo cargar la nota de salida.';
  } finally {
    cargandoDetallesEdicion.value = false;
  }
};

const cerrarEditarSalida = () => {
  mostrarModalEdicion.value = false;
  errorEdicion.value = '';
};

// Búsqueda de medicamentos para agregar ítem en edición
const buscarMedicamentosEdicion = () => {
  clearTimeout(tmedEdicion);
  const q = busquedaMedEdicion.value.trim();
  if (q.length < 2) {
    sugerenciasMedEdicion.value = [];
    return;
  }
  tmedEdicion = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/medicamentos', { params: { buscar: q } });
      sugerenciasMedEdicion.value = Array.isArray(data) ? data.slice(0, 8) : [];
    } catch {
      sugerenciasMedEdicion.value = [];
    }
  }, 220);
};

const seleccionarMedEdicion = async (med) => {
  medEdicionSeleccionado.value = med;
  busquedaMedEdicion.value = `${med.codigo} - ${med.nombre}`;
  sugerenciasMedEdicion.value = [];
  cargandoLotesEdicion.value = true;
  loteEdicionId.value = '';
  cantidadNuevoItem.value = 1;

  try {
    const { data } = await axios.get(`api/medicamentos/${med.id}/lotes`, {
      params: { salida_id: formEdicion.id }
    });
    lotesDisponiblesEdicion.value = Array.isArray(data) ? data : [];
    if (lotesDisponiblesEdicion.value.length === 1) {
      loteEdicionId.value = lotesDisponiblesEdicion.value[0].id;
    }
  } catch {
    lotesDisponiblesEdicion.value = [];
  } finally {
    cargandoLotesEdicion.value = false;
  }
};

const agregarItemEdicion = () => {
  const lote = loteEdicionSeleccionado.value;
  if (!lote || !medEdicionSeleccionado.value) return;

  const existente = formEdicion.detalles.find(d => d.lote_id === lote.id);
  const cant = Number(cantidadNuevoItem.value);

  if (existente) {
    existente.cantidad = Number(existente.cantidad) + cant;
  } else {
    formEdicion.detalles.push({
      lote_id: lote.id,
      codigo_lote: lote.codigo_lote,
      fecha_vencimiento: lote.fecha_vencimiento,
      precio_unitario: Number(lote.precio_unitario) || 0,
      cantidad: cant,
      stock_disponible: Number(lote.cantidad_disponible ?? lote.cantidad_actual) || cant,
      medicamento: {
        id: medEdicionSeleccionado.value.id,
        codigo: medEdicionSeleccionado.value.codigo,
        nombre: medEdicionSeleccionado.value.nombre,
        concentracion: medEdicionSeleccionado.value.concentracion,
      },
    });
  }

  // Reset del formulario de adición
  busquedaMedEdicion.value = '';
  medEdicionSeleccionado.value = null;
  loteEdicionId.value = '';
  lotesDisponiblesEdicion.value = [];
  cantidadNuevoItem.value = 1;
};

const eliminarItemEdicion = (idx) => {
  formEdicion.detalles.splice(idx, 1);
};

const guardarEdicionSalida = async () => {
  if (!formEdicion.detalles.length) {
    errorEdicion.value = 'Debe incluir al menos un ítem en la salida.';
    return;
  }

  guardandoEdicion.value = true;
  errorEdicion.value = '';

  const payload = {
    fecha_salida: formEdicion.fecha_salida,
    almacen_origen: formEdicion.almacen_origen,
    establecimiento_id: formEdicion.establecimiento_id,
    destino_establecimiento: formEdicion.destino_establecimiento,
    numero_pedido: formEdicion.numero_pedido,
    solicitado_por: formEdicion.solicitado_por,
    entregado_a: formEdicion.entregado_a,
    observaciones: formEdicion.observaciones,
    detalle: formEdicion.detalles.map(d => ({
      lote_id: d.lote_id,
      cantidad: Number(d.cantidad),
    })),
  };

  try {
    const { data } = await axios.put(`api/salidas/${formEdicion.id}`, payload);
    mostrarModalEdicion.value = false;
    mensajeExito.value = data.message || `Nota de Salida N.º ${formEdicion.numero_salida} modificada correctamente.`;
    salidaActualizadaId.value = formEdicion.id;
    await cargarSalidas();
  } catch (err) {
    errorEdicion.value = err.response?.data?.message || 'Error al guardar los cambios en la salida.';
  } finally {
    guardandoEdicion.value = false;
  }
};

// Métodos para Edición de Nota de Ingreso
const abrirEditarIngreso = async (id) => {
  errorEdicionIngreso.value = '';
  mostrarModalEdicionIngreso.value = true;
  cargandoDetallesEdicionIngreso.value = true;
  busquedaMedIngreso.value = '';
  sugerenciasMedIngreso.value = [];
  medIngresoSeleccionado.value = null;
  nuevoItemIngreso.codigo_lote = '';
  nuevoItemIngreso.fecha_vencimiento = '';
  nuevoItemIngreso.cantidad = 1;
  nuevoItemIngreso.precio_unitario = 0;

  try {
    const { data } = await axios.get(`api/ingresos/${id}`);
    formEdicionIngreso.id = data.id;
    formEdicionIngreso.numero_nota = data.numero_nota;
    formEdicionIngreso.fecha_ingreso = data.fecha_ingreso;
    formEdicionIngreso.almacen = data.almacen;
    formEdicionIngreso.proveedor_nombre = data.proveedor?.nombre || '';
    formEdicionIngreso.proveedor_telefono = data.proveedor?.telefono || '';
    formEdicionIngreso.numero_remision = data.numero_remision || '';
    formEdicionIngreso.numero_factura = data.numero_factura || '';
    formEdicionIngreso.numero_orden_compra = data.numero_orden_compra || '';
    formEdicionIngreso.recibido_por = data.recibido_por || '';
    formEdicionIngreso.observacion = data.observacion || '';
    formEdicionIngreso.items = Array.isArray(data.items) ? data.items.map(it => ({ ...it })) : [];
  } catch (err) {
    errorEdicionIngreso.value = err.response?.data?.message || 'No se pudo cargar la nota de ingreso.';
  } finally {
    cargandoDetallesEdicionIngreso.value = false;
  }
};

const cerrarEditarIngreso = () => {
  mostrarModalEdicionIngreso.value = false;
  errorEdicionIngreso.value = '';
};

const buscarMedicamentosIngreso = () => {
  clearTimeout(tmedIngreso);
  const q = busquedaMedIngreso.value.trim();
  if (q.length < 2) {
    sugerenciasMedIngreso.value = [];
    return;
  }
  tmedIngreso = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/medicamentos', { params: { buscar: q } });
      sugerenciasMedIngreso.value = Array.isArray(data) ? data.slice(0, 8) : [];
    } catch {
      sugerenciasMedIngreso.value = [];
    }
  }, 220);
};

const seleccionarMedIngreso = (med) => {
  medIngresoSeleccionado.value = med;
  busquedaMedIngreso.value = `${med.codigo} - ${med.nombre}`;
  sugerenciasMedIngreso.value = [];
  if (med.precio_referencial && Number(nuevoItemIngreso.precio_unitario) === 0) {
    nuevoItemIngreso.precio_unitario = Number(med.precio_referencial);
  }
};

const agregarItemEdicionIngreso = () => {
  if (!medIngresoSeleccionado.value) return;

  formEdicionIngreso.items.push({
    id: null,
    producto_id: medIngresoSeleccionado.value.id,
    codigo_lote: nuevoItemIngreso.codigo_lote.trim(),
    fecha_vencimiento: nuevoItemIngreso.fecha_vencimiento || null,
    cantidad: Number(nuevoItemIngreso.cantidad),
    precio_unitario: Number(nuevoItemIngreso.precio_unitario),
    cantidad_despachada: 0,
    tiene_salidas: false,
    medicamento: {
      id: medIngresoSeleccionado.value.id,
      codigo: medIngresoSeleccionado.value.codigo,
      nombre: medIngresoSeleccionado.value.nombre,
      concentracion: medIngresoSeleccionado.value.concentracion,
    },
  });

  busquedaMedIngreso.value = '';
  medIngresoSeleccionado.value = null;
  nuevoItemIngreso.codigo_lote = '';
  nuevoItemIngreso.fecha_vencimiento = '';
  nuevoItemIngreso.cantidad = 1;
  nuevoItemIngreso.precio_unitario = 0;
};

const eliminarItemEdicionIngreso = (idx) => {
  const it = formEdicionIngreso.items[idx];
  if (it?.tiene_salidas || (it?.cantidad_despachada && it.cantidad_despachada > 0)) {
    alert(`No se puede eliminar el lote ${it.codigo_lote} porque ya cuenta con despachos registrados.`);
    return;
  }
  formEdicionIngreso.items.splice(idx, 1);
};

const guardarEdicionIngreso = async () => {
  if (!formEdicionIngreso.items.length) {
    errorEdicionIngreso.value = 'Debe incluir al menos un ítem en la nota de ingreso.';
    return;
  }

  for (const it of formEdicionIngreso.items) {
    if (it.cantidad_despachada && Number(it.cantidad) < Number(it.cantidad_despachada)) {
      errorEdicionIngreso.value = `El lote ${it.codigo_lote} no puede tener una cantidad menor a las ${it.cantidad_despachada} unidades ya despachadas.`;
      return;
    }
  }

  guardandoEdicionIngreso.value = true;
  errorEdicionIngreso.value = '';

  const payload = {
    ingreso: {
      almacen: formEdicionIngreso.almacen,
      fecha_ingreso: formEdicionIngreso.fecha_ingreso,
      numero_remision: formEdicionIngreso.numero_remision,
      numero_factura: formEdicionIngreso.numero_factura,
      numero_orden_compra: formEdicionIngreso.numero_orden_compra,
      recibido_por: formEdicionIngreso.recibido_por,
      observacion: formEdicionIngreso.observacion,
    },
    proveedor: {
      nombre: formEdicionIngreso.proveedor_nombre,
      telefono: formEdicionIngreso.proveedor_telefono,
    },
    items: formEdicionIngreso.items.map(it => ({
      id: it.id || undefined,
      producto_id: it.producto_id || it.medicamento?.id,
      codigo_lote: it.codigo_lote,
      fecha_vencimiento: it.fecha_vencimiento || null,
      cantidad: Number(it.cantidad),
      precio_unitario: Number(it.precio_unitario),
    })),
  };

  try {
    const { data } = await axios.put(`api/ingresos/${formEdicionIngreso.id}`, payload);
    mostrarModalEdicionIngreso.value = false;
    mensajeExito.value = data.message || `Nota de Ingreso ${formEdicionIngreso.numero_nota} modificada correctamente.`;
    ingresoActualizadoId.value = formEdicionIngreso.id;
    await cargarIngresos();
  } catch (err) {
    errorEdicionIngreso.value = err.response?.data?.message || 'Error al guardar los cambios en la nota de ingreso.';
  } finally {
    guardandoEdicionIngreso.value = false;
  }
};

// Búsqueda para Kardex y Reporte de Inventario
const buscarProductos = () => {
  clearTimeout(tp);
  tp = setTimeout(async () => {
    if (textoProducto.value.trim().length < 1) {
      sugerencias.value = [];
      return;
    }
    sugerencias.value = (await axios.get('api/medicamentos', { params: { buscar: textoProducto.value } })).data.slice(0, 8);
  }, 200);
};

const seleccionarProducto = p => {
  producto.value = p;
  textoProducto.value = `${p.codigo} — ${p.nombre}`;
  sugerencias.value = [];
};

const buscarInventarioProductos = () => {
  clearTimeout(tinv);
  tinv = setTimeout(async () => {
    if (inv.buscar.trim().length < 1) {
      invSugerencias.value = [];
      return;
    }
    const data = (await axios.get('api/medicamentos', { params: { buscar: inv.buscar } })).data;
    invSugerencias.value = (Array.isArray(data) ? data : (data.data || [])).slice(0, 8);
  }, 200);
};

const seleccionarInventarioProducto = p => {
  if (!productosInventario.value.some(x => x.id === p.id)) {
    productosInventario.value.push({ ...p });
  }
  inv.buscar = '';
  invSugerencias.value = [];
};

const eliminarInventario = index => {
  if (editandoInventario.value === index) editandoInventario.value = null;
  productosInventario.value.splice(index, 1);
};

const editarInventario = index => {
  if (editandoInventario.value === index) {
    productosInventario.value[index].nombre = edicionInventario.nombre;
    editandoInventario.value = null;
    return;
  }
  editandoInventario.value = index;
  edicionInventario.nombre = productosInventario.value[index].nombre;
};

const generarSeleccionados = formato => {
  const ids = productosInventario.value.map(p => p.id).join(',');
  descargar('api/reportes/inventario/' + formato, { producto_ids: ids });
};

const descargar = async (url, params = {}) => {
  const r = await axios.get(url, { params, responseType: 'blob' });
  const type = r.headers['content-type'] || 'application/octet-stream';
  const blob = new Blob([r.data], { type });
  const cd = r.headers['content-disposition'] || '';
  const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(cd);
  let filename = match && match[1] ? match[1].replace(/['"]/g, '') : 'reporte';
  if (!/\.(pdf|xls|xlsx)$/i.test(filename)) {
    filename += type.includes('pdf') ? '.pdf' : '.xls';
  }
  const a = document.createElement('a');
  const objectUrl = URL.createObjectURL(blob);
  a.href = objectUrl;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
};

onMounted(() => {
  cargarIngresos();
  cargarSalidas();
});
</script>

<style scoped>
.reports-page { max-width: 1400px; margin: 0 auto; padding: 8px 0 30px; }
.reports-hero { background: #0b3d62; color: #fff; border-radius: 14px; padding: 24px 28px; margin-bottom: 18px; }
.reports-hero h2 { margin: 0; font-size: 1.65rem; }
.reports-hero p { margin: 6px 0 0; opacity: .9; }
.report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.report-card { background: #fff; border: 1px solid #e1e7ed; border-radius: 14px; padding: 20px; box-shadow: 0 6px 18px rgba(11,61,98,.06); }
.report-wide { grid-column: 1/-1; }
.report-title { background: #0b3d62; color: #fff; padding: 10px 14px; border-radius: 9px; font-weight: 700; margin-bottom: 12px; }
.results { margin-top: 10px; max-height: 320px; overflow-y: auto; }
.result-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 9px 0; gap: 8px; }
.result-row span { display: block; font-size: .82rem; color: #667788; }
.result-actions { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.filter-row { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; }
.actions { display: flex; gap: 10px; margin: 14px 0; }
.btn-orange { background: #e85d04; color: #fff; border: 0; border-radius: 7px; padding: 6px 12px; font-size: 0.82rem; font-weight: 600; }
.btn-orange:hover { background: #d35400; }
.btn-outline { background: #fff; color: #0b3d62; border: 1px solid #0b3d62; border-radius: 7px; padding: 6px 11px; font-size: 0.82rem; font-weight: 600; }
.btn-outline:hover { background: #f0f5fa; }
.btn-edit { background: #0b3d62; color: #fff; border: 0; border-radius: 7px; padding: 6px 11px; font-size: 0.82rem; font-weight: 600; }
.btn-edit:hover { background: #082d49; }
.badge-lock { font-size: 0.72rem; padding: 4px 8px; border-radius: 6px; background: #e9ecef; color: #6c757d; font-weight: 600; }
.search-wrap { position: relative; }
.suggestions { position: absolute; z-index: 10; background: #fff; border: 1px solid #dbe3ea; width: 100%; border-radius: 8px; box-shadow: 0 8px 18px rgba(0,0,0,.12); max-height: 220px; overflow-y: auto; }
.suggestions button { display: block; width: 100%; text-align: left; background: #fff; border: 0; border-bottom: 1px solid #eee; padding: 8px 10px; }
.selected { margin-top: 8px; color: #0b3d62; font-weight: 600; }
.empty { padding: 10px; color: #667788; text-align: center; }
.inventory-selection { margin-top: 16px; border: 1px solid #dbe3ea; border-radius: 10px; overflow: hidden; }
.inventory-head { background: #0b3d62; color: #fff; padding: 10px 14px; display: flex; justify-content: space-between; }
.inventory-table-wrap { overflow-x: auto; }
.inventory-table { width: 100%; border-collapse: collapse; }
.inventory-table th { background: #f4f7fa; color: #0b3d62; }
.inventory-table th, .inventory-table td { padding: 10px; border-bottom: 1px solid #e3e9ee; text-align: left; }
.table-actions { white-space: nowrap; }
.edit-btn, .delete-btn { border: 0; border-radius: 6px; padding: 6px 9px; margin-right: 6px; }
.edit-btn { background: #0b3d62; color: #fff; }
.delete-btn { background: #e85d04; color: #fff; }
.table-input { width: 100%; padding: 6px; border: 1px solid #cdd8e2; border-radius: 5px; }
.complete-actions { margin-top: 20px; border-top: 2px solid #e85d04; padding-top: 14px; }
.complete-actions strong { display: block; color: #0b3d62; font-size: 1.05rem; }
.complete-actions span { display: block; color: #667788; margin: 4px 0; }

/* Modal personalizado */
.modal-backdrop-custom {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(11, 61, 98, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 20px;
}
.modal-dialog-custom {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 950px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal-header-custom {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-body-custom {
  overflow-y: auto;
  max-height: calc(90vh - 70px);
}
.bg-csc-blue { background-color: #0b3d62 !important; }
.text-csc-blue { color: #0b3d62 !important; }
.text-csc-orange { color: #e85d04 !important; }
.btn-csc-orange { background-color: #e85d04 !important; border-color: #e85d04 !important; }
.btn-csc-orange:hover { background-color: #d35400 !important; }
.suggestions-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  max-height: 200px;
  overflow-y: auto;
  z-index: 1060;
}
.suggestions-dropdown button {
  width: 100%;
  text-align: left;
  border: 0;
  background: transparent;
}
.suggestions-dropdown button:hover {
  background: #f1f5f9;
}

@media(max-width:850px){
  .report-grid { grid-template-columns: 1fr; }
  .filter-row { grid-template-columns: 1fr; }
  .report-wide { grid-column: auto; }
}
</style>
