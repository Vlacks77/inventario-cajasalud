<template>
  <section class="inventory-card">
    <div class="inventory-hero">
      <div>
        <h2>Inventario del almacén</h2>
        <p>Consulta el stock actual, lotes disponibles y fechas de vencimiento.</p>
      </div>
    </div>

    <div class="inventory-body">
      <div class="summary-grid">
        <article class="summary-card">
          <span>Productos catalogados</span>
          <strong>{{ resumen.productos }}</strong>
        </article>
        <article class="summary-card">
          <span>Con stock</span>
          <strong>{{ resumen.conStock }}</strong>
        </article>
        <article class="summary-card summary-warning">
          <span>Stock bajo</span>
          <strong>{{ resumen.stockBajo }}</strong>
        </article>
        <article class="summary-card summary-danger">
          <span>Próximos a vencer</span>
          <strong>{{ resumen.proximos }}</strong>
        </article>
      </div>

      <div class="filters-panel">
        <div class="filter-title">Buscar y filtrar</div>
        <div class="filter-grid">
          <label>
            Producto / LINAME
            <input v-model="filtros.buscar" class="form-control" placeholder="Ej.: J0501, Abacavir 300 mg" @keyup.enter="cargarInventario">
          </label>

          <label>
            Grupo de producto
            <select v-model="filtros.grupo" class="form-select">
              <option value="">Todos los grupos</option>
              <option v-for="grupo in grupos" :key="grupo" :value="grupo">{{ grupo }}</option>
            </select>
          </label>

          <label>
            Partida presupuestaria
            <select v-model="filtros.partida" class="form-select">
              <option value="">Todas las partidas</option>
              <option v-for="partida in partidas" :key="partida.codigo" :value="partida.codigo">
                {{ partida.codigo }} — {{ partida.nombre }}
              </option>
            </select>
          </label>

          <label>
            Estado de stock
            <select v-model="filtros.estado_stock" class="form-select">
              <option value="">Todos</option>
              <option value="NORMAL">Normal</option>
              <option value="STOCK_BAJO">Stock bajo</option>
              <option value="SIN_STOCK">Sin stock</option>
            </select>
          </label>

          <label>
            Vencimiento
            <select v-model="filtros.vencimiento" class="form-select">
              <option value="">Todos</option>
              <option value="VIGENTE">Vigente</option>
              <option value="PROXIMO">Próximo a vencer (90 días)</option>
              <option value="VENCIDO">Vencido</option>
              <option value="SIN_VENCIMIENTO">Sin vencimiento</option>
            </select>
          </label>

          <div class="filter-actions">
            <button type="button" class="btn-search" @click="cargarInventario">Buscar</button>
            <button type="button" class="btn-clear" @click="limpiarFiltros">Limpiar</button>
          </div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mb-3">{{ error }}</div>

      <div class="table-wrap">
        <table class="inventory-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th>LINAME</th>
              <th>Partida</th>
              <th>Grupo</th>
              <th>Forma / unidad</th>
              <th class="text-center">Stock</th>
              <th>Próximo vencimiento</th>
              <th>Estado</th>
              <th class="text-center">Detalle</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="9" class="empty-row">Cargando inventario…</td>
            </tr>
            <tr v-else-if="productos.length === 0">
              <td colspan="9" class="empty-row">No se encontraron productos con los filtros seleccionados.</td>
            </tr>

            <template v-else v-for="producto in productos" :key="producto.id">
              <tr class="product-row" :class="{ expanded: expandido === producto.id }">
                <td>
                  <strong>{{ producto.nombre }}</strong>
                </td>
                <td class="code-cell">{{ producto.codigo }}</td>
                <td><strong>{{ producto.partida?.codigo || '—' }}</strong></td>
                <td><span class="group-text">{{ producto.grupo_producto || '—' }}</span></td>
                <td>
                  <span>{{ producto.forma_farmaceutica || '—' }}</span>
                  <small v-if="producto.unidad_presentacion && producto.unidad_presentacion !== producto.forma_farmaceutica">
                    {{ producto.unidad_presentacion }}
                  </small>
                </td>
                <td class="text-center">
                  <strong class="stock-number">{{ producto.stock_total }}</strong>
                  <small v-if="producto.stock_minimo > 0">Mín. {{ producto.stock_minimo }}</small>
                </td>
                <td>
                  <span>{{ fecha(producto.proximo_vencimiento) }}</span>
                  <small v-if="producto.estado_vencimiento === 'PROXIMO'" class="date-warning">Próximo a vencer</small>
                  <small v-if="producto.estado_vencimiento === 'VENCIDO'" class="date-danger">Vencido</small>
                </td>
                <td>
                  <span class="status-pill" :class="estadoClass(producto.estado_stock)">{{ estadoTexto(producto.estado_stock) }}</span>
                </td>
                <td class="text-center">
                  <button type="button" class="detail-button" @click="toggleDetalle(producto.id)">
                    {{ expandido === producto.id ? 'Ocultar' : 'Lotes' }}
                  </button>
                </td>
              </tr>

              <tr v-if="expandido === producto.id" class="lot-row">
                <td colspan="9">
                  <div class="lot-panel">
                    <div class="lot-panel-head">
                      <div><strong>{{ producto.nombre }}</strong><span> · {{ producto.codigo }}</span></div>
                      <div class="lot-total">Stock total: <strong>{{ producto.stock_total }}</strong></div>
                    </div>

                    <div v-if="producto.lotes?.length" class="lot-table-wrap">
                      <table class="lot-table">
                        <thead>
                          <tr>
                            <th>Lote</th>
                            <th>Fecha de ingreso</th>
                            <th>Vencimiento</th>
                            <th>Proveedor</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">P. unitario</th>
                            <th class="text-end">Valor actual</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="lote in producto.lotes" :key="lote.id">
                            <td>{{ lote.codigo_lote }}</td>
                            <td>{{ fecha(lote.fecha_ingreso) }}</td>
                            <td>{{ fecha(lote.fecha_vencimiento) }}</td>
                            <td>{{ lote.proveedor || '—' }}</td>
                            <td class="text-end"><strong>{{ lote.cantidad_actual }}</strong></td>
                            <td class="text-end">{{ moneda(lote.precio_unitario) }}</td>
                            <td class="text-end"><strong>{{ moneda(lote.valor_actual) }}</strong></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div v-else class="no-lots">Este producto no tiene lotes con stock disponible.</div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';

