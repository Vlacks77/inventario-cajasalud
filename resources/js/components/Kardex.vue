<template>
  <section class="kardex-card">
    <div class="kardex-hero">
      <div>
        <h2>Kardex / Movimientos</h2>
        <p>Consulta la trazabilidad completa de ingresos y salidas del almacén.</p>
      </div>
      <span class="movement-badge">INGRESOS + SALIDAS</span>
    </div>

    <div class="kardex-body">
      <div class="info-strip">
        <strong>{{ filtrosActivos ? 'Resultados de la consulta' : 'Últimos 10 movimientos' }}</strong>
        <span>{{ kardex.length }} movimiento{{ kardex.length === 1 ? '' : 's' }} mostrado{{ kardex.length === 1 ? '' : 's' }}</span>
      </div>

      <div class="filters-panel">
        <div class="filter-title">Buscar movimientos</div>
        <div class="filter-grid">
          <label class="product-search">
            Producto / LINAME
            <input
              v-model="filtros.buscar"
              class="form-control"
              placeholder="Escriba nombre o código"
              autocomplete="off"
              @input="buscarSugerencias"
              @focus="mostrarSugerencias = sugerencias.length > 0"
              @keyup.enter="cargarKardex"
            >
            <div v-if="mostrarSugerencias && sugerencias.length" class="suggestions">
              <button
                v-for="producto in sugerencias"
                :key="producto.id"
                type="button"
                class="suggestion-item"
                @mousedown.prevent="seleccionarProducto(producto)"
              >
                <strong>{{ producto.codigo }} — {{ producto.nombre }}</strong>
                <small>{{ producto.grupo_producto || 'Sin grupo' }} · Partida {{ producto.partida_presupuestaria?.codigo || '—' }}</small>
              </button>
            </div>
          </label>

          <label>
            Procedencia / destino
            <input v-model="filtros.procedencia" class="form-control" placeholder="Proveedor o destino">
          </label>
          <label>
            Desde
            <input v-model="filtros.fecha_desde" type="date" class="form-control">
          </label>
          <label>
            Hasta
            <input v-model="filtros.fecha_hasta" type="date" class="form-control">
          </label>
          <div class="filter-actions">
            <button type="button" class="btn-search" @click="cargarKardex">Buscar</button>
            <button type="button" class="btn-clear" @click="limpiarFiltros">Limpiar</button>
          </div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mb-3">{{ error }}</div>

      <div class="table-wrap">
        <table class="kardex-table">
          <thead>
            <tr>
              <th>Movimiento</th>
              <th>Fecha</th>
              <th>Registrado por</th>
              <th>Nota / remisión</th>
              <th>Partida</th>
              <th>LINAME</th>
              <th>Producto</th>
              <th>Procedencia</th>
              <th>Lote</th>
              <th>Vence</th>
              <th class="text-end">Cant.</th>
              <th class="text-end">P. unit.</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="13" class="empty-row">Cargando movimientos…</td>
            </tr>
            <tr v-else-if="kardex.length === 0">
              <td colspan="13" class="empty-row">No se encontraron movimientos con esos filtros.</td>
            </tr>
            <tr v-for="fila in kardex" :key="fila.id">
              <td><span class="movement-pill" :class="fila.tipo === 'SALIDA' ? 'movement-pill-out' : 'movement-pill-in'">{{ fila.tipo }}</span></td>
              <td>
                {{ fecha(fila.fecha) }}
                <small v-if="fila.registrado_en">{{ fechaHora(fila.registrado_en) }}</small>
              </td>
              <td class="user-cell">
                <strong>{{ fila.usuario || 'Sin trazabilidad histórica' }}</strong>
                <small v-if="fila.usuario_username">@{{ fila.usuario_username }}</small>
              </td>
              <td>
                {{ fila.referencia || '—' }}
                <small v-if="fila.documento">{{ fila.tipo === 'SALIDA' ? 'Pedido' : 'Rem.' }} {{ fila.documento }}</small>
              </td>
              <td>{{ fila.partida || '—' }}</td>
              <td class="code-cell">{{ fila.codigo || '—' }}</td>
              <td>
                <strong>{{ fila.producto || '—' }}</strong>
                <small>{{ fila.forma || '' }}</small>
              </td>
              <td>{{ fila.procedencia || '—' }}</td>
              <td>{{ fila.lote || '—' }}</td>
              <td>{{ fecha(fila.vencimiento) }}</td>
              <td class="text-end" :class="fila.tipo === 'SALIDA' ? 'quantity-out' : 'quantity-in'">{{ fila.tipo === 'SALIDA' ? '−' : '+' }}{{ fila.cantidad }}</td>
              <td class="text-end">{{ moneda(fila.precio_unitario) }}</td>
              <td class="text-end fw-bold">{{ moneda(fila.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue';

const kardex = ref([]);
const sugerencias = ref([]);
const mostrarSugerencias = ref(false);
const cargando = ref(false);
const error = ref('');
let temporizador = null;

const filtros = reactive({
  buscar: '',
  procedencia: '',
  fecha_desde: '',
  fecha_hasta: '',
});

const filtrosActivos = computed(() => Object.values(filtros).some(Boolean));

const moneda = n => new Intl.NumberFormat('es-BO', {
  style: 'currency',
  currency: 'BOB',
  minimumFractionDigits: 2,
}).format(Number(n) || 0);

const fecha = value => {
  if (!value) return '—';
  const raw = String(value).slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return '—';
  const [year, month, day] = raw.split('-').map(Number);
  return new Intl.DateTimeFormat('es-BO').format(new Date(year, month - 1, day));
};

const fechaHora = value => {
  if (!value) return '—';
  const normalizado = String(value).replace(' ', 'T');
  const date = new Date(normalizado);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'short',
    timeStyle: 'short',
    hour12: false,
  }).format(date);
};

const cargarKardex = async () => {
  cargando.value = true;
  error.value = '';
  mostrarSugerencias.value = false;
  try {
    const { data } = await axios.get('api/kardex', { params: filtros });
    kardex.value = data;
  } catch {
    error.value = 'No fue posible cargar los movimientos del kardex.';
  } finally {
    cargando.value = false;
  }
};

const buscarSugerencias = () => {
  clearTimeout(temporizador);
  mostrarSugerencias.value = false;

  const texto = filtros.buscar.trim();
  if (texto.length < 1) {
    sugerencias.value = [];
    return;
  }

  temporizador = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/medicamentos', { params: { buscar: texto } });
      sugerencias.value = data.slice(0, 10);
      mostrarSugerencias.value = sugerencias.value.length > 0;
    } catch {
      sugerencias.value = [];
    }
  }, 220);
};

