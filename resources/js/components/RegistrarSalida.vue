<template>
  <form class="salida-card" @submit.prevent="procesarSalida" novalidate>
    <div v-if="mensajeExito" class="alert alert-success rounded-3 salida-alert">
      {{ mensajeExito }} <button type="button" class="btn-close" @click="mensajeExito = ''"></button>
    </div>
    <div v-if="error" class="alert alert-danger rounded-3 salida-alert">
      {{ error }} <button type="button" class="btn-close" @click="error = ''"></button>
    </div>

    <div class="salida-hero">
      <div><h2>Nota de salida del almacén</h2><p>Registro y control de salidas de medicamentos del almacén.</p></div>
      <div class="salida-note"><span>N.º DE NOTA DE SALIDA</span><strong>{{ numeroSalida ?? 'Consultando…' }}</strong></div>
    </div>

    <div class="salida-body">
      <section class="salida-section">
        <div class="section-heading"><div><span class="section-kicker">REGISTRO DE SALIDA</span><h3>Datos de la nota</h3></div></div>
        <div class="section-content">
          <div class="row g-3">
            <div class="col-lg-3 col-md-6"><label>Fecha de salida</label><input v-model="form.fecha_salida" type="date" class="form-control" required></div>
            <div class="col-lg-3 col-md-6"><label>N.º de salida</label><input :value="numeroSalida ?? 'Cargando...'" type="text" class="form-control fw-bold" readonly><small class="field-help">Correlativo automático.</small></div>
            <div class="col-lg-6"><label>Almacén de origen</label><input v-model.trim="form.almacen_origen" type="text" class="form-control" maxlength="150"></div>

            <div class="col-lg-6"><label>Destino / establecimiento</label><select v-model="form.establecimiento_id" class="form-select" required :disabled="cargandoEstablecimientos">
              <option value="" disabled>{{ cargandoEstablecimientos ? 'Cargando establecimientos...' : 'Seleccione un establecimiento...' }}</option>
              <option v-for="establecimiento in establecimientos" :key="establecimiento.id" :value="establecimiento.id">{{ establecimiento.nombre }}<span v-if="establecimiento.sigla"> ({{ establecimiento.sigla }})</span></option>
            </select></div>
            <div class="col-lg-6"><label>N.º de pedido / documento</label><input v-model.trim="form.numero_pedido" type="text" class="form-control" maxlength="100" placeholder="Número del documento físico"></div>

            <div class="col-lg-6"><label>Responsable que solicita</label>
              <input v-model.trim="form.solicitado_por" @input="sincronizarResponsable" type="text" class="form-control" placeholder="Nombre del solicitante" required>
            </div>
            <div class="col-lg-6"><label>Responsable que recibe</label>
              <div class="receive-field">
                <input v-model.trim="form.entregado_a" type="text" class="form-control" placeholder="Nombre del responsable que recibe" :readonly="mismoResponsable">
                <label class="same-person-check"><input v-model="mismoResponsable" type="checkbox" @change="cambiarModoResponsable"><span>Mismo responsable que solicita</span></label>
              </div>
              <small class="field-help">Desmarque la casilla cuando reciba otra persona.</small>
            </div>
            <div class="col-12"><label>Observaciones</label><textarea v-model.trim="form.observaciones" class="form-control" rows="2" placeholder="Observaciones adicionales"></textarea></div>
          </div>
        </div>
      </section>

      <section class="salida-section">
        <div class="section-heading"><div><span class="section-kicker">DETALLE</span><h3>Agregar productos</h3></div><span class="count-badge">{{ detalles.length }} {{ detalles.length === 1 ? 'medicamento' : 'medicamentos' }}</span></div>
        <div class="section-content">
          <div class="row g-3">
            <div class="col-lg-6 position-relative">
              <label>Buscar producto *</label>
              <div class="input-group"><span class="input-group-text search-icon">⌕</span><input ref="buscadorMedicamento" v-model="textoBusqueda" type="text" class="form-control" placeholder="Código LINAME o nombre del producto..." autocomplete="off" @input="buscarMedicamentos"></div>
              <div v-if="mostrarResultados" class="product-results">
                <button v-for="medicamento in resultadosMedicamentos" :key="medicamento.id" type="button" @click="seleccionarMedicamento(medicamento)">
                  <strong>{{ medicamento.nombre }}</strong><small>{{ medicamento.codigo }}<span v-if="medicamento.concentracion"> · {{ medicamento.concentracion }}</span><span v-if="medicamento.forma_farmaceutica"> · {{ medicamento.forma_farmaceutica }}</span></small>
                </button>
                <div v-if="resultadosMedicamentos.length === 0 && !buscandoMedicamentos" class="result-empty">No se encontraron productos.</div>
                <div v-if="buscandoMedicamentos" class="result-empty">Buscando productos...</div>
              </div>
              <div v-if="medicamentoSeleccionado" class="selected-product"><div><strong>{{ medicamentoSeleccionado.nombre }}</strong><small>{{ medicamentoSeleccionado.codigo }}<span v-if="medicamentoSeleccionado.concentracion"> · {{ medicamentoSeleccionado.concentracion }}</span></small></div><button type="button" @click="limpiarSeleccionMedicamento">×</button></div>
            </div>
            <div class="col-lg-4"><label>Lote *</label><select v-model="loteSeleccionadoId" class="form-select" :disabled="!medicamentoSeleccionado || cargandoLotes">
              <option value="" disabled>{{ !medicamentoSeleccionado ? 'Seleccione primero un medicamento' : cargandoLotes ? 'Cargando lotes...' : 'Seleccione un lote...' }}</option>
              <option v-for="lote in lotesDisponibles" :key="lote.id" :value="lote.id">{{ lote.codigo_lote }} — Stock: {{ lote.cantidad_actual }} — Vence: {{ formatearFecha(lote.fecha_vencimiento) }}</option>
            </select><div v-if="loteSeleccionado" class="lot-info">Stock: <strong>{{ loteSeleccionado.cantidad_actual }}</strong> · Vence: {{ formatearFecha(loteSeleccionado.fecha_vencimiento) }}</div></div>
            <div class="col-lg-2"><label>Cantidad *</label><input v-model.number="cantidadDetalle" type="number" min="1" :max="loteSeleccionado?.cantidad_actual || undefined" class="form-control fw-bold" :disabled="!loteSeleccionado"></div>
          </div>
          <div class="add-actions"><button type="button" class="btn btn-csc-orange px-4 fw-bold" :disabled="!puedeAgregarDetalle || procesando" @click="agregarDetalle">{{ indiceEditando !== null ? 'Actualizar producto' : '+ Agregar producto' }}</button><button v-if="indiceEditando !== null" type="button" class="btn btn-outline-csc" @click="cancelarEdicion">Cancelar edición</button><span v-if="loteSeleccionado && cantidadDetalle > loteSeleccionado.cantidad_actual" class="text-danger small fw-bold">La cantidad supera el stock disponible.</span></div>
        </div>
      </section>

      <section class="salida-section">
        <div class="section-heading"><div><span class="section-kicker">PRODUCTOS</span><h3>Detalle de la salida</h3></div><span class="count-badge">{{ totalUnidades }} unidades</span></div>
        <div v-if="detalles.length" class="table-responsive">
          <table class="salida-table"><thead><tr><th>#</th><th>Medicamento</th><th>Lote</th><th>Vencimiento</th><th class="text-center">Cantidad</th><th class="text-center">Acciones</th></tr></thead>
          <tbody><tr v-for="(detalle, index) in detalles" :key="detalle.lote_id">
            <td>{{ index + 1 }}</td><td><strong>{{ detalle.nombre }}</strong><small>{{ detalle.codigo }}<span v-if="detalle.concentracion"> · {{ detalle.concentracion }}</span></small></td><td>{{ detalle.codigo_lote }}</td><td>{{ formatearFecha(detalle.fecha_vencimiento) }}</td><td class="text-center fw-bold">{{ detalle.cantidad }}</td>
            <td class="text-center"><button type="button" class="action-blue" @click="editarDetalle(index)">Editar</button><button type="button" class="action-orange" @click="eliminarDetalle(index)">Eliminar</button></td>
          </tr></tbody>
          <tfoot><tr><td colspan="4" class="text-end fw-bold">Total de unidades:</td><td class="text-center fw-bold">{{ totalUnidades }}</td><td></td></tr></tfoot></table>
        </div>
        <div v-else class="empty-details"><strong>Todavía no hay productos agregados.</strong><span>Busque un producto arriba y agréguelo al detalle.</span></div>
      </section>

      <div class="salida-footer-actions"><span v-if="detalles.length === 0">Agregue al menos un medicamento para guardar la salida.</span><button type="submit" class="btn btn-csc-orange px-5 shadow fw-bold" :disabled="procesando || !puedeGuardar"><span v-if="procesando" class="spinner-border spinner-border-sm me-2"></span>{{ procesando ? 'Guardando salida...' : 'Guardar salida' }}</button></div>
    </div>
  </form>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import axios from 'axios'


