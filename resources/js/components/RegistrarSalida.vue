<template>
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">

      <!-- MENSAJES -->
      <div
        v-if="mensajeExito"
        class="alert alert-success alert-dismissible fade show fw-bold"
        role="alert"
      >
        <i class="bi bi-check-circle me-2"></i>
        {{ mensajeExito }}

        <button
          type="button"
          class="btn-close"
          @click="mensajeExito = ''"
        ></button>
      </div>

      <div
        v-if="error"
        class="alert alert-danger alert-dismissible fade show"
        role="alert"
      >
        <i class="bi bi-exclamation-triangle me-2"></i>
        {{ error }}

        <button
          type="button"
          class="btn-close"
          @click="error = ''"
        ></button>
      </div>

      <form @submit.prevent="procesarSalida" novalidate>

        <!-- ===================================================== -->
        <!-- CABECERA DE LA SALIDA -->
        <!-- ===================================================== -->

        <section class="mb-4">
          <div class="csc-section-title csc-section-blue mb-3">
            <i class="bi bi-file-earmark-medical me-2"></i>
            1. Encabezado de la salida
          </div>

          <div class="row g-3">

            <div class="col-md-3">
              <label class="form-label fw-bold">Fecha de salida</label>
              <input v-model="form.fecha_salida" type="date" class="form-control" required>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">N.º de salida</label>
              <input :value="numeroSalida ?? 'Cargando...'" type="text" class="form-control fw-bold text-center" readonly>
              <small class="text-muted">Correlativo automático</small>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Almacén de origen</label>
              <input v-model.trim="form.almacen_origen" type="text" class="form-control" maxlength="150">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Destino</label>
              <select v-model="form.establecimiento_id" class="form-select" required :disabled="cargandoEstablecimientos">
                <option value="" disabled>
                  {{ cargandoEstablecimientos ? 'Cargando establecimientos...' : 'Seleccione un establecimiento...' }}
                </option>
                <option v-for="establecimiento in establecimientos" :key="establecimiento.id" :value="establecimiento.id">
                  {{ establecimiento.nombre }}<span v-if="establecimiento.sigla"> ({{ establecimiento.sigla }})</span>
                </option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">N.º de pedido / documento</label>
              <input v-model.trim="form.numero_pedido" type="text" class="form-control" maxlength="100" placeholder="Número del documento físico de pedido">
              <small class="text-muted">Es el número consignado en el documento físico; no es necesariamente correlativo.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Responsable que solicita</label>
              <input v-model.trim="form.solicitado_por" type="text" class="form-control" placeholder="Nombre del solicitante" required>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Responsable que recibe</label>
              <input v-model.trim="form.entregado_a" type="text" class="form-control" placeholder="Nombre del responsable que recibe">
            </div>

            <div class="col-12">
              <label class="form-label fw-bold">Observaciones</label>
              <textarea v-model.trim="form.observaciones" class="form-control" rows="2" placeholder="Observaciones adicionales"></textarea>
            </div>

          </div>
        </section>

        <!-- ===================================================== -->
        <!-- AGREGAR MEDICAMENTO -->
        <!-- ===================================================== -->

        <section class="mb-4">
          <div class="csc-section-title csc-section-blue mb-3">
            <i class="bi bi-capsule me-2"></i>
            2. Agregar productos
          </div>

          <div class="row g-3">

            <!-- BUSCADOR -->
            <div class="col-md-6 position-relative">

              <label class="form-label fw-bold">
                Buscar producto *
              </label>

              <div class="input-group">
                <span class="input-group-text bg-light">
                  <i class="bi bi-search"></i>
                </span>

                <input
                  ref="buscadorMedicamento"
                  v-model="textoBusqueda"
                  type="text"
                  class="form-control"
                  placeholder="Código LINAME o nombre del producto..."
                  autocomplete="off"
                  @input="buscarMedicamentos"
                >
              </div>

              <!-- RESULTADOS DEL BUSCADOR -->
              <div
                v-if="mostrarResultados"
                class="position-absolute bg-white border rounded shadow w-100 mt-1"
                style="z-index: 1050; max-height: 280px; overflow-y: auto;"
              >

                <button
                  v-for="medicamento in resultadosMedicamentos"
                  :key="medicamento.id"
                  type="button"
                  class="list-group-item list-group-item-action border-0 border-bottom text-start"
                  @click="seleccionarMedicamento(medicamento)"
                >
                  <div class="fw-bold">
                    {{ medicamento.nombre }}
                  </div>

                  <small class="text-muted">
                    {{ medicamento.codigo }}
                    <span v-if="medicamento.concentracion">
                      · {{ medicamento.concentracion }}
                    </span>
                    <span v-if="medicamento.forma_farmaceutica">
                      · {{ medicamento.forma_farmaceutica }}
                    </span>
                  </small>
                </button>

                <div
                  v-if="resultadosMedicamentos.length === 0 && !buscandoMedicamentos"
                  class="p-3 text-muted text-center"
                >
                  No se encontraron productos.
                </div>

                <div
                  v-if="buscandoMedicamentos"
                  class="p-3 text-muted text-center"
                >
                  Buscando productos...
                </div>

              </div>

              <!-- MEDICAMENTO SELECCIONADO -->
              <div
                v-if="medicamentoSeleccionado"
                class="mt-2 p-2 rounded bg-light border"
              >
                <div class="d-flex justify-content-between align-items-start">

                  <div>
                    <div class="fw-bold text-primary">
                      {{ medicamentoSeleccionado.nombre }}
                    </div>

                    <small class="text-muted">
                      Código: {{ medicamentoSeleccionado.codigo }}
                      <span v-if="medicamentoSeleccionado.concentracion">
                        · {{ medicamentoSeleccionado.concentracion }}
                      </span>
                    </small>
                  </div>

                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="limpiarSeleccionMedicamento"
                  >
                    <i class="bi bi-x"></i>
                  </button>

                </div>
              </div>

            </div>


            <!-- LOTE -->
            <div class="col-md-4">

              <label class="form-label fw-bold">
                Lote *
              </label>

              <select
                v-model="loteSeleccionadoId"
                class="form-select border-warning"
                :disabled="!medicamentoSeleccionado || cargandoLotes"
              >
                <option value="" disabled>
                  {{
                    !medicamentoSeleccionado
                      ? 'Seleccione primero un medicamento'
                      : cargandoLotes
                        ? 'Cargando lotes...'
                        : 'Seleccione un lote...'
                  }}
                </option>

                <option
                  v-for="lote in lotesDisponibles"
                  :key="lote.id"
                  :value="lote.id"
                >
                  {{ lote.codigo_lote }}
                  — Stock: {{ lote.cantidad_actual }}
                  — Vence: {{ formatearFecha(lote.fecha_vencimiento) }}
                </option>
              </select>

              <!-- INFORMACIÓN DEL LOTE -->
              <div
                v-if="loteSeleccionado"
                class="mt-2 small"
              >
                <span class="badge bg-success me-1">
                  Stock: {{ loteSeleccionado.cantidad_actual }}
                </span>

                <span class="badge bg-secondary">
                  Vence:
                  {{ formatearFecha(loteSeleccionado.fecha_vencimiento) }}
                </span>
              </div>

            </div>


            <!-- CANTIDAD -->
            <div class="col-md-2">

              <label class="form-label fw-bold">
                Cantidad *
              </label>

              <input
                v-model.number="cantidadDetalle"
                type="number"
                min="1"
                :max="loteSeleccionado?.cantidad_actual || undefined"
                class="form-control text-danger fw-bold"
                :disabled="!loteSeleccionado"
              >

            </div>

          </div>


          <!-- BOTÓN AGREGAR -->
          <div class="mt-3 d-flex align-items-center gap-2">

            <button
              type="button"
              class="btn btn-csc-orange px-4 fw-bold"
              :disabled="!puedeAgregarDetalle || procesando"
              @click="agregarDetalle"
            >
              <i class="bi bi-plus-circle me-2"></i>

              {{
                indiceEditando !== null
                  ? 'Actualizar producto'
                  : 'Agregar producto'
              }}
            </button>

            <button
              v-if="indiceEditando !== null"
              type="button"
              class="btn btn-outline-secondary"
              @click="cancelarEdicion"
            >
              Cancelar edición
            </button>

            <span
              v-if="loteSeleccionado && cantidadDetalle > loteSeleccionado.cantidad_actual"
              class="text-danger small fw-bold"
            >
              La cantidad supera el stock disponible.
            </span>

          </div>

        </section>


        <!-- ===================================================== -->
        <!-- DETALLE DE LA SALIDA -->
        <!-- ===================================================== -->

        <section class="mb-4">

          <div class="csc-section-title csc-section-blue mb-3 d-flex justify-content-between align-items-center csc-detail-heading">
            <span><i class="bi bi-list-check me-2"></i>3. Detalle de la Salida</span>
            <span class="csc-count-badge">
              {{ detalles.length }} {{ detalles.length === 1 ? 'medicamento' : 'medicamentos' }}
            </span>
          </div>


          <!-- TABLA -->
          <div
            v-if="detalles.length > 0"
            class="table-responsive"
          >

            <table class="table table-hover align-middle">

              <thead class="table-light">

                <tr>
                  <th>#</th>
                  <th>Medicamento</th>
                  <th>Lote</th>
                  <th>Vencimiento</th>
                  <th class="text-center">Cantidad</th>
                  <th class="text-center">Acciones</th>
                </tr>

              </thead>

              <tbody>

                <tr
                  v-for="(detalle, index) in detalles"
                  :key="detalle.lote_id"
                >

                  <td>
                    {{ index + 1 }}
                  </td>

                  <td>
                    <div class="fw-bold">
                      {{ detalle.nombre }}
                    </div>

                    <small class="text-muted">
                      {{ detalle.codigo }}
                      <span v-if="detalle.concentracion">
                        · {{ detalle.concentracion }}
                      </span>
                    </small>
                  </td>

                  <td>
                    <span class="badge bg-light text-dark border">
                      {{ detalle.codigo_lote }}
                    </span>
                  </td>

                  <td>
                    {{ formatearFecha(detalle.fecha_vencimiento) }}
                  </td>

                  <td class="text-center fw-bold">
                    {{ detalle.cantidad }}
                  </td>

                  <td class="text-center">

                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary me-1"
                      title="Editar"
                      @click="editarDetalle(index)"
                    >
                      <i class="bi bi-pencil"></i>
                    </button>

                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      title="Eliminar"
                      @click="eliminarDetalle(index)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>

                  </td>

                </tr>

              </tbody>

              <tfoot class="table-light">

                <tr>
                  <td colspan="4" class="text-end fw-bold">
                    Total de unidades:
                  </td>

                  <td class="text-center fw-bold text-primary">
                    {{ totalUnidades }}
                  </td>

                  <td></td>
                </tr>

              </tfoot>

            </table>

          </div>


          <!-- SIN DETALLES -->
          <div
            v-else
            class="border rounded p-4 text-center text-muted bg-light"
          >
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>

            Todavía no hay productos agregados a esta salida.

            <div class="small mt-1">
              Busque un producto arriba y agréguelo al detalle.
            </div>
          </div>

        </section>


        <!-- ===================================================== -->
        <!-- GUARDAR SALIDA -->
        <!-- ===================================================== -->

        <div class="d-flex justify-content-end align-items-center gap-3">

          <div
            v-if="detalles.length === 0"
            class="text-muted small"
          >
            Agregue al menos un medicamento.
          </div>

          <button
            type="submit"
            class="btn btn-csc-orange px-5 shadow fw-bold"
            :disabled="procesando || !puedeGuardar"
          >

            <span
              v-if="procesando"
              class="spinner-border spinner-border-sm me-2"
              aria-hidden="true"
            ></span>

            <i
              v-else
              class="bi bi-check-circle me-2"
            ></i>

            {{ procesando ? 'Guardando salida...' : 'Guardar Salida' }}

          </button>

        </div>

      </form>

    </div>
  </div>
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
.csc-section-title {
  border-radius: 10px;
  padding: 10px 16px;
  font-weight: 800;
  color: #fff;
  text-align: center;
}
.csc-section-blue {
  background: #0b3d62;
  min-height: 54px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  line-height: 1.2;
  box-shadow: 0 5px 14px rgba(11, 61, 98, .12);
}
.csc-count-badge {
  background: rgba(255,255,255,.14);
  border: 1px solid rgba(255,255,255,.45);
  border-radius: 999px;
  padding: 5px 10px;
  font-size: .72rem;
  font-weight: 800;
}

.csc-detail-heading {
  position: relative;
  justify-content: center !important;
}
.csc-detail-heading > span:first-child {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  white-space: nowrap;
}
.csc-detail-heading .csc-count-badge {
  position: absolute;
  right: 16px;
}
@media (max-width: 640px) {
  .csc-detail-heading { min-height: 64px; }
  .csc-detail-heading > span:first-child { white-space: normal; padding-right: 90px; }
}
</style>
