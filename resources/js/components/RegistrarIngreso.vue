<template>
  <form class="ingreso-card" @submit.prevent="registrar">
    <div class="ingreso-title">
      <h2>Nota de ingreso al almacén</h2>
    </div>

    <div class="ingreso-body">
      <div v-if="mensaje" class="alert alert-success rounded-3">
        {{ mensaje }}
        <button v-if="pdfId" type="button" class="btn btn-link p-0 ms-2 align-baseline" @click="descargarPdf">
          Abrir PDF
        </button>
      </div>

      <div v-if="errorGeneral" class="alert alert-danger rounded-3">{{ errorGeneral }}</div>

      <section class="ingreso-section ingreso-cabecera">
        <div class="section-heading">
          <div>
            <span class="section-kicker">REGISTRO DE INGRESO</span>
            <h3>Datos de la nota</h3>
          </div>
          <div class="nota-destacada">
            <span>N.º de nota</span>
            <strong>{{ siguienteNumeroNota || 'Consultando…' }}</strong>
          </div>
        </div>

        <div class="section-content">
          <div class="row g-3">
            <div class="col-lg-3 col-md-6">
              <label>Almacén</label>
              <input v-model.trim="form.ingreso.almacen" class="form-control">
            </div>

            <div class="col-lg-5 col-md-6">
              <label>Procedencia / proveedor</label>
              <div class="position-relative">
                <input
                  v-model.trim="form.proveedor.nombre"
                  id="busqueda-proveedor"
                  class="form-control"
                  placeholder="Buscar proveedor oficial..."
                  autocomplete="off"
                  @input="buscarProveedores"
                  @focus="buscarProveedores"
                  @keydown.escape="cerrarProveedores"
                >

                <div v-if="proveedoresResultados.length" class="proveedores-resultados shadow">
                  <button
                    v-for="proveedor in proveedoresResultados"
                    :key="proveedor.clave"
                    type="button"
                    class="list-group-item list-group-item-action"
                    @click="seleccionarProveedor(proveedor)"
                  >
                    <strong>{{ proveedor.nombre }}</strong>
                    <small v-if="proveedor.telefono" class="d-block">Tel.: {{ proveedor.telefono }}</small>
                  </button>
                </div>
              </div>

              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                @click="alternarFormularioProveedor"
              >
                {{ mostrarFormularioProveedor ? 'Cancelar nuevo proveedor' : '+ Añadir proveedor' }}
              </button>

              <div v-if="mostrarFormularioProveedor" class="nuevo-proveedor-box mt-2">
                <div class="small fw-semibold text-primary mb-2">Nuevo proveedor</div>
                <input
                  v-model.trim="nuevoProveedor.nombre"
                  class="form-control form-control-sm mb-2"
                  placeholder="Nombre del proveedor"
                  maxlength="255"
                  @keydown.enter.prevent="guardarProveedor"
                >
                <input
                  v-model.trim="nuevoProveedor.telefono"
                  class="form-control form-control-sm mb-2"
                  placeholder="Teléfono (opcional)"
                  maxlength="30"
                >
                <input
                  v-model.trim="nuevoProveedor.nit"
                  class="form-control form-control-sm mb-2"
                  placeholder="NIT (opcional)"
                  maxlength="100"
                >
                <div v-if="errorProveedor" class="alert alert-danger py-2 px-3 small mb-2">{{ errorProveedor }}</div>
                <button
                  type="button"
                  class="btn btn-primary btn-sm w-100"
                  :disabled="guardandoProveedor || !nuevoProveedor.nombre"
                  @click="guardarProveedor"
                >
                  {{ guardandoProveedor ? 'Guardando…' : 'Guardar proveedor' }}
                </button>
              </div>
            </div>

            <div class="col-lg-2 col-md-6">
              <label>Teléfono proveedor</label>
              <input v-model.trim="form.proveedor.telefono" class="form-control">
            </div>

            <div class="col-lg-2 col-md-6">
              <label>Fecha</label>
              <input v-model="form.ingreso.fecha_ingreso" type="date" class="form-control">
            </div>

            <div class="col-lg-3 col-md-6">
              <label>N.º de Orden de Compra</label>
              <input
                v-model.trim="form.ingreso.numero_orden_compra"
                class="form-control"
                placeholder="Ingrese el número de orden"
                maxlength="100"
              >
              <small class="field-help">Campo manual · lo registra la encargada de almacén.</small>
            </div>

            <div class="col-lg-3 col-md-6">
              <label>N.º de remisión</label>
              <input v-model.trim="form.ingreso.numero_remision" class="form-control">
            </div>

            <div class="col-lg-3 col-md-6">
              <label>N.º de factura</label>
              <input v-model.trim="form.ingreso.numero_factura" class="form-control">
            </div>

            <div class="col-lg-3 col-md-6">
              <label>Tipo de ingreso</label>
              <select v-model="form.ingreso.tipo_ingreso" class="form-select">
                <option value="compra_local">Compra local</option>
                <option value="transferencia">Transferencia</option>
                <option value="transferencia_regional">Transferencia entre regional</option>
                <option value="donacion">Donación</option>
                <option value="devolucion">Devolución</option>
                <option value="otro">Otro</option>
              </select>
            </div>
          </div>
        </div>
      </section>

      <section class="ingreso-section">
        <div class="section-heading d-flex justify-content-between align-items-center">
          <h3>Detalle de ítems</h3>
          <button type="button" class="btn btn-add-item" @click="agregarItem">+ Agregar ítem</button>
        </div>

        <div class="table-card">
          <div class="table-responsive">
            <table class="table table-sm align-middle detalle mb-0">
              <thead class="table-csc-blue">
                <tr>
                  <th>Buscar producto</th>
                  <th>Partida</th>
                  <th>LINAME</th>
                  <th>Descripción / concentración</th>
                  <th>Forma / unidad</th>
                  <th>Lote</th>
                  <th>Vencimiento</th>
                  <th>Cant.</th>
                  <th>P. unitario</th>
                  <th>Importe</th>
                  <th></th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="(item, indice) in form.items" :key="indice">
                  <td class="position-relative">
                    <input
                      v-model.trim="item.busqueda"
                      :id="`busqueda-producto-${indice}`"
                      class="form-control form-control-sm"
                      placeholder="Nombre o código"
                      autocomplete="off"
                      @input="buscarProductos(item, indice)"
                      @focus="buscarProductos(item, indice)"
                      @keydown.escape="cerrarResultados(item)"
                    >

                    <Teleport to="body">
                      <div
                        v-if="item.resultados.length"
                        class="lista-resultados shadow"
                        :style="item.dropdownStyle"
                      >
                        <button
                          v-for="producto in item.resultados"
                          :key="producto.id"
                          type="button"
                          class="list-group-item list-group-item-action"
                          @click="seleccionarProducto(item, producto)"
                        >
                          <strong>{{ producto.codigo }}</strong> - {{ descripcionProducto(producto) }}
                          <small class="d-block">
                            {{ producto.partida_presupuestaria?.codigo }}
                            <template v-if="valorCatalogo(producto.forma_farmaceutica)">
                              · {{ valorCatalogo(producto.forma_farmaceutica) }}
                            </template>
                          </small>
                        </button>
                      </div>
                    </Teleport>
                  </td>

                  <td>{{ item.producto?.partida_presupuestaria?.codigo || '—' }}</td>
                  <td>{{ item.producto?.codigo || '—' }}</td>
                  <td>{{ item.producto ? descripcionProducto(item.producto) : '—' }}</td>
                  <td>{{ item.producto ? formatoFormaUnidad(item.producto.forma_farmaceutica, item.producto.unidad_presentacion) : '—' }}</td>

                  <td>
                    <input v-model.trim="item.lote.codigo_lote" class="form-control form-control-sm" placeholder="Lote o procedencia">
                  </td>

                  <td>
                    <input v-model="item.lote.fecha_vencimiento" type="date" class="form-control form-control-sm">
                  </td>

                  <td>
                    <input v-model.number="item.cantidad" type="number" min="1" class="form-control form-control-sm">
                  </td>

                  <td>
                    <input v-model.number="item.precio_unitario" type="number" min="0" step="0.01" class="form-control form-control-sm">
                  </td>

                  <td class="text-end fw-bold importe-cell">{{ moneda(totalItem(item)) }}</td>

                  <td>
                    <button
                      v-if="form.items.length > 1"
                      type="button"
                      class="btn btn-outline-danger btn-sm"
                      @click="form.items.splice(indice,1)"
                    >
                      ×
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="total-box">
          <div class="fs-5 fw-bold">Total: {{ moneda(totalGeneral) }}</div>
          <small class="text-uppercase text-muted">{{ totalLiteral }} bolivianos</small>
        </div>
      </section>

      <section class="ingreso-section cierre-section">
        <div class="row g-3">
          <div class="col-md-6">
            <label>Recibido por</label>
            <input v-model.trim="form.ingreso.recibido_por" class="form-control">
          </div>

          <div class="col-12">
            <label>Observaciones</label>
            <textarea v-model.trim="form.ingreso.observacion" class="form-control" rows="3"></textarea>
          </div>
        </div>
      </section>
    </div>

    <div class="ingreso-footer">
      <button class="btn btn-csc-orange px-5 py-2 fw-bold" :disabled="procesando">
        {{ procesando ? 'Guardando…' : 'Registrar nota de ingreso' }}
      </button>
    </div>
  </form>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { proveedoresOficiales } from '../data/proveedoresOficiales.js';