/*
|--------------------------------------------------------------------------
| ESTADO DE LA CABECERA
|--------------------------------------------------------------------------
*/

const form = ref({
  fecha_salida: obtenerFechaLocal(),
  almacen_origen: 'REGIONAL LA PAZ',
  establecimiento_id: '',
  numero_pedido: '',
  solicitado_por: '',
  entregado_a: '',
  observaciones: ''
})

const numeroSalida = ref(null)
const cargandoNumeroSalida = ref(false)


/*
|--------------------------------------------------------------------------
| ESTABLECIMIENTOS
|--------------------------------------------------------------------------
*/

const establecimientos = ref([])
const cargandoEstablecimientos = ref(false)


/*
|--------------------------------------------------------------------------
| BUSCADOR DE MEDICAMENTOS
|--------------------------------------------------------------------------
*/

const textoBusqueda = ref('')
const resultadosMedicamentos = ref([])
const medicamentoSeleccionado = ref(null)

const buscandoMedicamentos = ref(false)
const mostrarResultados = ref(false)

const buscadorMedicamento = ref(null)

let temporizadorBusqueda = null


/*
|--------------------------------------------------------------------------
| LOTES
|--------------------------------------------------------------------------
*/

const lotesDisponibles = ref([])
const loteSeleccionadoId = ref('')
const cargandoLotes = ref(false)