const seleccionarProducto = producto => {
  filtros.buscar = producto.codigo;
  sugerencias.value = [];
  mostrarSugerencias.value = false;
  cargarKardex();
};

const limpiarFiltros = () => {
  Object.keys(filtros).forEach(key => { filtros[key] = ''; });
  sugerencias.value = [];
  mostrarSugerencias.value = false;
  cargarKardex();
};

const cerrarSugerencias = event => {
  if (!event.target.closest('.product-search')) mostrarSugerencias.value = false;
};

onMounted(() => {
  document.addEventListener('click', cerrarSugerencias);
  cargarKardex();
});

onBeforeUnmount(() => {
  document.removeEventListener('click', cerrarSugerencias);
  clearTimeout(temporizador);
});
</script>

<style scoped>
.kardex-card { background:#fff; border:1px solid #e1e7ed; border-radius:14px; overflow:hidden; box-shadow:0 5px 20px rgba(20,48,70,.07); }
.kardex-hero { background:#0b3d62; color:#fff; min-height:76px; padding:16px 22px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
.kardex-hero h2 { font-size:1.25rem; margin:0 0 3px; font-weight:700; } .kardex-hero p { margin:0; font-size:.9rem; color:rgba(255,255,255,.8); }
.movement-badge { border:1px solid rgba(255,255,255,.6); border-radius:999px; padding:6px 11px; font-size:.72rem; font-weight:800; }
.kardex-body { padding:20px 22px 26px; }
.info-strip { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; color:#0b3d62; }
.info-strip span { color:#71808f; font-size:.8rem; }
.filters-panel { border:1px solid #e1e7ed; border-radius:11px; padding:15px; background:#f8fafc; margin-bottom:18px; }
.filter-title { color:#e85d04; font-weight:800; margin-bottom:12px; }
.filter-grid { display:grid; grid-template-columns:2fr 1.4fr 1fr 1fr auto; gap:10px; align-items:end; }
.filter-grid label { color:#243447; font-size:.78rem; font-weight:700; } .filter-grid input { margin-top:5px; }
.filter-actions { display:flex; gap:7px; } .btn-search,.btn-clear { min-height:38px; border-radius:8px; padding:8px 14px; font-weight:700; white-space:nowrap; }
.btn-search { border:0; background:#e85d04; color:#fff; } .btn-search:hover { background:#d94f00; }
.btn-clear { border:1px solid #cbd6df; background:#fff; color:#0b3d62; }
.product-search { position:relative; }
.suggestions { position:absolute; z-index:50; left:0; right:0; top:100%; margin-top:5px; background:#fff; border:1px solid #d6e0e8; border-radius:9px; box-shadow:0 12px 25px rgba(20,48,70,.16); overflow:hidden; }
.suggestion-item { display:block; width:100%; border:0; border-bottom:1px solid #edf1f4; background:#fff; text-align:left; padding:9px 11px; color:#173c5a; cursor:pointer; }
.suggestion-item:last-child { border-bottom:0; } .suggestion-item:hover { background:#eef5fa; }
.suggestion-item strong { display:block; font-size:.78rem; } .suggestion-item small { display:block; margin-top:2px; color:#71808f; font-size:.7rem; }
.table-wrap { overflow-x:auto; border:1px solid #dbe4eb; border-radius:10px; }
.kardex-table { width:100%; min-width:1510px; border-collapse:separate; border-spacing:0; font-size:.84rem; }
.kardex-table th { background:#0b3d62; color:#fff; padding:10px 9px; border-right:1px solid rgba(255,255,255,.18); text-align:left; white-space:nowrap; font-size:.76rem; }
.kardex-table th:first-child { border-top-left-radius:9px; } .kardex-table th:last-child { border-top-right-radius:9px; border-right:0; }
.kardex-table td { padding:9px; border-bottom:1px solid #e4e9ee; border-right:1px solid #edf1f4; color:#26394b; vertical-align:middle; }
.kardex-table td:last-child { border-right:0; } .kardex-table td strong { display:block; color:#173c5a; } .kardex-table td small { display:block; color:#71808f; font-size:.7rem; margin-top:2px; }
.user-cell { min-width:190px; } .user-cell strong { font-size:.78rem; } .code-cell { font-weight:800; color:#0b3d62 !important; white-space:nowrap; } .movement-pill { display:inline-flex; padding:4px 7px; border-radius:999px; font-size:.65rem; font-weight:800; } .movement-pill-in { background:#e8f5ee; color:#137a45; } .movement-pill-out { background:#fff0e8; color:#c84b00; } .quantity-in { color:#137a45; font-weight:800; } .quantity-out { color:#c84b00; font-weight:800; }
.empty-row { padding:35px !important; text-align:center; color:#71808f; }
@media (max-width:1000px) { .filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .filter-actions { grid-column:1/-1; } }
@media (max-width:650px) { .kardex-body{padding:14px;} .filter-grid{grid-template-columns:1fr;} .filter-actions{grid-column:auto;} .kardex-hero{align-items:flex-start;flex-direction:column;} }
</style>