const productos = ref([]);
const catalogoCompleto = ref([]);
const cargando = ref(false);
const error = ref('');
const expandido = ref(null);

const filtros = reactive({
  buscar: '',
  grupo: '',
  partida: '',
  estado_stock: '',
  vencimiento: '',
});

const partidas = computed(() => {
  const mapa = new Map();
  catalogoCompleto.value.forEach(producto => {
    if (producto.partida?.codigo) mapa.set(producto.partida.codigo, producto.partida);
  });
  return [...mapa.values()].sort((a, b) => a.codigo.localeCompare(b.codigo));
});

const grupos = computed(() =>
  [...new Set(catalogoCompleto.value.map(p => p.grupo_producto).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b))
);

const resumen = computed(() => ({
  productos: catalogoCompleto.value.length,
  conStock: catalogoCompleto.value.filter(p => Number(p.stock_total) > 0).length,
  stockBajo: catalogoCompleto.value.filter(p => p.estado_stock === 'STOCK_BAJO').length,
  proximos: catalogoCompleto.value.filter(p => p.estado_vencimiento === 'PROXIMO').length,
}));

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

const estadoTexto = estado => ({
  NORMAL: 'Normal',
  STOCK_BAJO: 'Stock bajo',
  SIN_STOCK: 'Sin stock',
}[estado] || estado);

const estadoClass = estado => ({
  NORMAL: 'status-normal',
  STOCK_BAJO: 'status-warning',
  SIN_STOCK: 'status-danger',
}[estado] || '');

const cargarInventario = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('api/inventario', { params: filtros });
    productos.value = data;

    if (catalogoCompleto.value.length === 0) {
      const completo = await axios.get('api/inventario');
      catalogoCompleto.value = completo.data;
    }
  } catch {
    error.value = 'No fue posible cargar el inventario. Verifique la conexión con el servidor.';
  } finally {
    cargando.value = false;
  }
};

const limpiarFiltros = () => {
  Object.keys(filtros).forEach(key => { filtros[key] = ''; });
  expandido.value = null;
  cargarInventario();
};

const toggleDetalle = id => {
  expandido.value = expandido.value === id ? null : id;
};

onMounted(cargarInventario);
</script>

