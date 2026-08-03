<template>
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div v-show="mensajeExito" class="alert alert-warning alert-dismissible fade show text-dark fw-bold" role="alert">
                <i class="bi bi-check-circle me-2"></i> {{ mensajeExito }}
                <button type="button" class="btn-close" @click="mensajeExito = ''"></button>
            </div>
            <div v-show="error" class="alert alert-danger" role="alert">
                {{ error }}
            </div>

            <form @submit.prevent="procesarSalida" novalidate>
                
                <section class="mb-4">
                    <h2 class="h5 border-bottom pb-2 text-primary">1. Selección de Insumo</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Medicamento *</label>
                            <select v-model="medicamentoSeleccionado" class="form-select bg-light" @change="cargarLotes" required>
                                <option value="" disabled>Seleccione de la lista...</option>
                                <option v-for="med in inventario" :key="med.id" :value="med">
                                    {{ med.codigo }} - {{ med.nombre }} (Stock Disp: {{ med.stock_total }})
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lote a descontar *</label>
                            <select v-model="form.lote_id" class="form-select border-warning" required :disabled="!medicamentoSeleccionado">
                                <option value="" disabled>Seleccione un lote...</option>
                                <option v-for="lote in lotesDisponibles" :key="lote.id" :value="lote.id">
                                    Lote: {{ lote.codigo_lote }} - Disp: {{ lote.cantidad_actual }} unids (Vence: {{ lote.fecha_vencimiento }})
                                </option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="mb-4">
                    <h2 class="h5 border-bottom pb-2 text-primary">2. Datos del Traspaso / Salida</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cantidad a extraer *</label>
                            <input v-model.number="form.cantidad" type="number" min="1" class="form-control text-danger fw-bold" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Destino / Área *</label>
                            <input v-model.trim="form.destino" type="text" class="form-control" placeholder="Ej: Farmacia 1, Emergencias" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de salida *</label>
                            <input v-model="form.fecha_salida" type="date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Entregado a (Nombre del Doctor/Enfermera)</label>
                            <input v-model.trim="form.entregado_a" type="text" class="form-control" placeholder="Ej: Dr. Pérez">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Observaciones</label>
                            <input v-model.trim="form.observaciones" type="text" class="form-control" placeholder="Motivo o detalle adicional">
                        </div>
                    </div>
                </section>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-warning px-5 shadow text-dark fw-bold" :disabled="procesando">
                        <span v-if="procesando" class="spinner-border spinner-border-sm me-2"></span>
                        {{ procesando ? 'Descontando...' : 'Registrar Salida' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const inventario = ref([])
const medicamentoSeleccionado = ref('')
const lotesDisponibles = ref([])

const form = ref({
    lote_id: '',
    cantidad: 1,
    destino: '',
    entregado_a: '',
    fecha_salida: new Date().toISOString().slice(0, 10),
    observaciones: ''
})

const procesando = ref(false)
const mensajeExito = ref('')
const error = ref('')

// Pedimos al backend la lista de inventario
const cargarInventario = async () => {
    try {
        const respuesta = await axios.get('api/inventario')
        // Solo mostramos los que tienen stock mayor a 0
        inventario.value = respuesta.data.filter(med => med.stock_total > 0)
    } catch (e) {
        error.value = "Error conectando con la base de datos."
    }
}

// Cuando la doctora elige un medicamento, esta función filtra y muestra sus lotes
const cargarLotes = () => {
    form.value.lote_id = '' 
    if (medicamentoSeleccionado.value) {
        lotesDisponibles.value = medicamentoSeleccionado.value.lotes.filter(l => l.cantidad_actual > 0)
    }
}

const procesarSalida = async () => {
    procesando.value = true
    error.value = ''
    mensajeExito.value = ''

    try {
        const { data } = await axios.post('api/salidas', form.value)
        mensajeExito.value = data.message
        
        // Limpiamos el formulario para la siguiente salida
        form.value.cantidad = 1
        form.value.destino = ''
        form.value.entregado_a = ''
        form.value.observaciones = ''
        medicamentoSeleccionado.value = ''
        lotesDisponibles.value = []
        
        // Refrescamos los números del inventario
        cargarInventario()
    } catch (e) {
        error.value = e.response?.data?.message || 'Revisa los campos requeridos.'
    } finally {
        procesando.value = false
    }
}

onMounted(() => {
    cargarInventario()
})
</script>