const props = defineProps({
  recibidoPor: { type: String, default: '' },
  regional: { type: String, default: 'La Paz' },
});

const nuevoItem = () => ({
  producto_id: null,
  producto: null,
  busqueda: '',
  resultados: [],
  dropdownStyle: {},
  lote: { codigo_lote: '', fecha_vencimiento: '' },
  cantidad: 1,
  precio_unitario: 0,
});

const nombreAlmacenRegional = () => {
  const regional = String(props.regional || 'La Paz').trim();
  return regional ? `REGIONAL ${regional.toUpperCase()}` : 'REGIONAL LA PAZ';
};

const nuevoFormulario = () => ({
  proveedor: { nombre: '', telefono: '' },
  ingreso: {
    almacen: nombreAlmacenRegional(),
    fecha_ingreso: new Date().toISOString().slice(0, 10),
    numero_remision: '',
    numero_factura: '',
    numero_orden_compra: '',
    tipo_ingreso: 'compra_local',
    observacion: '',
    recibido_por: props.recibidoPor,
  },
  items: [nuevoItem()],
});

const form = ref(nuevoFormulario());
const procesando = ref(false);
const mensaje = ref('');
const errorGeneral = ref('');
const pdfId = ref(null);
const siguienteNumeroNota = ref('');
const proveedoresResultados = ref([]);
const mostrarFormularioProveedor = ref(false);
const guardandoProveedor = ref(false);
const errorProveedor = ref('');
const nuevoProveedor = ref({ nombre: '', telefono: '', nit: '' });