<style scoped>
.inventory-card { background:#fff; border:1px solid #e1e7ed; border-radius:14px; overflow:hidden; box-shadow:0 5px 20px rgba(20,48,70,.07); }
.inventory-hero { background:#0b3d62; color:#fff; min-height:76px; padding:16px 22px; display:flex; align-items:center; justify-content:space-between; }
.inventory-hero h2 { font-size:1.25rem; margin:0 0 3px; font-weight:700; }
.inventory-hero p { margin:0; font-size:.9rem; color:rgba(255,255,255,.8); }
.inventory-body { padding:20px 22px 26px; }
.summary-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
.summary-card { border:1px solid #e1e7ed; border-left:4px solid #0b3d62; border-radius:10px; padding:13px 15px; background:#fbfcfd; }
.summary-card span { display:block; color:#667788; font-size:.78rem; font-weight:700; }
.summary-card strong { display:block; color:#0b3d62; font-size:1.45rem; line-height:1.2; margin-top:4px; }
.summary-warning { border-left-color:#e85d04; } .summary-danger { border-left-color:#b42318; }
.filters-panel { border:1px solid #e1e7ed; border-radius:11px; padding:15px; background:#f8fafc; margin-bottom:18px; }
.filter-title { color:#e85d04; font-weight:800; margin-bottom:12px; }
.filter-grid { display:grid; grid-template-columns:2fr 1.5fr 1.25fr 1.25fr 1.45fr auto; gap:10px; align-items:end; }
.filter-grid label { color:#243447; font-size:.78rem; font-weight:700; } .filter-grid input,.filter-grid select { margin-top:5px; }
.filter-actions { display:flex; gap:7px; } .btn-search,.btn-clear { min-height:38px; border-radius:8px; padding:8px 14px; font-weight:700; white-space:nowrap; }
.btn-search { border:0; background:#e85d04; color:#fff; } .btn-search:hover { background:#d94f00; }
.btn-clear { border:1px solid #cbd6df; background:#fff; color:#0b3d62; }
.table-wrap { overflow-x:auto; border:1px solid #dbe4eb; border-radius:10px; }
.inventory-table { width:100%; min-width:1180px; border-collapse:separate; border-spacing:0; margin:0; font-size:.86rem; }
.inventory-table th { background:#0b3d62; color:#fff; padding:10px 9px; border-right:1px solid rgba(255,255,255,.18); text-align:left; white-space:nowrap; font-size:.78rem; }
.inventory-table th:first-child { border-top-left-radius:9px; } .inventory-table th:last-child { border-top-right-radius:9px; border-right:0; }
.inventory-table td { padding:10px 9px; border-bottom:1px solid #e4e9ee; border-right:1px solid #edf1f4; color:#26394b; vertical-align:middle; }
.inventory-table td:last-child { border-right:0; } .product-row:hover td { background:#f7fafc; } .product-row.expanded td { background:#eef5fa; }
.inventory-table td strong { display:block; color:#173c5a; } .inventory-table td small { display:block; color:#71808f; font-size:.72rem; margin-top:2px; }
.code-cell { font-weight:800; color:#0b3d62 !important; white-space:nowrap; } .group-text { display:block; max-width:220px; font-size:.76rem; color:#536779; }
.stock-number { font-size:1.05rem; } .status-pill { display:inline-flex; align-items:center; justify-content:center; min-width:78px; padding:5px 9px; border-radius:999px; font-size:.7rem; font-weight:800; }
.status-normal { background:#e8f5ee; color:#137a45; } .status-warning { background:#fff2e7; color:#b84d00; } .status-danger { background:#fdecec; color:#b42318; }
.detail-button { border:1px solid #0b3d62; background:#fff; color:#0b3d62; border-radius:7px; padding:5px 9px; font-size:.72rem; font-weight:800; }
.detail-button:hover { background:#0b3d62; color:#fff; } .empty-row { padding:35px !important; text-align:center; color:#71808f; }
.lot-row td { padding:0 !important; background:#f5f8fb; } .lot-panel { padding:15px 18px 18px; border-top:3px solid #e85d04; }
.lot-panel-head { display:flex; justify-content:space-between; gap:15px; color:#0b3d62; margin-bottom:10px; } .lot-total { white-space:nowrap; }
.lot-table-wrap { overflow-x:auto; } .lot-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #dbe4eb; border-radius:8px; }
.lot-table th,.lot-table td { padding:8px 10px; border-bottom:1px solid #e5ebf0; font-size:.78rem; } .lot-table th { background:#eef5fa; color:#0b3d62; text-align:left; }
.no-lots { color:#71808f; font-size:.82rem; padding:8px 0; } .date-warning { color:#b84d00 !important; font-weight:700; } .date-danger { color:#b42318 !important; font-weight:700; }
@media (max-width:1100px) { .summary-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .filter-actions { grid-column:1/-1; } }
@media (max-width:650px) { .inventory-body{padding:14px;} .summary-grid,.filter-grid{grid-template-columns:1fr;} .filter-actions{grid-column:auto;} }
</style>
