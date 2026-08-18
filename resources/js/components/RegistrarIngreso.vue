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

      <section class="ingreso-section">
        <div class="section-heading-orange">
          <h3>Encabezado de la nota</h3>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label>Almacén</label>
            <input v-model.trim="form.ingreso.almacen" class="form-control">
          </div>

          <div class="col-md-4">
            <label>Procedencia / proveedor</label>
            <input v-model.trim="form.proveedor.nombre" class="form-control">
          </div>

          <div class="col-md-4">
            <label>Teléfono proveedor</label>
            <input v-model.trim="form.proveedor.telefono" class="form-control">
          </div>

          <div class="col-md-3">
            <label>Fecha</label>
            <input v-model="form.ingreso.fecha_ingreso" type="date" class="form-control">
          </div>

          <div class="col-md-3">
            <label>N.º de remisión</label>
            <input v-model.trim="form.ingreso.numero_remision" class="form-control">
          </div>

          <div class="col-md-3">
            <label>N.º de factura</label>
            <input v-model.trim="form.ingreso.numero_factura" class="form-control">
          </div>

          <div class="col-md-3">
            <label>Tipo</label>
            <select v-model="form.ingreso.tipo_ingreso" class="form-select">
              <option value="compra_local">Compra local</option>
              <option value="transferencia">Transferencia</option>
              <option value="donacion">Donación</option>
              <option value="devolucion">Devolución</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        </div>
      </section>

      <section class="ingreso-section">
        <div class="section-heading-orange d-flex justify-content-between align-items-center">
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

          <div class="col-md-6">
            <label>Autorizado por</label>
            <input v-model.trim="form.ingreso.autorizado_por" class="form-control">
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
import axios from 'axios'; import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps({ recibidoPor: { type: String, default: '' } }); const nuevoItem = () => ({ producto_id: null, producto: null, busqueda: '', resultados: [], dropdownStyle: {}, lote: { codigo_lote: '', fecha_vencimiento: '' }, cantidad: 1, precio_unitario: 0 }); const nuevoFormulario = () => ({ proveedor: { nombre: '', telefono: '' }, ingreso: { almacen: 'REGIONAL LA PAZ', fecha_ingreso: new Date().toISOString().slice(0,10), numero_remision: '', numero_factura: '', tipo_ingreso: 'compra_local', observacion: '', recibido_por: props.recibidoPor, autorizado_por: 'Caja de Salud de Caminos y R.A.' }, items: [nuevoItem()] }); const form = ref(nuevoFormulario()), procesando = ref(false), mensaje = ref(''), errorGeneral = ref(''), pdfId = ref(null); let timer;
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
    width: `${ancho}px`
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
    const { data } = await axios.get('api/medicamentos', { params: { buscar: item.busqueda } });
    item.resultados = data;
    await posicionarDropdown(item, indice);
  }, 250);
};
const seleccionarProducto = (item,p) => {
  item.producto_id=p.id;
  item.producto=p;
  item.busqueda=`${p.codigo} - ${p.nombre}`;
  cerrarResultados(item);
};
onMounted(() => {
  window.addEventListener('resize', actualizarPosiciones);
  window.addEventListener('scroll', actualizarPosiciones, true);
});
onBeforeUnmount(() => {
  window.removeEventListener('resize', actualizarPosiciones);
  window.removeEventListener('scroll', actualizarPosiciones, true);
  clearTimeout(timer);
});
const agregarItem=()=>form.value.items.push(nuevoItem()); const totalItem=i=>(Number(i.cantidad)||0)*(Number(i.precio_unitario)||0); const totalGeneral=computed(()=>form.value.items.reduce((sum,i)=>sum+totalItem(i),0)); const moneda=n=>new Intl.NumberFormat('es-BO',{style:'currency',currency:'BOB'}).format(n); const totalLiteral=computed(()=>`${Math.floor(totalGeneral.value)} con ${String(Math.round((totalGeneral.value%1)*100)).padStart(2,'0')}/100`);
const descargarPdf = async () => { const { data } = await axios.get(`api/ingresos/${pdfId.value}/pdf`, { responseType: 'blob' }); const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' })); window.open(url, '_blank'); setTimeout(() => URL.revokeObjectURL(url), 60000); }; const registrar=async()=>{ procesando.value=true; mensaje.value='';errorGeneral.value='';pdfId.value=null;try{const {data}=await axios.post('api/ingresos',form.value);mensaje.value=data.message;pdfId.value=data.pdf_id;form.value=nuevoFormulario();}catch(e){errorGeneral.value=e.response?.data?.message||'Revise los campos obligatorios y seleccione un producto de catálogo en cada fila.';}finally{procesando.value=false;}};
</script>
<style scoped>
.ingreso-card {
  width: 100%;
  margin: 0 auto 28px;
  background: #fff;
  border: 1px solid #e1e7ed;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 28px rgba(20, 48, 70, 0.08);
}

.ingreso-title {
  width: calc(100% - 40px);
  margin: 20px auto 0;
  padding: 14px 20px;
  background: var(--csc-blue-dark, #0b3d62);
  color: #fff;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 3px 10px rgba(11, 61, 98, 0.12);
}

.ingreso-title h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
}

.ingreso-body {
  padding: 22px 28px 8px;
}

.ingreso-section {
  margin-bottom: 22px;
}

.section-heading-orange {
  min-height: 42px;
  margin-bottom: 16px;
  padding: 9px 14px;
  background: var(--csc-orange, #e85d04);
  color: #fff;
  border-radius: 9px;
  box-shadow: 0 3px 9px rgba(232, 93, 4, 0.12);
}

.section-heading-orange h3 {
  margin: 0;
  font-size: 0.96rem;
  font-weight: 700;
}

.ingreso-section label {
  display: block;
  margin-bottom: 6px;
  color: #33485a;
  text-align: center;
  font-size: 0.9rem;
  font-weight: 600;
}

.ingreso-section .form-control,
.ingreso-section .form-select {
  min-height: 40px;
  border: 1px solid #d8e0e7;
  border-radius: 8px;
  box-shadow: none;
  transition: border-color .15s ease, box-shadow .15s ease;
}

.ingreso-section .form-control:focus,
.ingreso-section .form-select:focus {
  border-color: rgba(22, 79, 120, .5);
  box-shadow: 0 0 0 3px rgba(22, 79, 120, .1);
}

.table-card {
  overflow: hidden;
  border: 1px solid #dfe6ec;
  border-radius: 10px;
  background: #fff;
}

.table-csc-blue th {
  background-color: var(--csc-blue-dark, #0b3d62) !important;
  color: #fff !important;
  text-align: center !important;
  vertical-align: middle;
  white-space: nowrap;
  font-size: .82rem;
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
  font-size: .86rem;
  color: #2e4354;
  vertical-align: middle;
}

/* Separadores verticales sutiles para distinguir claramente cada dato. */
.detalle th + th {
  border-left: 1px solid rgba(255,255,255,.24) !important;
}

.detalle td + td {
  border-left: 1px solid #dfe7ed;
}

/* Separadores ligeramente más marcados entre los grupos de identificación
   del producto y los datos de lote/cantidad/precio. */
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
  background: transparent;
  border: 1px solid rgba(255,255,255,.85);
  border-radius: 7px;
  font-size: .8rem;
  font-weight: 600;
  padding: 5px 10px;
}

.btn-add-item:hover {
  background: rgba(255,255,255,.14);
  color: #fff;
}

.lista-resultados {
  position: fixed;
  z-index: 3000;
  max-height: 220px;
  overflow-y: auto;
  overflow-x: hidden;
  background: #fff;
  border: 1px solid #cbd9e3;
  border-radius: 9px;
  box-shadow: 0 12px 28px rgba(11, 61, 98, .18);
  padding: 4px 0;
}

.lista-resultados button {
  width: 100%;
  font-size: .8rem;
  text-align: left;
  border: 0;
  padding: 8px 12px;
  color: #284257;
  white-space: normal;
}
.lista-resultados button:hover {
  background: #f2f7fa;
  color: var(--csc-blue-dark, #0b3d62);
}
.lista-resultados button strong {
  color: var(--csc-blue-dark, #0b3d62);
}
.lista-resultados button small {
  color: #60778a;
  margin-top: 2px;
}

.total-box {
  margin-top: 14px;
  padding: 10px 4px 4px;
  text-align: right;
}

.cierre-section {
  padding-top: 2px;
}

.ingreso-footer {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin: 0 28px 24px;
  padding-top: 18px;
  border-top: 1px solid #e8edf1;
}

@media (max-width: 900px) {
  .ingreso-title {
    width: calc(100% - 24px);
    margin-top: 12px;
  }

  .ingreso-body {
    padding: 18px 16px 6px;
  }

  .section-heading-orange {
    border-radius: 8px;
  }

  .ingreso-footer {
    margin-left: 16px;
    margin-right: 16px;
  }
}
</style>
