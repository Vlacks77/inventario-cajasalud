<template>
    <section>
        <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center mb-3">
            <div>
                <h2 class="h4 mb-1">Kardex de inventario</h2>
                <p class="text-secondary mb-0">Stock disponible consolidado por medicamento y lote.</p>
            </div>
            <button class="btn btn-outline-primary" type="button" :disabled="cargando" @click="cargarInventario">
                {{ cargando ? 'Actualizando...' : 'Actualizar' }}
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body border-bottom">
                <label class="visually-hidden" for="buscar-inventario">Buscar medicamento</label>
                <input id="buscar-inventario" v-model.trim="busqueda" class="form-control" type="search" placeholder="Buscar por código, nombre, concentración o lote...">
            </div>

            <div v-if="cargando" class="card-body text-center py-5 text-secondary">
                <div class="spinner-border spinner-border-sm me-2" aria-hidden="true"></div>
                Cargando inventario...
            </div>
            <div v-else-if="error" class="card-body">
                <div class="alert alert-danger mb-0">{{ error }}</div>
            </div>
            <div v-else-if="inventarioFiltrado.length === 0" class="card-body text-center py-5 text-secondary">
                No se encontraron medicamentos.
            </div>

            <div v-else class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Medicamento</th>
                            <th scope="col" class="text-end">Stock actual</th>
                            <th scope="col">Lotes y vencimientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="medicamento in inventarioFiltrado" :key="medicamento.id">
                            <td class="fw-semibold">{{ medicamento.codigo }}</td>
                            <td>
                                <div>{{ medicamento.nombre }}</div>
                                <small class="text-secondary">{{ medicamento.concentracion }} · {{ medicamento.forma_farmaceutica }}</small>
                            </td>
                            <td class="text-end">
                                <span class="badge text-bg-primary fs-6">{{ stockActual(medicamento) }}</span>
                                <small class="d-block text-secondary mt-1">Mínimo: {{ medicamento.stock_minimo }}</small>
                            </td>
                            <td>
                                <ul v-if="medicamento.lotes.length" class="list-unstyled mb-0 small">
                                    <li v-for="lote in medicamento.lotes" :key="lote.id" class="mb-1">
                                        <span class="fw-semibold">Lote {{ lote.codigo_lote }}</span>
                                        · vence {{ formatearFecha(lote.fecha_vencimiento) }}
                                        · {{ lote.cantidad_actual }} disponible
                                        <span v-if="lote.proveedor" class="text-secondary">· {{ lote.proveedor.nombre }}</span>
                                    </li>
                                </ul>
                                <span v-else class="text-secondary small">Sin lotes registrados</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const medicamentos = ref([])
const busqueda = ref('')
const cargando = ref(true)
const error = ref('')

const inventarioFiltrado = computed(() => {
    const termino = busqueda.value.toLocaleLowerCase()

    if (!termino) return medicamentos.value

    return medicamentos.value.filter((medicamento) => {
        const datos = [
            medicamento.codigo,
            medicamento.nombre,
            medicamento.concentracion,
            medicamento.forma_farmaceutica,
        ].join(' ').toLocaleLowerCase()

        return datos.includes(termino) || medicamento.lotes.some((lote) =>
            lote.codigo_lote.toLocaleLowerCase().includes(termino)
        )
    })
})

const stockActual = (medicamento) => Number(medicamento.stock_actual ?? 0)

const formatearFecha = (fecha) => new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'medium',
}).format(new Date(`${fecha}T00:00:00`))

const cargarInventario = async () => {
    cargando.value = true
    error.value = ''

    try {
        const { data } = await axios.get('api/inventario')
        medicamentos.value = data.data
    } catch (err) {
        error.value = err.response?.data?.message || 'No fue posible cargar el inventario.'
    } finally {
        cargando.value = false
    }
}

onMounted(cargarInventario)
</script>