/*
|--------------------------------------------------------------------------
| DETALLE
|--------------------------------------------------------------------------
*/

const cantidadDetalle = ref(1)
const detalles = ref([])

// null = estamos agregando
// número = estamos editando ese detalle
const indiceEditando = ref(null)


/*
|--------------------------------------------------------------------------
| ESTADO GENERAL
|--------------------------------------------------------------------------
*/

const procesando = ref(false)
const mensajeExito = ref('')
const error = ref('')


/*
|--------------------------------------------------------------------------
| COMPUTED
|--------------------------------------------------------------------------
*/

const loteSeleccionado = computed(() => {
  if (!loteSeleccionadoId.value) {
    return null
  }

  return lotesDisponibles.value.find(
    lote => String(lote.id) === String(loteSeleccionadoId.value)
  ) || null
})


const puedeAgregarDetalle = computed(() => {
  return (
    medicamentoSeleccionado.value &&
    loteSeleccionado.value &&
    Number.isInteger(Number(cantidadDetalle.value)) &&
    Number(cantidadDetalle.value) >= 1 &&
    Number(cantidadDetalle.value) <= Number(loteSeleccionado.value.cantidad_actual)
  )
})


const puedeGuardar = computed(() => {
  return (
    form.value.fecha_salida &&
    form.value.almacen_origen.trim() &&
    form.value.establecimiento_id &&
    form.value.solicitado_por.trim() &&
    detalles.value.length > 0
  )
})


const totalUnidades = computed(() => {
  return detalles.value.reduce(
    (total, detalle) => total + Number(detalle.cantidad),
    0
  )
})


/*
|--------------------------------------------------------------------------
| FECHA LOCAL
|--------------------------------------------------------------------------
*/

function obtenerFechaLocal() {
  const fecha = new Date()

  const año = fecha.getFullYear()
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const dia = String(fecha.getDate()).padStart(2, '0')

  return `${año}-${mes}-${dia}`
}


/*
|--------------------------------------------------------------------------
| FORMATO DE FECHA
|--------------------------------------------------------------------------
*/

function formatearFecha(fecha) {
  if (!fecha) {
    return '-'
  }

  // La API devuelve normalmente YYYY-MM-DD
  const partes = String(fecha).slice(0, 10).split('-')

  if (partes.length !== 3) {
    return fecha
  }

  return `${partes[2]}/${partes[1]}/${partes[0]}`
}


/*
|--------------------------------------------------------------------------
| SIGUIENTE NÚMERO DE SALIDA
|--------------------------------------------------------------------------
*/

const cargarSiguienteNumeroSalida = async () => {
  cargandoNumeroSalida.value = true

  try {
    const respuesta = await axios.get('api/salidas/siguiente-numero')
    numeroSalida.value = respuesta.data?.numero_salida ?? null
  } catch (e) {
    console.error(e)
    numeroSalida.value = null
  } finally {
    cargandoNumeroSalida.value = false
  }
}