let timer;
let proveedorTimer;

const normalizar = texto => String(texto ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ')
  .trim();

const puntuarProveedor = (nombre, consulta) => {
  const n = normalizar(nombre);
  const q = normalizar(consulta);
  if (!q) return 1;
  if (n === q) return 1000;
  if (n.startsWith(q)) return 900;
  if (n.includes(q)) return 800;

  const tokens = q.split(/\s+/).filter(Boolean);
  const palabras = n.split(/\s+/).filter(Boolean);
  let puntuacion = 0;
  for (const token of tokens) {
    if (palabras.some(p => p.startsWith(token))) puntuacion += 120;
    else if (palabras.some(p => p.includes(token))) puntuacion += 70;
  }
  return puntuacion;
};

const fusionarProveedores = (oficiales, registrados) => {
  const mapa = new Map();
  [...oficiales, ...registrados].forEach((proveedor, indice) => {
    const nombre = typeof proveedor === 'string' ? proveedor : proveedor.nombre;
    if (!nombre) return;
    const clave = normalizar(nombre);
    if (!mapa.has(clave)) {
      mapa.set(clave, {
        id: typeof proveedor === 'string' ? null : proveedor.id,
        nombre,
        telefono: typeof proveedor === 'string' ? '' : (proveedor.telefono || ''),
        oficial: typeof proveedor === 'string',
        clave: `${clave}-${indice}`,
      });
    } else if (typeof proveedor !== 'string' && proveedor.telefono) {
      mapa.get(clave).telefono = proveedor.telefono;
    }
  });
  return [...mapa.values()];
};

const actualizarResultadosProveedores = (consulta, registrados = []) => {
  const todos = fusionarProveedores(proveedoresOficiales, registrados);
  proveedoresResultados.value = todos
    .map(proveedor => ({ ...proveedor, puntuacion: puntuarProveedor(proveedor.nombre, consulta) }))
    .filter(proveedor => !consulta || proveedor.puntuacion > 0)
    .sort((a, b) => b.puntuacion - a.puntuacion || a.nombre.localeCompare(b.nombre, 'es'))
    .slice(0, 12);
};

const buscarProveedores = () => {
  clearTimeout(proveedorTimer);
  const consulta = form.value.proveedor.nombre;
  actualizarResultadosProveedores(consulta);

  proveedorTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/proveedores', { params: { buscar: consulta } });
      actualizarResultadosProveedores(consulta, Array.isArray(data) ? data : []);
    } catch {
      // La lista oficial local sigue disponible aunque la API no responda.
    }
  }, 180);
};

