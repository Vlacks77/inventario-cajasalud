<template>
    <main class="container py-4 py-md-5">
        <nav class="nav nav-pills gap-2 mb-4" aria-label="Navegación principal">
            <button class="nav-link" :class="{ active: vistaActual === 'ingreso' }" type="button" @click="vistaActual = 'ingreso'">
                Registrar ingreso
            </button>
            <button class="nav-link" :class="{ active: vistaActual === 'inventario' }" type="button" @click="vistaActual = 'inventario'">
                Ver inventario
            </button>
        </nav>

        <header class="mb-4">
            <h1 class="h2 mb-1">{{ vistaActual === 'ingreso' ? 'Registro de ingreso de medicamentos' : 'Inventario de medicamentos' }}</h1>
            <p class="text-secondary mb-0">Caja de Salud de Caminos · Almacén central</p>
        </header>

        <div v-show="vistaActual === 'ingreso' && mensajeExito" class="alert alert-success alert-dismissible fade show" role="alert">
            {{ mensajeExito }}
            <button type="button" class="btn-close" aria-label="Cerrar" @click="mensajeExito = ''"></button>
        </div>
        <div v-show="vistaActual === 'ingreso' && obtenerError('general')" class="alert alert-danger" role="alert">
            {{ obtenerError('general') }}
        </div>

        <form v-show="vistaActual === 'ingreso'" class="card shadow-sm border-0" novalidate @submit.prevent="registrarIngreso">
            <div class="card-body p-4">
                <section class="mb-4">
                    <h2 class="h5 border-bottom pb-2">Medicamento</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="codigo" class="form-label">Código institucional *</label>
                            <input id="codigo" v-model.trim="form.medicamento.codigo" class="form-control" :class="claseError('medicamento.codigo')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.codigo') }}</div>
                        </div>
                        <div class="col-md-8">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input id="nombre" v-model.trim="form.medicamento.nombre" class="form-control" :class="claseError('medicamento.nombre')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.nombre') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="concentracion" class="form-label">Concentración *</label>
                            <input id="concentracion" v-model.trim="form.medicamento.concentracion" placeholder="Ej.: 500 mg" class="form-control" :class="claseError('medicamento.concentracion')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.concentracion') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="forma" class="form-label">Forma farmacéutica *</label>
                            <input id="forma" v-model.trim="form.medicamento.forma_farmaceutica" placeholder="Ej.: Comprimido" class="form-control" :class="claseError('medicamento.forma_farmaceutica')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.forma_farmaceutica') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="presentacion" class="form-label">Unidad de presentación *</label>
                            <input id="presentacion" v-model.trim="form.medicamento.unidad_presentacion" placeholder="Ej.: Caja x 100" class="form-control" :class="claseError('medicamento.unidad_presentacion')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.unidad_presentacion') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="stock-minimo" class="form-label">Stock mínimo *</label>
                            <input id="stock-minimo" v-model.number="form.medicamento.stock_minimo" type="number" min="0" class="form-control" :class="claseError('medicamento.stock_minimo')" required>
                            <div class="invalid-feedback">{{ obtenerError('medicamento.stock_minimo') }}</div>
                        </div>
                        <div class="col-md-8">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <input id="descripcion" v-model.trim="form.medicamento.descripcion" class="form-control" placeholder="Observaciones opcionales">
                        </div>
                    </div>
                </section>

                <section class="mb-4">
                    <h2 class="h5 border-bottom pb-2">Proveedor</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="proveedor" class="form-label">Nombre o razón social *</label>
                            <input id="proveedor" v-model.trim="form.proveedor.nombre" class="form-control" :class="claseError('proveedor.nombre')" required>
                            <div class="invalid-feedback">{{ obtenerError('proveedor.nombre') }}</div>
                        </div>
                        <div class="col-md-3">
                            <label for="nit" class="form-label">NIT</label>
                            <input id="nit" v-model.trim="form.proveedor.nit" class="form-control" :class="claseError('proveedor.nit')">
                            <div class="invalid-feedback">{{ obtenerError('proveedor.nit') }}</div>
                        </div>
                        <div class="col-md-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input id="telefono" v-model.trim="form.proveedor.telefono" class="form-control" :class="claseError('proveedor.telefono')">
                            <div class="invalid-feedback">{{ obtenerError('proveedor.telefono') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="contacto" class="form-label">Contacto</label>
                            <input id="contacto" v-model.trim="form.proveedor.contacto" class="form-control" :class="claseError('proveedor.contacto')">
                            <div class="invalid-feedback">{{ obtenerError('proveedor.contacto') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input id="direccion" v-model.trim="form.proveedor.direccion" class="form-control" :class="claseError('proveedor.direccion')">
                            <div class="invalid-feedback">{{ obtenerError('proveedor.direccion') }}</div>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="h5 border-bottom pb-2">Lote e ingreso</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="codigo-lote" class="form-label">Código de lote *</label>
                            <input id="codigo-lote" v-model.trim="form.lote.codigo_lote" class="form-control" :class="claseError('lote.codigo_lote')" required>
                            <div class="invalid-feedback">{{ obtenerError('lote.codigo_lote') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="vencimiento" class="form-label">Fecha de vencimiento *</label>
                            <input id="vencimiento" v-model="form.lote.fecha_vencimiento" type="date" :min="fechaMinima" class="form-control" :class="claseError('lote.fecha_vencimiento')" required>
                            <div class="invalid-feedback">{{ obtenerError('lote.fecha_vencimiento') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label for="cantidad" class="form-label">Cantidad ingresada *</label>
                            <input id="cantidad" v-model.number="form.lote.cantidad" type="number" min="1" class="form-control" :class="claseError('lote.cantidad')" required>
                            <div class="invalid-feedback">{{ obtenerError('lote.cantidad') }}</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="card-footer bg-white border-0 p-4 pt-0 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary px-4" :disabled="procesando">
                    <span v-if="procesando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ procesando ? 'Guardando...' : 'Registrar ingreso' }}
                </button>
            </div>
        </form>

        <Inventario v-if="vistaActual === 'inventario'" />
    </main>
</template>

<script setup>
import axios from 'axios'
import { computed, ref } from 'vue'
import Inventario from './components/Inventario.vue'

const crearFormulario = () => ({
    medicamento: {
        codigo: '', nombre: '', concentracion: '', forma_farmaceutica: '',
        unidad_presentacion: '', stock_minimo: 0, descripcion: '',
    },
    proveedor: {
        nombre: '', nit: '', contacto: '', telefono: '', direccion: '',
    },
    lote: {
        codigo_lote: '', fecha_vencimiento: '', cantidad: 1,
    },
})

const form = ref(crearFormulario())
const vistaActual = ref('ingreso')
const errores = ref({})
const mensajeExito = ref('')
const procesando = ref(false)

const fechaMinima = computed(() => {
    const fecha = new Date()
    fecha.setDate(fecha.getDate() + 1)
    return fecha.toISOString().slice(0, 10)
})

const obtenerError = (campo) => errores.value[campo]?.[0] ?? ''
const claseError = (campo) => ({ 'is-invalid': Boolean(obtenerError(campo)) })

const registrarIngreso = async () => {
    procesando.value = true
    errores.value = {}
    mensajeExito.value = ''

    try {
        const { data } = await axios.post('api/ingresos', form.value)

        mensajeExito.value = data.message
        form.value = crearFormulario()
    } catch (error) {
        if (error.response?.status === 422) {
            errores.value = error.response.data.errors
            return
        }

        errores.value = { general: [error.message || 'Ocurrió un error inesperado.'] }
    } finally {
        procesando.value = false
    }
}
</script>