/*
|--------------------------------------------------------------------------
| CARGAR ESTABLECIMIENTOS
|--------------------------------------------------------------------------
*/

const cargarEstablecimientos = async () => {
  cargandoEstablecimientos.value = true

  try {
    const respuesta = await axios.get('api/establecimientos')

    establecimientos.value = respuesta.data
  } catch (e) {
    console.error(e)

    error.value =
      e.response?.data?.message ||
      'No se pudieron cargar los establecimientos.'
  } finally {
    cargandoEstablecimientos.value = false
  }
}


/*
|--------------------------------------------------------------------------
| BUSCAR MEDICAMENTOS
|--------------------------------------------------------------------------
*/

const buscarMedicamentos = () => {
  mostrarResultados.value = true

  clearTimeout(temporizadorBusqueda)

  const termino = textoBusqueda.value.trim()

  // No hacemos consultas para búsquedas demasiado cortas.
  if (termino.length < 2) {
    resultadosMedicamentos.value = []
    return
  }

  temporizadorBusqueda = setTimeout(async () => {

    buscandoMedicamentos.value = true
    error.value = ''

    try {
      const respuesta = await axios.get('api/medicamentos', {
        params: {
          buscar: termino
        }
      })

      resultadosMedicamentos.value = respuesta.data

    } catch (e) {
      console.error(e)

      resultadosMedicamentos.value = []

      error.value =
        e.response?.data?.message ||
        'No se pudieron buscar los medicamentos.'
    } finally {
      buscandoMedicamentos.value = false
    }

  }, 250)
}


/*
|--------------------------------------------------------------------------
| SELECCIONAR MEDICAMENTO
|--------------------------------------------------------------------------
*/

const seleccionarMedicamento = async (medicamento) => {

  medicamentoSeleccionado.value = medicamento

  textoBusqueda.value = ''
  resultadosMedicamentos.value = []
  mostrarResultados.value = false

  loteSeleccionadoId.value = ''
  lotesDisponibles.value = []

  cantidadDetalle.value = 1

  await cargarLotes(medicamento.id)
}


/*
|--------------------------------------------------------------------------
| CARGAR LOTES DEL MEDICAMENTO
|--------------------------------------------------------------------------
*/

const cargarLotes = async (medicamentoId) => {

  if (!medicamentoId) {
    return
  }

  cargandoLotes.value = true
  error.value = ''

  try {

    const respuesta = await axios.get(
      `api/medicamentos/${medicamentoId}/lotes`
    )

    lotesDisponibles.value = respuesta.data

    /*
     * La API ya entrega los lotes ordenados por vencimiento.
     * Por eso el primero es la sugerencia FEFO.
     */
    if (lotesDisponibles.value.length > 0) {
      loteSeleccionadoId.value = lotesDisponibles.value[0].id
    } else {
      error.value =
        'El medicamento seleccionado no tiene lotes con stock disponible.'
    }

  } catch (e) {
    console.error(e)

    lotesDisponibles.value = []
    loteSeleccionadoId.value = ''

    error.value =
      e.response?.data?.message ||
      'No se pudieron cargar los lotes del medicamento.'
  } finally {
    cargandoLotes.value = false
  }
}


/*
|--------------------------------------------------------------------------
| LIMPIAR SELECCIÓN DE MEDICAMENTO
|--------------------------------------------------------------------------
*/

const limpiarSeleccionMedicamento = () => {

  medicamentoSeleccionado.value = null

  textoBusqueda.value = ''
  resultadosMedicamentos.value = []

  lotesDisponibles.value = []
  loteSeleccionadoId.value = ''

  cantidadDetalle.value = 1

  indiceEditando.value = null
}


/*
|--------------------------------------------------------------------------
| AGREGAR / ACTUALIZAR DETALLE
|--------------------------------------------------------------------------
*/