const cerrarProveedores = () => {
  proveedoresResultados.value = [];
};

const seleccionarProveedor = proveedor => {
  form.value.proveedor.nombre = proveedor.nombre;
  form.value.proveedor.telefono = proveedor.telefono || '';
  cerrarProveedores();
};

const alternarFormularioProveedor = () => {
  mostrarFormularioProveedor.value = !mostrarFormularioProveedor.value;
  errorProveedor.value = '';
  if (!mostrarFormularioProveedor.value) {
    nuevoProveedor.value = { nombre: '', telefono: '', nit: '' };
  }
};

const guardarProveedor = async () => {
  if (!nuevoProveedor.value.nombre) return;
  guardandoProveedor.value = true;
  errorProveedor.value = '';

  try {
    const { data } = await axios.post('api/proveedores', nuevoProveedor.value);
    const proveedor = data.proveedor;
    form.value.proveedor.nombre = proveedor.nombre;
    form.value.proveedor.telefono = proveedor.telefono || '';
    proveedoresResultados.value = [];
    mostrarFormularioProveedor.value = false;
    nuevoProveedor.value = { nombre: '', telefono: '', nit: '' };
  } catch (error) {
    errorProveedor.value = error.response?.data?.message || 'No se pudo guardar el proveedor.';
  } finally {
    guardandoProveedor.value = false;
  }
};

const cargarSiguienteNumero = async () => {
  try {
    const { data } = await axios.get('api/ingresos/siguiente-numero');
    siguienteNumeroNota.value = data.numero_nota || '';
  } catch {
    siguienteNumeroNota.value = '';
  }
};

const valorCatalogo = valor => {
  const texto = String(valor ?? '').trim();
  return texto && texto.toLowerCase() !== 'no aplica' ? texto : '';
};

const descripcionProducto = producto => producto?.nombre || '';
const formatoFormaUnidad = (forma, unidad) => {
  const valores = [forma, unidad].map(valorCatalogo).filter(Boolean);
  const unicos = [...new Set(valores)];
  return unicos.length ? unicos.join(' / ') : '—';
};

const posicionarDropdown = async (item, indice) => {
  await nextTick();
  const input = document.getElementById(`busqueda-producto-${indice}`);
  if (!input || !item.resultados.length) return;
  const rect = input.getBoundingClientRect();
  const margen = 8;
  const alturaEstimada = Math.min(220, Math.max(72, item.resultados.length * 64));
  const ancho = Math.min(Math.max(rect.width, 320), window.innerWidth - (margen * 2));
  const left = Math.min(Math.max(rect.left, margen), window.innerWidth - ancho - margen);
  const espacioAbajo = window.innerHeight - rect.bottom;
  const mostrarArriba = espacioAbajo < alturaEstimada + margen && rect.top > alturaEstimada + margen;
  const top = mostrarArriba ? Math.max(margen, rect.top - alturaEstimada - 4) : rect.bottom + 4;
  item.dropdownStyle = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    width: `${ancho}px`,
  };
};

const actualizarPosiciones = () => {
  form.value.items.forEach((item, indice) => {
    if (item.resultados.length) posicionarDropdown(item, indice);
  });
};

const cerrarResultados = item => {
  item.resultados = [];
  item.dropdownStyle = {};
};

const buscarProductos = (item, indice) => {
  clearTimeout(timer);
  if (item.busqueda.length < 2) {
    cerrarResultados(item);
    return;
  }
  timer = setTimeout(async () => {
    try {
      const { data } = await axios.get('api/medicamentos', { params: { buscar: item.busqueda } });
      item.resultados = data;
      await posicionarDropdown(item, indice);
    } catch {
      cerrarResultados(item);
    }
  }, 250);
};

const seleccionarProducto = (item, p) => {
  item.producto_id = p.id;
  item.producto = p;
  item.busqueda = `${p.codigo} - ${p.nombre}`;
  cerrarResultados(item);
};

onMounted(() => {
  cargarSiguienteNumero();
  window.addEventListener('resize', actualizarPosiciones);
  window.addEventListener('scroll', actualizarPosiciones, true);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', actualizarPosiciones);
  window.removeEventListener('scroll', actualizarPosiciones, true);
  clearTimeout(timer);
  clearTimeout(proveedorTimer);
});

