<template>
<section class="cm-page">
  <!-- ENCABEZADO INSTITUCIONAL DE PANTALLA (oculto en impresión porque se usa el institucional de print) -->
  <div class="cm-hero no-print">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2>Cierre y Resumen Mensual de Inventario</h2>
        <p>Balances oficiales físico-valorados y respaldo contable de la Cuenta 121 (20 Partidas Oficiales).</p>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span v-if="resumen" :class="['cm-badge-status', resumen.estado === 'CERRADO' ? 'badge-cerrado' : 'badge-preliminar']">
          {{ resumen.estado === 'CERRADO' ? '● CERRADO (OFICIAL)' : '○ PRELIMINAR (EN CURSO)' }}
        </span>
      </div>
    </div>
  </div>

  <!-- BARRA SUPERIOR DE FILTRO Y ACCIONES (no-print) -->
  <div class="cm-card no-print">
    <div class="cm-filter-bar">
      <div class="cm-filter-fields">
        <div class="cm-field">
          <label>Mes</label>
          <select v-model="mesSeleccionado" class="form-select form-select-sm" :disabled="cargando">
            <option v-for="(nombre, num) in mesesLista" :key="num" :value="num">
              {{ num }} - {{ nombre }}
            </option>
          </select>
        </div>

        <div class="cm-field">
          <label>Año</label>
          <select v-model="anioSeleccionado" class="form-select form-select-sm" :disabled="cargando">
            <option v-for="y in aniosDisponibles" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div class="cm-field">
          <label>Almacén / Regional</label>
          <input v-model="almacen" class="form-control form-control-sm" readonly>
        </div>
      </div>

      <div class="cm-filter-actions">
        <button class="btn-csc-navy" :disabled="cargando" @click="consultarResumen">
          <span v-if="cargando" class="spinner-border spinner-border-sm me-1" role="status"></span>
          {{ cargando ? 'Consultando...' : 'Consultar' }}
        </button>

        <button
          v-if="resumen && resumen.estado !== 'CERRADO'"
          class="btn-csc-orange"
          :disabled="guardando || cargando"
          @click="congelarCierreMensual"
          title="Congela y respalda los saldos del mes para evitar alteraciones retroactivas"
        >
          {{ guardando ? 'Congelando...' : 'Generar / Congelar Cierre' }}
        </button>

        <button class="btn-csc-outline" :disabled="!resumen || cargando" @click="imprimirRespaldo" title="Imprimir en formato A4 Horizontal">
          🖨️ Imprimir Respaldo
        </button>

        <button class="btn-csc-green" :disabled="!resumen || cargando" @click="exportarExcel" title="Descargar planilla Excel idéntica a la plantilla histórica">
          📊 Exportar Excel (.xls)
        </button>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger mt-3 mb-0 py-2 small">{{ error }}</div>
    <div v-if="mensajeExito" class="alert alert-success mt-3 mb-0 py-2 small">{{ mensajeExito }}</div>
  </div>

  <!-- NAVEGACIÓN EN TABS (no-print) -->
  <div class="cm-tabs-wrap no-print" v-if="resumen">
    <button
      type="button"
      class="cm-tab-btn"
      :class="{ active: tabActiva === 'tabla_b' }"
      @click="tabActiva = 'tabla_b'"
    >
      📋 Tab 1: Balance Físico - Valorado (Tabla B)
    </button>
    <button
      type="button"
      class="cm-tab-btn"
      :class="{ active: tabActiva === 'tabla_a' }"
      @click="tabActiva = 'tabla_a'"
    >
      🏦 Tab 2: Cuenta 121 - Contable (Tabla A)
    </button>
    <button
      type="button"
      class="cm-tab-btn"
      :class="{ active: tabActiva === 'detalle' }"
      @click="tabActiva = 'detalle'"
    >
      🔍 Tab 3: Auditoría y Detalle de Ítems ({{ resumen.total_items_catalogo || 0 }})
    </button>
    <button
      type="button"
      class="cm-tab-btn"
      :class="{ active: tabActiva === 'historial' }"
      @click="tabActiva = 'historial'"
    >
      📁 Tab 4: Historial de Cierres ({{ cierres.length }})
    </button>
  </div>

  <!-- ============================================================== -->
  <!-- VISTA IMPRESA Y RESPALDO INSTITUCIONAL (Siempre visible al imprimir) -->
  <!-- ============================================================== -->
  <div class="print-only print-header">
    <div class="print-header-top">
      <div>
        <h1 class="print-title-inst">CAJA DE SALUD DE CAMINOS Y R.A.</h1>
        <h2 class="print-sub-inst">REGIONAL LA PAZ · ALMACÉN CENTRAL DE MEDICAMENTOS E INSUMOS MÉDICOS</h2>
        <div class="print-periodo-tag">
          BALANCE MENSUAL OFICIAL DE INVENTARIO · PERIODO: {{ resumen?.mes_nombre }} DE {{ resumen?.anio }}
          <span v-if="resumen?.estado === 'CERRADO'"> (DOCUMENTO CERRADO Y AUDITADO)</span>
          <span v-else> (DOCUMENTO PRELIMINAR)</span>
        </div>
      </div>
      <div class="print-fecha-emision">
        <div>Fecha de emisión: {{ fechaActual }}</div>
        <div v-if="resumen?.cerrado_en">Cierre oficial: {{ resumen.cerrado_en }}</div>
      </div>
    </div>
  </div>

  <!-- TAB 1: TABLA B - BALANCE FÍSICO VALORADO -->
  <div v-if="resumen && (tabActiva === 'tabla_b' || esModoImpresion)" class="cm-card cm-table-card print-section">
    <div class="cm-card-header no-print">
      <div>
        <h3 class="cm-section-title">TABLA B: Resumen Mensual Regional (Físico - Valorado / Kardex Global)</h3>
        <p class="cm-section-desc">Consolidado oficial de las 20 Partidas Presupuestarias con desglose de Cantidades y Valores en Bolivianos (Bs).</p>
      </div>
      <div v-if="resumen.cuadre_exacto" class="cm-pill-cuadre">
        ✓ Cuadre contable con Cuenta 121: 100% Exacto (Bs {{ money(resumen.totales_b.valores.saldo_final) }})
      </div>
    </div>

    <!-- Título para la vista impresa -->
    <div class="print-only print-table-title">
      TABLA B: RESUMEN MENSUAL REGIONAL (FÍSICO - VALORADO / KARDEX GLOBAL)
    </div>

    <div class="table-responsive cm-table-container">
      <table class="table cm-official-table">
        <thead>
          <tr class="th-main-group">
            <th rowspan="2" class="col-num">N°</th>
            <th rowspan="2" class="col-desc">DESCRIPCIÓN (PARTIDA)</th>
            <th colspan="4" class="col-group-cant">CANTIDAD</th>
            <th colspan="4" class="col-group-val">VALORES (BS)</th>
          </tr>
          <tr class="th-sub-group">
            <!-- Cantidades -->
            <th class="th-sub th-sa">Saldo Inicial</th>
            <th class="th-sub th-in">Entradas</th>
            <th class="th-sub th-eg">Salidas</th>
            <th class="th-sub th-sf">Saldo Final</th>
            <!-- Valores -->
            <th class="th-sub th-sa">Saldo Inicial</th>
            <th class="th-sub th-in">Entradas</th>
            <th class="th-sub th-eg">Salidas</th>
            <th class="th-sub th-sf">Saldo Final</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fila in resumen.tabla_b" :key="'b-' + fila.numero" :class="{ 'cm-row-zero': fila.valores.saldo_final === 0 && fila.valores.entradas === 0 }">
            <td class="text-center font-monospace">{{ fila.numero }}</td>
            <td class="text-start fw-semibold text-dark">{{ fila.descripcion }}</td>
            <!-- Cantidades -->
            <td class="text-end">{{ qty(fila.cantidades.saldo_inicial) }}</td>
            <td class="text-end">{{ qty(fila.cantidades.entradas) }}</td>
            <td class="text-end">{{ qty(fila.cantidades.salidas) }}</td>
            <td class="text-end fw-bold text-dark">{{ qty(fila.cantidades.saldo_final) }}</td>
            <!-- Valores -->
            <td class="text-end font-monospace">{{ money(fila.valores.saldo_inicial) }}</td>
            <td class="text-end font-monospace text-success">{{ money(fila.valores.entradas) }}</td>
            <td class="text-end font-monospace text-danger">{{ money(fila.valores.salidas) }}</td>
            <td class="text-end font-monospace fw-bold text-primary">{{ money(fila.valores.saldo_final) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="cm-total-row">
            <td colspan="2" class="text-end fw-bold">TOTALES GENERALES:</td>
            <td class="text-end fw-bold">{{ qty(resumen.totales_b.cantidades.saldo_inicial) }}</td>
            <td class="text-end fw-bold">{{ qty(resumen.totales_b.cantidades.entradas) }}</td>
            <td class="text-end fw-bold">{{ qty(resumen.totales_b.cantidades.salidas) }}</td>
            <td class="text-end fw-bold text-dark">{{ qty(resumen.totales_b.cantidades.saldo_final) }}</td>
            <td class="text-end fw-bold font-monospace">{{ money(resumen.totales_b.valores.saldo_inicial) }}</td>
            <td class="text-end fw-bold font-monospace text-success">{{ money(resumen.totales_b.valores.entradas) }}</td>
            <td class="text-end fw-bold font-monospace text-danger">{{ money(resumen.totales_b.valores.salidas) }}</td>
            <td class="text-end fw-bold font-monospace text-primary fs-6">{{ money(resumen.totales_b.valores.saldo_final) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- TAB 2: TABLA A - CUENTA 121 (FINANCIERA / CONTABLE) -->
  <div v-if="resumen && (tabActiva === 'tabla_a' || esModoImpresion)" class="cm-card cm-table-card print-section">
    <div class="cm-card-header no-print">
      <div>
        <h3 class="cm-section-title">TABLA A: Resumen Cuenta 121 (Financiera / Contable)</h3>
        <p class="cm-section-desc">Desglose oficial de Ingresos (Transferencias entre Regionales vs Compras Locales), Egresos y Saldo Final por Partida.</p>
      </div>
      <div class="cm-pill-formula">
        Saldo del Mes = Saldo Anterior + Total Ingresos (Transf. + Compras) - Egresos
      </div>
    </div>

    <!-- Título para la vista impresa -->
    <div class="print-only print-table-title mt-4">
      TABLA A: RESUMEN CUENTA 121 (FINANCIERA / CONTABLE)
    </div>

    <div class="table-responsive cm-table-container">
      <table class="table cm-official-table">
        <thead>
          <tr class="th-main-group">
            <th class="col-num">N°</th>
            <th class="col-desc">GRUPO / PARTIDA</th>
            <th class="th-sa">SALDO ANTERIOR</th>
            <th class="th-trans">TRANSFERENCIAS ENTRE REGIONALES</th>
            <th class="th-compra">COMPRAS LOCALES</th>
            <th class="th-in">TOTAL INGRESOS REGIONAL</th>
            <th class="th-eg">EGRESOS REGIONAL</th>
            <th class="th-sf">SALDO DEL MES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fila in resumen.tabla_a" :key="'a-' + fila.numero" :class="{ 'cm-row-zero': fila.saldo_mes === 0 && fila.total_ingresos === 0 }">
            <td class="text-center font-monospace">{{ fila.numero }}</td>
            <td class="text-start fw-semibold text-dark">{{ fila.grupo }}</td>
            <td class="text-end font-monospace">{{ money(fila.saldo_anterior) }}</td>
            <td class="text-end font-monospace">{{ money(fila.transferencias) }}</td>
            <td class="text-end font-monospace">{{ money(fila.compras_locales) }}</td>
            <td class="text-end font-monospace fw-semibold text-success">{{ money(fila.total_ingresos) }}</td>
            <td class="text-end font-monospace text-danger">{{ money(fila.egresos) }}</td>
            <td class="text-end font-monospace fw-bold text-primary">{{ money(fila.saldo_mes) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="cm-total-row">
            <td colspan="2" class="text-end fw-bold">TOTALES (BS):</td>
            <td class="text-end fw-bold font-monospace">{{ money(resumen.totales_a.saldo_anterior) }}</td>
            <td class="text-end fw-bold font-monospace">{{ money(resumen.totales_a.transferencias) }}</td>
            <td class="text-end fw-bold font-monospace">{{ money(resumen.totales_a.compras_locales) }}</td>
            <td class="text-end fw-bold font-monospace text-success">{{ money(resumen.totales_a.total_ingresos) }}</td>
            <td class="text-end fw-bold font-monospace text-danger">{{ money(resumen.totales_a.egresos) }}</td>
            <td class="text-end fw-bold font-monospace text-primary fs-6">{{ money(resumen.totales_a.saldo_mes) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- PIE DE FIRMAS OFICIALES INSTITUCIONALES (Siempre al imprimir y al final del balance) -->
  <div class="print-firmas-box">
    <div class="print-firma-col">
      <div class="firma-line"></div>
      <div class="firma-cargo">RESPONSABLE DE ALMACÉN</div>
      <div class="firma-nombre">Elaborado por: Dra. Carmen</div>
      <div class="firma-sello">Almacén Central de Medicamentos e Insumos Médicos</div>
      <div class="firma-pie">Firma y Sello</div>
    </div>

    <div class="print-firma-col">
      <div class="firma-line"></div>
      <div class="firma-cargo">ADMINISTRADOR / CONTADOR</div>
      <div class="firma-nombre">Revisado por</div>
      <div class="firma-sello">Administración Regional La Paz</div>
      <div class="firma-pie">Firma y Sello</div>
    </div>

    <div class="print-firma-col">
      <div class="firma-line"></div>
      <div class="firma-cargo">JEFATURA MÉDICA / REGIONAL</div>
      <div class="firma-nombre">Aprobado por</div>
      <div class="firma-sello">Caja de Salud de Caminos y R.A.</div>
      <div class="firma-pie">Firma y Sello</div>
    </div>
  </div>

  <!-- TAB 3: AUDITORÍA Y DETALLE DE ÍTEMS (no-print) -->
  <div v-if="resumen && tabActiva === 'detalle'" class="cm-card no-print">
    <div class="cm-card-header">
      <div>
        <h3 class="cm-section-title">Auditoría y Detalle Ítem por Ítem</h3>
        <p class="cm-section-desc">Consulte los productos del catálogo que componen los saldos del mes actual. Puede buscar cualquier producto para auditar sus lotes e ingresos/egresos.</p>
      </div>
      <div>
        <span class="badge bg-secondary">{{ detallesFiltrados.length }} de {{ itemsDetalle.length }} productos con movimiento o stock</span>
      </div>
    </div>

    <!-- Buscador de producto para auditoría profunda -->
    <div class="cm-validation mb-4">
      <div class="cm-subtitle">Auditoría Individual de Producto</div>
      <p class="cm-note">Escriba el nombre o código de un producto para verificar la formación exacta de su saldo (saldo anterior, ingresos, egresos y saldo calculado).</p>
      <div class="cm-search-wrap">
        <input v-model="buscarProducto" class="form-control" autocomplete="off" placeholder="Buscar por código LINAME o descripción...">
        <div v-if="sugerencias.length && buscarProducto" class="cm-suggestions">
          <button v-for="p in sugerencias" :key="p.id" type="button" @click="seleccionarProducto(p)">
            <strong>{{ p.codigo }}</strong> · {{ nombreProducto(p) }}
            <small>{{ p.forma_farmaceutica || p.grupo_producto }}</small>
          </button>
        </div>
      </div>

      <div v-if="cargandoProducto" class="cm-loading mt-2">Analizando movimientos del producto en este periodo...</div>
      <div v-if="errorProducto" class="alert alert-danger mt-2 mb-0 py-2 small">{{ errorProducto }}</div>

      <div v-if="productoDetalle" class="cm-product-review mt-3">
        <div class="cm-product-heading">
          <div>
            <strong>{{ productoDetalle.producto.codigo }} · {{ productoDetalle.producto.nombre }}</strong>
            <span>{{ productoDetalle.producto.forma_farmaceutica || 'Sin forma' }} · {{ productoDetalle.producto.grupo_producto || 'Sin grupo' }}</span>
          </div>
          <button type="button" class="cm-clear" @click="limpiarProducto">Limpiar</button>
        </div>

        <div class="cm-product-stats">
          <div><span>Saldo anterior</span><strong>{{ qty(productoDetalle.calculo.saldo_anterior_cantidad) }}</strong><small>Bs {{ money(productoDetalle.calculo.saldo_anterior_importe) }}</small></div>
          <div><span>Transferencias</span><strong>{{ qty(productoDetalle.calculo.transferencia_cantidad) }}</strong><small>Bs {{ money(productoDetalle.calculo.transferencia_importe) }}</small></div>
          <div><span>Compras locales</span><strong>{{ qty(productoDetalle.calculo.compra_local_cantidad) }}</strong><small>Bs {{ money(productoDetalle.calculo.compra_local_importe) }}</small></div>
          <div><span>Egresos</span><strong>{{ qty(productoDetalle.calculo.egreso_cantidad) }}</strong><small>Bs {{ money(productoDetalle.calculo.egreso_importe) }}</small></div>
          <div><span>Saldo calculado</span><strong>{{ qty(productoDetalle.calculo.saldo_mes_cantidad) }}</strong><small>Bs {{ money(productoDetalle.calculo.saldo_mes_importe) }}</small></div>
        </div>
      </div>
    </div>

    <!-- Filtros de la tabla detallada -->
    <div class="cm-table-tools">
      <input v-model="filtroTabla" class="form-control" placeholder="Filtrar por código, descripción o partida...">
      <select v-model="grupoSeleccionado" class="form-select cm-group-filter">
        <option value="TODOS">Todos los 20 grupos</option>
        <option v-for="(grupo, index) in gruposPrincipales" :key="grupo" :value="grupo">{{ index + 1 }}. {{ grupo }}</option>
      </select>
      <label class="cm-stock-filter">
        <input v-model="soloConStock" type="checkbox">
        <span>Solo con saldo &gt; 0</span>
      </label>
    </div>

    <div class="table-responsive cm-full-table">
      <table>
        <thead>
          <tr>
            <th>PARTIDA</th>
            <th>CÓDIGO</th>
            <th>DESCRIPCIÓN Y CONCENTRACIÓN</th>
            <th>FORMA</th>
            <th>GRUPO OFICIAL</th>
            <th>SALDO ANT. (CANT)</th>
            <th>SALDO ANT. (BS)</th>
            <th>INGRESOS (CANT)</th>
            <th>INGRESOS (BS)</th>
            <th>EGRESOS (CANT)</th>
            <th>EGRESOS (BS)</th>
            <th>SALDO MES (CANT)</th>
            <th>SALDO MES (BS)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in detallesFiltradosPaginados" :key="'det-' + (item.medicamento_id || item.codigo)">
            <td class="font-monospace">{{ item.partida_codigo || '—' }}</td>
            <td class="font-monospace fw-semibold">{{ item.codigo || '—' }}</td>
            <td class="text-start">{{ item.descripcion }}</td>
            <td>{{ item.forma_farmaceutica || '—' }}</td>
            <td class="text-start small text-muted">{{ clasificacionDe(item).grupo }}</td>
            <td class="text-end">{{ qty(item.saldo_anterior_cantidad) }}</td>
            <td class="text-end font-monospace">{{ money(item.saldo_anterior_importe) }}</td>
            <td class="text-end">{{ qty(item.total_ingresos_cantidad) }}</td>
            <td class="text-end font-monospace text-success">{{ money(item.total_ingresos_importe) }}</td>
            <td class="text-end">{{ qty(item.egreso_cantidad) }}</td>
            <td class="text-end font-monospace text-danger">{{ money(item.egreso_importe) }}</td>
            <td class="text-end fw-bold">{{ qty(item.saldo_mes_cantidad) }}</td>
            <td class="text-end font-monospace fw-bold text-primary">{{ money(item.saldo_mes_importe) }}</td>
          </tr>
          <tr v-if="!detallesFiltrados.length">
            <td colspan="13" class="text-center py-4 text-muted">No se encontraron productos con los filtros aplicados.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación liviana si hay muchos items -->
    <div v-if="totalPaginas > 1" class="d-flex justify-content-between align-items-center mt-3">
      <small class="text-muted">Mostrando {{ (paginaActual - 1) * itemsPorPagina + 1 }} a {{ Math.min(paginaActual * itemsPorPagina, detallesFiltrados.length) }} de {{ detallesFiltrados.length }} productos</small>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-outline-secondary" :disabled="paginaActual <= 1" @click="paginaActual--">Anterior</button>
        <button class="btn btn-sm btn-outline-secondary" :disabled="paginaActual >= totalPaginas" @click="paginaActual++">Siguiente</button>
      </div>
    </div>
  </div>

  <!-- TAB 4: HISTORIAL DE CIERRES REGISTRADOS (no-print) -->
  <div v-if="tabActiva === 'historial'" class="cm-card no-print">
    <div class="cm-card-header">
      <div>
        <h3 class="cm-section-title">Historial de Cierres Mensuales Registrados</h3>
        <p class="cm-section-desc">Cierres congelados para auditoría y trazabilidad histórica del Almacén.</p>
      </div>
    </div>

    <div v-if="!cierres.length" class="cm-empty text-center py-4">
      Todavía no existen cierres mensuales registrados.
    </div>

    <div v-for="c in cierres" :key="c.id" class="cm-row">
      <div>
        <strong class="text-dark fs-6">{{ etiquetaMes(c.periodo) }}</strong>
        <span class="text-muted small d-block">
          Almacén: {{ c.almacen }} · {{ c.total_items }} ítems · Saldo Valorado: Bs {{ money(c.importe_saldo_mes) }} · Cerrado el: {{ c.cerrado_en || '—' }} (por: {{ c.usuario || 'Sistema' }})
        </span>
      </div>
      <div class="d-flex gap-2">
        <button class="btn-csc-navy btn-sm" @click="verCierreHistorico(c)">Ver Resumen</button>
        <button class="btn-csc-green btn-sm" @click="download(`/api/inventario/resumen-mensual/excel?periodo=${c.periodo.slice(0,7)}`)">Excel Oficial</button>
        <button class="btn-csc-orange btn-sm" @click="download(`/api/cierres-mensuales/${c.id}/pdf`, { stock_only: 1 })">PDF Ítems</button>
      </div>
    </div>
  </div>
</section>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { GRUPOS_INVENTARIO, SUBGRUPOS_LABORATORIO, CATALOGO_GRUPOS_POR_CODIGO } from '../data/catalogoGrupos';

// Mes actual por defecto (Octubre 2026)
const hoy = new Date();
const anioActual = hoy.getFullYear();
const mesActual = hoy.getMonth() + 1;

const mesSeleccionado = ref(mesActual);
const anioSeleccionado = ref(anioActual);
const almacen = ref('REGIONAL LA PAZ');

const mesesLista = {
  1: 'ENERO', 2: 'FEBRERO', 3: 'MARZO', 4: 'ABRIL',
  5: 'MAYO', 6: 'JUNIO', 7: 'JULIO', 8: 'AGOSTO',
  9: 'SEPTIEMBRE', 10: 'OCTUBRE', 11: 'NOVIEMBRE', 12: 'DICIEMBRE'
};

const aniosDisponibles = [2025, 2026, 2027, 2028];

const tabActiva = ref('tabla_b');
const cargando = ref(false);
const guardando = ref(false);
const error = ref('');
const mensajeExito = ref('');
const esModoImpresion = ref(false);

const resumen = ref(null);
const cierres = ref([]);
const itemsDetalle = ref([]);

// Filtros para la Tab 3 (Detalle de Ítems)
const filtroTabla = ref('');
const grupoSeleccionado = ref('TODOS');
const soloConStock = ref(false);
const paginaActual = ref(1);
const itemsPorPagina = 100;

// Auditoría individual de producto
const buscarProducto = ref('');
const sugerencias = ref([]);
const productoDetalle = ref(null);
const cargandoProducto = ref(false);
const errorProducto = ref('');
let buscarTimer = null;
let ignorarBusqueda = false;

const gruposPrincipales = GRUPOS_INVENTARIO;
const subgruposLaboratorio = SUBGRUPOS_LABORATORIO;

const periodoKey = computed(() => {
  const m = String(mesSeleccionado.value).padStart(2, '0');
  return `${anioSeleccionado.value}-${m}`;
});

const fechaActual = computed(() => {
  return new Date().toLocaleDateString('es-BO', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  });
});

// Normalización para clasificación
const normalizarTexto = (v) => String(v || '')
  .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  .replace(/\s+/g, ' ').trim().toUpperCase();

const normalizarCodigo = (v) => String(v || '').trim().toUpperCase().replace(/\.0$/, '');

const clasificacionDe = (d) => {
  const codigo = normalizarCodigo(d.codigo);
  const desdeCatalogo = CATALOGO_GRUPOS_POR_CODIGO[codigo];
  if (desdeCatalogo) return desdeCatalogo;

  const grupoOrigen = normalizarTexto(d.grupo_producto);
  const grupoEncontrado = gruposPrincipales.find((g) => normalizarTexto(g) === grupoOrigen);
  const subgrupoEncontrado = subgruposLaboratorio.find((g) => normalizarTexto(g) === grupoOrigen);

  if (subgrupoEncontrado) {
    return { grupo: 'MATERIAL DE LABORATORIO Y REACTIVOS', subgrupo: subgrupoEncontrado };
  }
  return { grupo: grupoEncontrado || 'OTROS MATERIALES Y SUMINISTROS', subgrupo: null };
};

// Formateadores numéricos
const money = (v) => Number(v || 0).toLocaleString('es-BO', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

const qty = (v) => Number(v || 0).toLocaleString('es-BO', {
  maximumFractionDigits: 3
});

const etiquetaMes = (v) => {
  if (!v) return '';
  const [y, m] = String(v).slice(0, 7).split('-');
  return `${mesesLista[parseInt(m, 10)] || m} ${y}`;
};

// Consulta del resumen oficial mensual
async function consultarResumen() {
  if (tabActiva.value === 'historial') {
    tabActiva.value = 'tabla_b';
  }
  cargando.value = true;
  error.value = '';
  mensajeExito.value = '';
  try {
    const { data } = await axios.get('/api/inventario/resumen-mensual', {
      params: {
        mes: mesSeleccionado.value,
        anio: anioSeleccionado.value,
        almacen: almacen.value
      }
    });
    resumen.value = data;

    // Cargamos también los detalles si estamos en una vista de preview
    await cargarDetallesCatalogo();
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al obtener el balance mensual.';
  } finally {
    cargando.value = false;
  }
}

async function cargarDetallesCatalogo() {
  try {
    // Si el mes está cerrado, recuperamos desde show de cierre
    if (resumen.value?.cierre_id) {
      const { data } = await axios.get(`/api/cierres-mensuales/${resumen.value.cierre_id}`);
      itemsDetalle.value = data.detalles || [];
    } else {
      // Si está preliminar, recuperamos desde preview
      const { data } = await axios.get('/api/cierres-mensuales/preview', {
        params: {
          periodo: periodoKey.value,
          almacen: almacen.value
        }
      });
      itemsDetalle.value = data.detalles || [];
    }
  } catch {
    itemsDetalle.value = [];
  }
}

// Congelar cierre mensual
async function congelarCierreMensual() {
  const nombreMes = mesesLista[mesSeleccionado.value];
  if (!confirm(`¿Está seguro de generar y congelar el CIERRE OFICIAL de ${nombreMes} / ${anioSeleccionado.value}?\n\nEsta acción respaldará físicamente los saldos para que la Dra. Carmen y las autoridades cuenten con trazabilidad inalterable.`)) {
    return;
  }

  guardando.value = true;
  error.value = '';
  mensajeExito.value = '';
  try {
    const { data } = await axios.post('/api/inventario/resumen-mensual/cerrar', {
      periodo: periodoKey.value,
      almacen: almacen.value,
      observacion: `Cierre oficial del periodo ${nombreMes} / ${anioSeleccionado.value} generado por Almacén Regional La Paz.`
    });
    mensajeExito.value = '¡Cierre mensual generado y congelado exitosamente!';
    await consultarResumen();
    await cargarHistorialCierres();
  } catch (e) {
    error.value = e.response?.data?.message || 'No se pudo congelar el cierre mensual.';
  } finally {
    guardando.value = false;
  }
}

// Exportación a Excel
function exportarExcel() {
  const url = `/api/inventario/resumen-mensual/excel?mes=${mesSeleccionado.value}&anio=${anioSeleccionado.value}&almacen=${encodeURIComponent(almacen.value)}`;
  window.open(url, '_blank');
}

// Impresión oficial A4 Landscape
function imprimirRespaldo() {
  window.print();
}

// Cargar historial de cierres
async function cargarHistorialCierres() {
  try {
    const { data } = await axios.get('/api/cierres-mensuales');
    cierres.value = data;
  } catch {
    cierres.value = [];
  }
}

function verCierreHistorico(c) {
  const [y, m] = c.periodo.slice(0, 7).split('-');
  anioSeleccionado.value = parseInt(y, 10);
  mesSeleccionado.value = parseInt(m, 10);
  tabActiva.value = 'tabla_b';
  consultarResumen();
}

// Descarga genérica de archivos
async function download(url, params = {}) {
  try {
    const r = await axios.get(url, { responseType: 'blob', params });
    const cd = r.headers['content-disposition'] || '';
    const m = cd.match(/filename="?([^";]+)"?/);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(r.data);
    a.download = m?.[1] || 'reporte.xls';
    document.body.appendChild(a);
    a.click();
    a.remove();
  } catch (e) {
    alert('Error al descargar el archivo.');
  }
}

// Auditoría individual de producto
function nombreProducto(p) {
  const nombre = String(p?.nombre || '').trim();
  const concentracion = String(p?.concentracion || '').trim();
  if (!concentracion) return nombre;
  const n = normalizarTexto(nombre);
  const c = normalizarTexto(concentracion);
  return n.includes(c) ? nombre : `${nombre} ${concentracion}`.trim();
}

watch(buscarProducto, (valor) => {
  if (ignorarBusqueda) {
    ignorarBusqueda = false;
    return;
  }
  clearTimeout(buscarTimer);
  const q = (valor || '').trim();
  if (q.length < 1) {
    sugerencias.value = [];
    return;
  }
  buscarTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('/api/medicamentos', { params: { buscar: q } });
      sugerencias.value = data;
    } catch {
      sugerencias.value = [];
    }
  }, 200);
});

async function seleccionarProducto(p) {
  ignorarBusqueda = true;
  buscarProducto.value = `${p.codigo} · ${nombreProducto(p)}`;
  sugerencias.value = [];
  productoDetalle.value = null;
  errorProducto.value = '';
  cargandoProducto.value = true;
  try {
    const { data } = await axios.get(`/api/cierres-mensuales/productos/${p.id}/preview`, {
      params: { periodo: periodoKey.value, almacen: almacen.value }
    });
    productoDetalle.value = data;
  } catch (e) {
    errorProducto.value = e.response?.data?.message || 'No se pudo analizar el producto.';
  } finally {
    cargandoProducto.value = false;
  }
}

function limpiarProducto() {
  buscarProducto.value = '';
  sugerencias.value = [];
  productoDetalle.value = null;
  errorProducto.value = '';
}

// Filtros para la lista de ítems detallados
const detallesFiltrados = computed(() => {
  let list = itemsDetalle.value;
  const q = filtroTabla.value.trim().toLowerCase();

  if (q) {
    list = list.filter((item) =>
      String(item.codigo || '').toLowerCase().includes(q) ||
      String(item.descripcion || '').toLowerCase().includes(q) ||
      String(item.partida_codigo || '').toLowerCase().includes(q)
    );
  }

  if (grupoSeleccionado.value !== 'TODOS') {
    list = list.filter((item) => clasificacionDe(item).grupo === grupoSeleccionado.value);
  }

  if (soloConStock.value) {
    list = list.filter((item) => Number(item.saldo_mes_cantidad || 0) > 0);
  }

  return list;
});

const totalPaginas = computed(() => Math.ceil(detallesFiltrados.value.length / itemsPorPagina));

const detallesFiltradosPaginados = computed(() => {
  const start = (paginaActual.value - 1) * itemsPorPagina;
  return detallesFiltrados.value.slice(start, start + itemsPorPagina);
});

watch([filtroTabla, grupoSeleccionado, soloConStock], () => {
  paginaActual.value = 1;
});

onMounted(async () => {
  await consultarResumen();
  await cargarHistorialCierres();
});
</script>

<style scoped>
.cm-page {
  width: 100%;
  margin: 10px 0;
  font-family: inherit;
}

/* HERO */
.cm-hero {
  background: linear-gradient(135deg, #0b3d62 0%, #174f7a 100%);
  color: #fff;
  border-radius: 12px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(11, 61, 98, 0.12);
}
.cm-hero h2 {
  font-size: 1.35rem;
  font-weight: 700;
  margin: 0;
}
.cm-hero p {
  margin: 4px 0 0;
  color: #dbe7f0;
  font-size: 0.88rem;
}

/* BADGES DE ESTADO */
.cm-badge-status {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.badge-cerrado {
  background: #28a745;
  color: #fff;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}
.badge-preliminar {
  background: #fd7e14;
  color: #fff;
  box-shadow: 0 2px 8px rgba(253, 126, 20, 0.3);
}

/* CARDS */
.cm-card {
  background: #fff;
  border: 1px solid #dce4ec;
  border-radius: 10px;
  margin-top: 14px;
  padding: 16px 20px;
  box-shadow: 0 2px 8px rgba(11, 61, 98, 0.04);
}

/* FILTRO SUPERIOR */
.cm-filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}
.cm-filter-fields {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.cm-field {
  display: flex;
  flex-direction: column;
}
.cm-field label {
  font-size: 0.78rem;
  font-weight: 700;
  color: #384d63;
  margin-bottom: 4px;
  text-transform: uppercase;
}
.cm-field select, .cm-field input {
  min-width: 140px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.cm-filter-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

/* BOTONES */
.btn-csc-navy {
  background: #0b3d62;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 7px 14px;
  font-size: 0.85rem;
  font-weight: 600;
}
.btn-csc-navy:hover { background: #082d49; color: #fff; }

.btn-csc-orange {
  background: #e85d04;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 7px 14px;
  font-size: 0.85rem;
  font-weight: 600;
}
.btn-csc-orange:hover { background: #dc2f02; color: #fff; }

.btn-csc-green {
  background: #198754;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 7px 14px;
  font-size: 0.85rem;
  font-weight: 600;
}
.btn-csc-green:hover { background: #146c43; color: #fff; }

.btn-csc-outline {
  background: #fff;
  color: #0b3d62;
  border: 1px solid #0b3d62;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 0.85rem;
  font-weight: 600;
}
.btn-csc-outline:hover { background: #eef3f7; }

/* TABS */
.cm-tabs-wrap {
  display: flex;
  gap: 6px;
  margin-top: 14px;
  border-bottom: 2px solid #0b3d62;
  padding-bottom: 0;
  overflow-x: auto;
}
.cm-tab-btn {
  background: #e9eff5;
  color: #384d63;
  border: 1px solid #cbd5e1;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  padding: 9px 18px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}
.cm-tab-btn:hover {
  background: #dce7f0;
  color: #0b3d62;
}
.cm-tab-btn.active {
  background: #0b3d62;
  color: #fff;
  border-color: #0b3d62;
}

/* HEADER DEL CARD */
.cm-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid #eef2f6;
}
.cm-section-title {
  color: #0b3d62;
  font-size: 1.08rem;
  font-weight: 700;
  margin: 0;
}
.cm-section-desc {
  color: #64748b;
  font-size: 0.82rem;
  margin: 2px 0 0;
}

/* PILLS */
.cm-pill-cuadre {
  background: #e6fffa;
  color: #234e52;
  border: 1px solid #38b2ac;
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 0.8rem;
  font-weight: 700;
}
.cm-pill-formula {
  background: #f0f7ff;
  color: #1e3a8a;
  border: 1px solid #93c5fd;
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 0.78rem;
  font-weight: 600;
}

/* TABLAS OFICIALES (A y B) */
.cm-table-container {
  overflow-x: auto;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.cm-official-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin-bottom: 0;
}
.cm-official-table th, .cm-official-table td {
  border: 1px solid #cbd5e1;
  padding: 6px 8px;
  vertical-align: middle;
}
.cm-official-table th {
  background: #0b3d62;
  color: #ffffff;
  text-align: center;
  font-weight: 700;
  font-size: 0.8rem;
}
.th-main-group th {
  background: #0b3d62;
  color: #ffffff;
}
.th-sub-group th {
  background: #174f7a;
  color: #ffffff;
  font-size: 0.76rem;
}
.th-sa { background: #3b82f6 !important; }
.th-trans { background: #10b981 !important; }
.th-compra { background: #f59e0b !important; }
.th-in { background: #059669 !important; }
.th-eg { background: #dc2626 !important; }
.th-sf { background: #2563eb !important; }

.col-num { width: 38px; text-align: center; }
.col-desc { min-width: 260px; }
.cm-row-zero td {
  color: #94a3b8 !important;
}
.cm-total-row td {
  background: #edf2f7;
  border-top: 2px solid #0b3d62 !important;
  border-bottom: 2px solid #0b3d62 !important;
  font-weight: 700;
  color: #0f172a;
}

/* TAB 3 Y FILTROS */
.cm-table-tools {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin: 10px 0;
}
.cm-table-tools input { max-width: 380px; }
.cm-group-filter { max-width: 250px; }
.cm-stock-filter {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}
.cm-full-table {
  max-height: 550px;
  overflow: auto;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.cm-full-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.78rem;
}
.cm-full-table th, .cm-full-table td {
  border: 1px solid #cbd5e1;
  padding: 5px 7px;
  white-space: nowrap;
}
.cm-full-table th {
  background: #0b3d62;
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 2;
}

/* AUDITORÍA INDIVIDUAL */
.cm-search-wrap {
  position: relative;
  max-width: 500px;
}
.cm-suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 10;
  max-height: 220px;
  overflow-y: auto;
}
.cm-suggestions button {
  display: block;
  width: 100%;
  border: none;
  background: #fff;
  text-align: left;
  padding: 8px 12px;
  border-bottom: 1px solid #f1f5f9;
}
.cm-suggestions button:hover { background: #f8fafc; }
.cm-product-review {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
}
.cm-product-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
}
.cm-clear {
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 0.8rem;
}
.cm-product-stats {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
  margin-top: 10px;
}
.cm-product-stats > div {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #0b3d62;
  padding: 6px 10px;
  border-radius: 4px;
}
.cm-product-stats span { font-size: 0.72rem; color: #64748b; display: block; }
.cm-product-stats strong { font-size: 0.95rem; color: #0b3d62; display: block; }
.cm-product-stats small { font-size: 0.72rem; color: #475569; display: block; }

/* HISTORIAL */
.cm-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #e2e8f0;
  gap: 12px;
  flex-wrap: wrap;
}

/* FIRMAS OFICIALES */
.print-firmas-box {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  margin-top: 35px;
  page-break-inside: avoid;
}
.print-firma-col {
  flex: 1;
  text-align: center;
}
.firma-line {
  border-top: 1.5px solid #334155;
  width: 80%;
  margin: 40px auto 8px auto;
}
.firma-cargo {
  font-weight: 700;
  font-size: 0.82rem;
  color: #0f172a;
}
.firma-nombre {
  font-size: 0.8rem;
  color: #334155;
  margin-top: 2px;
}
.firma-sello {
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 2px;
}
.firma-pie {
  font-size: 0.68rem;
  color: #94a3b8;
  font-style: italic;
  margin-top: 4px;
}

/* ELEMENTOS DE IMPRESIÓN EXCLUSIVA */
.print-only { display: none; }

/* ============================================================== */
/* ESTILOS DE IMPRESIÓN OFICIAL: A4 HORIZONTAL (LANDSCAPE) */
/* ============================================================== */
@media print {
  @page {
    size: A4 landscape;
    margin: 8mm 8mm 8mm 8mm;
  }

  body, .csc-app, .csc-page, .csc-main, .cm-page {
    background: #ffffff !important;
    color: #000000 !important;
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
  }

  .no-print, header, .csc-topbar, .csc-nav, footer, .csc-system-credits {
    display: none !important;
  }

  .print-only {
    display: block !important;
  }

  .cm-card {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .print-header {
    margin-bottom: 10px;
    border-bottom: 2px solid #000;
    padding-bottom: 6px;
  }
  .print-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }
  .print-title-inst {
    font-size: 13pt;
    font-weight: 800;
    margin: 0;
    color: #000;
    text-transform: uppercase;
  }
  .print-sub-inst {
    font-size: 9.5pt;
    font-weight: 600;
    margin: 2px 0 0;
    color: #333;
  }
  .print-periodo-tag {
    font-size: 9pt;
    font-weight: 700;
    margin-top: 4px;
    color: #000;
  }
  .print-fecha-emision {
    font-size: 7.5pt;
    color: #555;
    text-align: right;
  }

  .print-table-title {
    font-size: 9.5pt;
    font-weight: 800;
    margin: 10px 0 4px;
    text-align: left;
    color: #000;
    border-left: 4px solid #000;
    padding-left: 6px;
  }

  .cm-table-container {
    border: 1px solid #000 !important;
    border-radius: 0 !important;
    overflow: visible !important;
  }

  .cm-official-table {
    font-size: 7pt !important;
    border: 1px solid #000 !important;
    page-break-inside: auto;
  }
  .cm-official-table th, .cm-official-table td {
    padding: 3px 4px !important;
    border: 1px solid #444 !important;
    color: #000 !important;
  }
  .cm-official-table th {
    background: #e2e8f0 !important;
    color: #000 !important;
    font-weight: 700 !important;
    font-size: 7pt !important;
  }
  .th-sub-group th {
    background: #f1f5f9 !important;
    color: #000 !important;
  }
  .cm-total-row td {
    background: #e2e8f0 !important;
    font-weight: 800 !important;
    border-top: 1.5px solid #000 !important;
    border-bottom: 1.5px solid #000 !important;
  }

  .print-section {
    page-break-after: auto;
  }

  .print-firmas-box {
    margin-top: 30px !important;
    page-break-inside: avoid !important;
  }
  .firma-line {
    margin-top: 35px !important;
    border-top: 1px solid #000 !important;
  }
  .firma-cargo { font-size: 7.5pt !important; }
  .firma-nombre { font-size: 7.5pt !important; }
  .firma-sello { font-size: 6.5pt !important; }
  .firma-pie { font-size: 6pt !important; }
}
</style>