const agregarDetalle = () => {

  if (!puedeAgregarDetalle.value) {
    error.value = 'Complete correctamente medicamento, lote y cantidad.'
    return
  }

  const medicamento = medicamentoSeleccionado.value
  const lote = loteSeleccionado.value
  const cantidad = Number(cantidadDetalle.value)

  /*
   * Si estamos editando un detalle existente...
   */
  if (indiceEditando.value !== null) {

    const indiceActual = indiceEditando.value

    /*
     * Comprobamos si el lote elegido ya existe en otro detalle.
     */
    const indiceDuplicado = detalles.value.findIndex(
      (detalle, index) =>
        index !== indiceActual &&
        String(detalle.lote_id) === String(lote.id)
    )

    if (indiceDuplicado !== -1) {

      const deseaSumar = window.confirm(
        'Este lote ya está incluido en la salida. ¿Desea sumar la cantidad al detalle existente?'
      )

      if (!deseaSumar) {
        return
      }

      const cantidadTotal =
        Number(detalles.value[indiceDuplicado].cantidad) + cantidad

      if (cantidadTotal > Number(lote.cantidad_actual)) {
        error.value =
          `La cantidad total supera el stock disponible del lote. ` +
          `Disponible: ${lote.cantidad_actual}.`

        return
      }

      detalles.value[indiceDuplicado].cantidad = cantidadTotal

      detalles.value.splice(indiceActual, 1)

      cancelarEdicion()

      return
    }

    /*
     * No hay duplicado: actualizamos directamente.
     */
    detalles.value[indiceActual] = crearDetalle(
      medicamento,
      lote,
      cantidad
    )

    cancelarEdicion()

    return
  }


  /*
   * Si estamos agregando un detalle nuevo,
   * primero verificamos si el lote ya está incluido.
   */
  const indiceDuplicado = detalles.value.findIndex(
    detalle =>
      String(detalle.lote_id) === String(lote.id)
  )

  if (indiceDuplicado !== -1) {

    const deseaSumar = window.confirm(
      'Este lote ya está incluido en la salida. ¿Desea sumar la nueva cantidad?'
    )

    if (!deseaSumar) {
      return
    }

    const cantidadTotal =
      Number(detalles.value[indiceDuplicado].cantidad) + cantidad

    if (cantidadTotal > Number(lote.cantidad_actual)) {
      error.value =
        `La cantidad total supera el stock disponible del lote. ` +
        `Disponible: ${lote.cantidad_actual}.`

      return
    }

    detalles.value[indiceDuplicado].cantidad = cantidadTotal

    limpiarLineaCaptura()

    return
  }


  /*
   * Agregar nuevo detalle.
   */
  detalles.value.push(
    crearDetalle(
      medicamento,
      lote,
      cantidad
    )
  )

  limpiarLineaCaptura()
}


/*
|--------------------------------------------------------------------------
| CREAR OBJETO DE DETALLE
|--------------------------------------------------------------------------
*/

function crearDetalle(medicamento, lote, cantidad) {

  return {
    lote_id: lote.id,
    cantidad: Number(cantidad),

    medicamento_id: medicamento.id,
    codigo: medicamento.codigo,
    nombre: medicamento.nombre,
    concentracion: medicamento.concentracion || '',
    forma_farmaceutica: medicamento.forma_farmaceutica || '',
    unidad_presentacion: medicamento.unidad_presentacion || '',

    codigo_lote: lote.codigo_lote,
    fecha_vencimiento: lote.fecha_vencimiento,
    stock_disponible: Number(lote.cantidad_actual)
  }
}


/*
|--------------------------------------------------------------------------
| LIMPIAR LÍNEA DE CAPTURA
|--------------------------------------------------------------------------
*/

const limpiarLineaCaptura = async () => {

  medicamentoSeleccionado.value = null

  textoBusqueda.value = ''

  resultadosMedicamentos.value = []

  lotesDisponibles.value = []
  loteSeleccionadoId.value = ''

  cantidadDetalle.value = 1

  indiceEditando.value = null

  await nextTick()

  buscadorMedicamento.value?.focus()
}


/*
|--------------------------------------------------------------------------
| EDITAR DETALLE
|--------------------------------------------------------------------------
*/

const editarDetalle = async (index) => {

  const detalle = detalles.value[index]

  if (!detalle) {
    return
  }

  indiceEditando.value = index

  medicamentoSeleccionado.value = {
    id: detalle.medicamento_id,
    codigo: detalle.codigo,
    nombre: detalle.nombre,
    concentracion: detalle.concentracion,
    forma_farmaceutica: detalle.forma_farmaceutica,
    unidad_presentacion: detalle.unidad_presentacion
  }

  textoBusqueda.value = ''

  await cargarLotes(detalle.medicamento_id)

  /*
   * Después de cargar los lotes seleccionamos el lote
   * específico que tenía el detalle.
   */
  const loteExiste = lotesDisponibles.value.some(
    lote => String(lote.id) === String(detalle.lote_id)
  )

  if (loteExiste) {
    loteSeleccionadoId.value = detalle.lote_id
  }

  cantidadDetalle.value = detalle.cantidad

  await nextTick()

  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  })
}