const agregarItem = () => form.value.items.push(nuevoItem());
const totalItem = i => (Number(i.cantidad) || 0) * (Number(i.precio_unitario) || 0);
const totalGeneral = computed(() => form.value.items.reduce((sum, i) => sum + totalItem(i), 0));
const moneda = n => new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);
const totalLiteral = computed(() => `${Math.floor(totalGeneral.value)} con ${String(Math.round((totalGeneral.value % 1) * 100)).padStart(2, '0')}/100`);

const descargarPdf = async () => {
  const { data } = await axios.get(`api/ingresos/${pdfId.value}/pdf`, { responseType: 'blob' });
  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
  window.open(url, '_blank');
  setTimeout(() => URL.revokeObjectURL(url), 60000);
};

const registrar = async () => {
  procesando.value = true;
  mensaje.value = '';
  errorGeneral.value = '';
  pdfId.value = null;

  try {
    const { data } = await axios.post('api/ingresos', form.value);
    mensaje.value = data.message;
    pdfId.value = data.pdf_id;
    form.value = nuevoFormulario();
    await cargarSiguienteNumero();
  } catch (e) {
    errorGeneral.value = e.response?.data?.message || 'Revise los campos obligatorios y seleccione un producto de catálogo en cada fila.';
    await cargarSiguienteNumero();
  } finally {
    procesando.value = false;
  }
};
</script>

<style scoped>
.ingreso-card {
  width: 100%;
  margin: 0 auto 28px;
  background: #fff;
  border: 1px solid #dfe7ee;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 18px rgba(20, 48, 70, 0.07);
}