/*
|--------------------------------------------------------------------------
| CANCELAR EDICIÓN
|--------------------------------------------------------------------------
*/

const cancelarEdicion = () => {

  indiceEditando.value = null

  limpiarLineaCaptura()
}


/*
|--------------------------------------------------------------------------
| ELIMINAR DETALLE
|--------------------------------------------------------------------------
*/

const eliminarDetalle = (index) => {

  const detalle = detalles.value[index]

  if (!detalle) {
    return
  }

  const confirmar = window.confirm(
    `¿Desea eliminar "${detalle.nombre}" del detalle de la salida?`
  )

  if (!confirmar) {
    return
  }

  detalles.value.splice(index, 1)

  if (indiceEditando.value === index) {
    cancelarEdicion()
  }
}


/*
|--------------------------------------------------------------------------
| PROCESAR SALIDA
|--------------------------------------------------------------------------
*/

const procesarSalida = async () => {

  error.value = ''
  mensajeExito.value = ''

  if (!puedeGuardar.value) {

    error.value =
      'Complete la fecha, almacén de origen, destino, responsable y agregue al menos un producto.'

    return
  }

  procesando.value = true

  try {

    /*
     * Enviamos únicamente la estructura que espera
     * SalidaController → SalidaService.
     */
    const payload = {
      fecha_salida: form.value.fecha_salida,
      almacen_origen: form.value.almacen_origen.trim(),
      establecimiento_id: Number(form.value.establecimiento_id),
      numero_pedido: form.value.numero_pedido.trim() || null,
      solicitado_por: form.value.solicitado_por.trim(),
      entregado_a: form.value.entregado_a.trim() || null,
      observaciones: form.value.observaciones.trim() || null,

      detalle: detalles.value.map(detalle => ({
        lote_id: Number(detalle.lote_id),
        cantidad: Number(detalle.cantidad)
      }))
    }

    const respuesta = await axios.post(
      'api/salidas',
      payload
    )

    mensajeExito.value =
      respuesta.data?.message ||
      'Salida registrada correctamente.'

    /*
     * Limpiamos todo después de una respuesta exitosa.
     */
    form.value = {
      fecha_salida: obtenerFechaLocal(),
      almacen_origen: 'REGIONAL LA PAZ',
      establecimiento_id: '',
      numero_pedido: '',
      solicitado_por: '',
      entregado_a: '',
      observaciones: ''
    }

    numeroSalida.value = Number(respuesta.data?.salida?.numero_salida || 0) + 1

    detalles.value = []

    await limpiarLineaCaptura()

  } catch (e) {

    console.error(e)

    /*
     * Laravel puede devolver errores de validación
     * en response.data.errors.
     */
    if (e.response?.status === 422) {

      const errores = e.response.data.errors

      if (errores) {

        const mensajes = Object.values(errores)
          .flat()
          .join(' ')

        error.value =
          mensajes ||
          e.response.data.message ||
          'Revise los datos de la salida.'

      } else {

        error.value =
          e.response.data.message ||
          'Revise los datos de la salida.'
      }

    } else {

      error.value =
        e.response?.data?.message ||
        'No se pudo registrar la salida.'
    }

  } finally {

    procesando.value = false
  }
}


/*
|--------------------------------------------------------------------------
| INICIO
|--------------------------------------------------------------------------
*/

onMounted(async () => {

  await Promise.all([
    cargarEstablecimientos(),
    cargarSiguienteNumeroSalida()
  ])

  await nextTick()

  buscadorMedicamento.value?.focus()
})
</script>
<style scoped>
.salida-card{background:#fff;border:1px solid #e1e7ed;border-radius:14px;overflow:hidden;box-shadow:0 5px 20px rgba(20,48,70,.07)}
.salida-alert{margin:18px 22px 0}.salida-hero{background:#0b3d62;color:#fff;border-bottom:4px solid #e85d04;min-height:88px;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:20px}.salida-hero h2{font-size:1.45rem;margin:0 0 4px;font-weight:700}.salida-hero p{margin:0;color:rgba(255,255,255,.85);font-size:.88rem}
.salida-note{min-width:170px;border:1px solid rgba(255,255,255,.65);text-align:center;background:#fff;color:#0b3d62;border-radius:8px;overflow:hidden}.salida-note span{display:block;background:#0b3d62;color:#fff;font-size:.68rem;font-weight:800;padding:5px}.salida-note strong{display:block;color:#e85d04;font-size:1.15rem;padding:6px}
.salida-body{padding:22px}.salida-section{border:1px solid #dbe4eb;border-radius:11px;background:#fff;margin-bottom:20px;overflow:visible}.section-heading{min-height:66px;padding:12px 18px;border-left:4px solid #e85d04;background:#f7fafc;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #e1e7ed}.section-kicker{display:block;color:#e85d04;font-size:.68rem;font-weight:800;letter-spacing:.04em}.section-heading h3{margin:2px 0 0;color:#0b3d62;font-size:1.05rem}.section-content{padding:18px}.salida-section label{display:block;color:#173c5a;font-size:.78rem;font-weight:800;margin-bottom:5px}.field-help{display:block;color:#71808f;font-size:.7rem;margin-top:4px}.count-badge{background:#0b3d62;color:#fff;border-radius:999px;padding:6px 10px;font-size:.7rem;font-weight:800}
.receive-field{display:flex;flex-direction:column;gap:7px}.same-person-check{display:flex!important;align-items:center;gap:7px;color:#667788!important;font-size:.72rem!important;font-weight:600!important;margin:0!important}.same-person-check input{accent-color:#0b3d62}
.search-icon{background:#f1f5f8;color:#0b3d62;border-color:#ced9e2;font-weight:900}.product-results{position:absolute;z-index:1050;left:12px;right:12px;top:calc(100% + 4px);background:#fff;border:1px solid #d6e0e8;border-radius:9px;box-shadow:0 12px 25px rgba(20,48,70,.16);overflow:hidden;max-height:280px;overflow-y:auto}.product-results button{display:block;width:100%;border:0;border-bottom:1px solid #edf1f4;background:#fff;text-align:left;padding:9px 11px;color:#173c5a}.product-results button:hover{background:#eef5fa}.product-results strong,.selected-product strong{display:block}.product-results small,.selected-product small{display:block;color:#71808f;margin-top:2px}.result-empty{padding:12px;text-align:center;color:#71808f}.selected-product{margin-top:8px;padding:9px 11px;border:1px solid #dbe4eb;border-left:4px solid #e85d04;border-radius:8px;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}.selected-product button{border:0;background:#fff;color:#b42318;font-size:1.1rem}.lot-info{margin-top:7px;color:#137a45;background:#edf8f1;border:1px solid #cce8d8;border-radius:7px;padding:6px 8px;font-size:.72rem}.add-actions{display:flex;align-items:center;gap:9px;margin-top:15px}.btn-outline-csc{border:1px solid #0b3d62;background:#fff;color:#0b3d62;border-radius:7px;padding:8px 13px}
.salida-table{width:100%;border-collapse:collapse}.salida-table th{background:#0b3d62;color:#fff;padding:10px 9px;font-size:.76rem;text-align:left}.salida-table td{padding:10px 9px;border-bottom:1px solid #e3e9ee;color:#26394b}.salida-table td small{display:block;color:#71808f;margin-top:2px;font-size:.72rem}.salida-table tfoot td{background:#f5f8fb;border-bottom:0}.action-blue,.action-orange{border-radius:6px;padding:5px 9px;font-size:.72rem;font-weight:700;margin:0 3px}.action-blue{border:1px solid #0b3d62;background:#fff;color:#0b3d62}.action-orange{border:0;background:#e85d04;color:#fff}.empty-details{padding:28px;text-align:center;color:#71808f;background:#f8fafc}.empty-details strong,.empty-details span{display:block}.empty-details strong{color:#0b3d62;margin-bottom:3px}.salida-footer-actions{border-top:2px solid #e85d04;padding-top:15px;display:flex;justify-content:flex-end;align-items:center;gap:15px}.salida-footer-actions>span{color:#71808f;font-size:.78rem}
@media(max-width:800px){.salida-hero{align-items:flex-start;flex-direction:column}.salida-note{width:100%}.salida-body{padding:14px}.add-actions,.salida-footer-actions{align-items:stretch;flex-direction:column}.salida-footer-actions .btn{width:100%}}
</style>