.ingreso-title {
  margin: 0;
  padding: 18px 24px;
  background: var(--csc-blue-dark, #0b3d62);
  color: #fff;
  border-bottom: 4px solid var(--csc-orange, #e85d04);
  text-align: left;
}

.ingreso-title h2 {
  margin: 0;
  font-size: 1.28rem;
  font-weight: 700;
  letter-spacing: .01em;
}

.ingreso-title::after {
  content: 'Registro y control de entradas de medicamentos al almacén';
  display: block;
  margin-top: 3px;
  color: rgba(255,255,255,.82);
  font-size: .82rem;
}

.ingreso-body {
  padding: 22px;
}

.ingreso-section {
  margin-bottom: 22px;
  padding: 0;
}

.section-heading {
  min-height: 54px;
  padding: 10px 14px;
  background: #f6f9fb;
  border: 1px solid #dfe7ee;
  border-left: 5px solid var(--csc-orange, #e85d04);
  border-radius: 9px 9px 0 0;
  color: var(--csc-blue-dark, #0b3d62);
}

.section-heading h3 {
  margin: 0;
  font-size: .98rem;
  font-weight: 750;
}

.section-kicker {
  display: block;
  margin-bottom: 2px;
  color: var(--csc-orange, #e85d04);
  font-size: .66rem;
  font-weight: 800;
  letter-spacing: .08em;
}

.section-content {
  padding: 18px 16px;
  border: 1px solid #dfe7ee;
  border-top: 0;
  border-radius: 0 0 9px 9px;
  background: #fff;
}

.nota-destacada {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 11px;
  background: var(--csc-blue-dark, #0b3d62);
  color: #fff;
  border-radius: 7px;
}

.nota-destacada span {
  font-size: .68rem;
  text-transform: uppercase;
  opacity: .78;
  letter-spacing: .05em;
}

.nota-destacada strong {
  font-size: .95rem;
  letter-spacing: .04em;
}

.ingreso-section label {
  display: block;
  margin-bottom: 6px;
  color: #33485a;
  font-size: .82rem;
  font-weight: 700;
}

.ingreso-section .form-control,
.ingreso-section .form-select {
  min-height: 40px;
  border: 1px solid #d3dfe7;
  border-radius: 7px;
  background: #fff;
  box-shadow: none;
  transition: border-color .15s ease, box-shadow .15s ease;
}

.ingreso-section .form-control:focus,
.ingreso-section .form-select:focus {
  border-color: var(--csc-blue, #164f78);
  box-shadow: 0 0 0 3px rgba(22,79,120,.09);
}

.field-help {
  display: block;
  margin-top: 4px;
  color: #7a8b98;
  font-size: .68rem;
}

.table-card {
  overflow: hidden;
  border: 1px solid #dfe7ee;
  border-radius: 0 0 9px 9px;
  background: #fff;
}

.table-csc-blue th {
  background-color: var(--csc-blue-dark, #0b3d62) !important;
  color: #fff !important;
  text-align: center !important;
  vertical-align: middle;
  white-space: nowrap;
  font-size: .78rem;
  padding: 9px 7px;
  border-color: rgba(255,255,255,.25) !important;
}

.detalle {
  min-width: 1400px;
}

.detalle td {
  padding: 7px 6px;
  border-top: 1px solid #e5ebf0;
  border-bottom: 1px solid #e5ebf0;
  border-right: 1px solid #d9e3ea;
  font-size: .84rem;
  color: #2e4354;
  vertical-align: middle;
}

.detalle th + th {
  border-left: 1px solid rgba(255,255,255,.24) !important;
}

.detalle td + td {
  border-left: 1px solid #dfe7ed;
}

.detalle th:nth-child(2),
.detalle th:nth-child(5),
.detalle th:nth-child(6),
.detalle th:nth-child(8),
.detalle th:nth-child(10),
.detalle td:nth-child(2),
.detalle td:nth-child(5),
.detalle td:nth-child(6),
.detalle td:nth-child(8),
.detalle td:nth-child(10) {
  border-left: 2px solid rgba(11,61,98,.16) !important;
}

.detalle tbody tr:hover {
  background: #f8fbfd;
}

.detalle th {
  white-space: nowrap;
}

.detalle td:nth-child(2),
.detalle td:nth-child(3) {
  white-space: nowrap;
  text-align: center;
  font-weight: 500;
}

.detalle .form-control-sm {
  min-height: 32px;
  border-radius: 6px;
}

.importe-cell {
  color: var(--csc-blue-dark, #0b3d62);
  white-space: nowrap;
}

.btn-add-item {
  color: #fff;
  background: var(--csc-orange, #e85d04);
  border: 1px solid var(--csc-orange, #e85d04);
  border-radius: 7px;
  font-size: .78rem;
  font-weight: 700;
  padding: 5px 10px;
}

.btn-add-item:hover {
  background: var(--csc-orange-hover, #d94f00);
  color: #fff;
}

.lista-resultados,
.proveedores-resultados {
  background: #fff;
  border: 1px solid #cbd9e3;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(11,61,98,.18);
}

.lista-resultados {
  position: fixed;
  z-index: 3000;
  max-height: 220px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 4px 0;
}

.lista-resultados button,
.proveedores-resultados button {
  width: 100%;
  font-size: .8rem;
  text-align: left;
  border: 0;
  padding: 8px 12px;
  color: #284257;
  white-space: normal;
}

.lista-resultados button:hover,
.proveedores-resultados button:hover {
  background: #f2f7fa;
  color: var(--csc-blue-dark, #0b3d62);
}

.lista-resultados button strong,
.proveedores-resultados button strong {
  color: var(--csc-blue-dark, #0b3d62);
}

.lista-resultados button small,
.proveedores-resultados button small {
  color: #60778a;
  margin-top: 2px;
}

.proveedores-resultados {
  position: absolute;
  z-index: 2500;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  max-height: 260px;
  overflow-y: auto;
  padding: 4px 0;
}

.nuevo-proveedor-box {
  padding: 10px;
  background: #f7fafc;
  border: 1px solid #d9e4eb;
  border-radius: 8px;
}

.total-box {
  margin-top: 14px;
  padding: 12px 14px;
  text-align: right;
  background: #f7fafc;
  border: 1px solid #e1e8ee;
  border-radius: 8px;
}

.total-box .fs-5 {
  color: var(--csc-blue-dark, #0b3d62);
}

.cierre-section {
  padding: 0;
}

.cierre-section > .row {
  padding: 18px 16px;
  margin: 0;
  border: 1px solid #dfe7ee;
  border-radius: 9px;
  background: #fff;
}

.ingreso-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin: 0;
  padding: 16px 22px 22px;
  border-top: 1px solid #e8edf1;
  background: #fff;
}

.ingreso-footer .btn {
  min-width: 220px;
}

@media (max-width: 900px) {
  .ingreso-body {
    padding: 16px;
  }

  .section-heading {
    align-items: flex-start !important;
    gap: 10px;
  }

  .nota-destacada {
    padding: 6px 9px;
  }

  .ingreso-footer {
    padding: 14px 16px 18px;
  }

  .ingreso-footer .btn {
    width: 100%;
  }
}
</style>
