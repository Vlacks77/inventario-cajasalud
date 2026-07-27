<template>
  <div>
    <!-- PANTALLA 1: LOGIN -->
    <Login v-if="!sesionIniciada" @login-exitoso="iniciarSesion" />

    <!-- PANTALLA 2: SISTEMA PRINCIPAL -->
    <div v-else class="min-vh-100 bg-light">
      
      <!-- CABECERA INSTITUCIONAL CS CAMINOS (Naranja) -->
      <header class="bg-csc-orange shadow-sm mb-4">
        <div class="container py-3">
          <div class="row align-items-center">
            
            <!-- Logo / Nombre -->
            <div class="col-md-7 d-flex align-items-center gap-3">
              <div class="bg-white rounded-3 p-1 shadow-sm d-none d-sm-block">
                <img src="/img/logo-caminos.png" alt="Logo Caja de Salud" style="height: 45px; object-fit: contain;">
              </div>
              <div>
                <h3 class="fw-bold mb-0 text-white">Caja de Salud de Caminos</h3>
                <p class="small mb-0 text-white-50">Sistema de Control e Inventario de Almacén</p>
              </div>
            </div>

            <!-- Perfil del Usuario -->
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex align-items-center justify-content-md-end gap-3">
              <div class="text-end">
                <div class="fw-bold text-white-50 small">USUARIO ACTIVO</div>
                <div class="small text-white fw-semibold">{{ usuarioActual.nombre }} ({{ usuarioActual.rol }})</div>
              </div>
              <button class="btn btn-light btn-sm fw-bold border-0 shadow-sm ms-2 text-csc-orange" @click="cerrarSesion">
                Salir 🚪
              </button>
            </div>

          </div>
        </div>
      </header>

      <main class="container py-2">
        <!-- NAVEGACIÓN PRINCIPAL -->
        <nav class="nav nav-pills gap-2 mb-4 p-2 bg-white rounded-3 shadow-sm border border-light" aria-label="Navegación principal">
          <button 
            class="nav-link fw-bold px-4" 
            :class="vistaActual === 'ingreso' ? 'bg-csc-orange text-white shadow-sm' : 'bg-white text-secondary'" 
            type="button" 
            @click="vistaActual = 'ingreso'"
          >
            📦 Registrar Ingreso
          </button>
          
          <button 
            class="nav-link fw-bold px-4" 
            :class="vistaActual === 'salida' ? 'bg-csc-orange text-white shadow-sm' : 'bg-white text-secondary'" 
            type="button" 
            @click="vistaActual = 'salida'"
          >
            📋 Registrar Salida / Traspaso
          </button>
          
          <button 
            class="nav-link fw-bold px-4" 
            :class="vistaActual === 'inventario' ? 'bg-csc-orange text-white shadow-sm' : 'bg-white text-secondary'" 
            type="button" 
            @click="vistaActual = 'inventario'"
          >
            📊 Ver Inventario (Kardex)
          </button>
        </nav>

        <!-- MENSJAES Y ALERTAS -->
        <div v-show="vistaActual === 'ingreso' && mensajeExito" class="alert bg-soft-blue text-primary border-0 shadow-sm" role="alert">
          <strong class="text-primary">¡Éxito!</strong> {{ mensajeExito }}
          <button type="button" class="btn-close" aria-label="Cerrar" @click="mensajeExito = ''"></button>
        </div>

        <div v-show="vistaActual === 'ingreso' && obtenerError('general')" class="alert alert-danger shadow-sm border-0" role="alert">
          {{ obtenerError('general') }}
        </div>

        <!-- FORMULARIO DE INGRESO -->
        <form v-show="vistaActual === 'ingreso'" class="card shadow-sm border-0 mb-5" novalidate @submit.prevent="registrarIngreso">
          <!-- Cabecera de tarjeta en Azul Suave -->
          <div class="card-header bg-soft-blue text-dark fw-bold py-3 border-0">
            <h5 class="mb-0 fs-6 text-primary"><i class="bi bi-box-seam me-2"></i> Formulario de Registro de Ingreso de Medicamento</h5>
          </div>
          <div class="card-body p-4 bg-white">
            
            <section class="mb-4">
              <!-- Separadores en Naranja -->
              <h6 class="text-csc-orange border-bottom border-light pb-2 fw-bold">1. Datos del Medicamento</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="codigo" class="form-label fw-semibold small">Código institucional *</label>
                  <input id="codigo" v-model.trim="form.medicamento.codigo" class="form-control bg-light border-0" :class="claseError('medicamento.codigo')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.codigo') }}</div>
                </div>
                <div class="col-md-8">
                  <label for="nombre" class="form-label fw-semibold small">Nombre genérico *</label>
                  <input id="nombre" v-model.trim="form.medicamento.nombre" class="form-control bg-light border-0" :class="claseError('medicamento.nombre')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.nombre') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="concentracion" class="form-label fw-semibold small">Concentración *</label>
                  <input id="concentracion" v-model.trim="form.medicamento.concentracion" placeholder="Ej.: 500 mg" class="form-control bg-light border-0" :class="claseError('medicamento.concentracion')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.concentracion') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="forma" class="form-label fw-semibold small">Forma farmacéutica *</label>
                  <input id="forma" v-model.trim="form.medicamento.forma_farmaceutica" placeholder="Ej.: Comprimido" class="form-control bg-light border-0" :class="claseError('medicamento.forma_farmaceutica')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.forma_farmaceutica') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="presentacion" class="form-label fw-semibold small">Unidad de presentación *</label>
                  <input id="presentacion" v-model.trim="form.medicamento.unidad_presentacion" placeholder="Ej.: Caja x 100" class="form-control bg-light border-0" :class="claseError('medicamento.unidad_presentacion')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.unidad_presentacion') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="stock-minimo" class="form-label fw-semibold small">Stock mínimo *</label>
                  <input id="stock-minimo" v-model.number="form.medicamento.stock_minimo" type="number" min="0" class="form-control bg-light border-0" :class="claseError('medicamento.stock_minimo')" required>
                  <div class="invalid-feedback">{{ obtenerError('medicamento.stock_minimo') }}</div>
                </div>
                <div class="col-md-8">
                  <label for="descripcion" class="form-label fw-semibold small">Descripción</label>
                  <input id="descripcion" v-model.trim="form.medicamento.descripcion" class="form-control bg-light border-0" placeholder="Observaciones opcionales">
                </div>
              </div>
            </section>

            <section class="mb-4">
              <h6 class="text-csc-orange border-bottom border-light pb-2 fw-bold">2. Datos del Proveedor / Laboratorio</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="proveedor" class="form-label fw-semibold small">Nombre o razón social *</label>
                  <input id="proveedor" v-model.trim="form.proveedor.nombre" class="form-control bg-light border-0" :class="claseError('proveedor.nombre')" required>
                  <div class="invalid-feedback">{{ obtenerError('proveedor.nombre') }}</div>
                </div>
                <div class="col-md-3">
                  <label for="nit" class="form-label fw-semibold small">NIT</label>
                  <input id="nit" v-model.trim="form.proveedor.nit" class="form-control bg-light border-0" :class="claseError('proveedor.nit')">
                </div>
                <div class="col-md-3">
                  <label for="telefono" class="form-label fw-semibold small">Teléfono</label>
                  <input id="telefono" v-model.trim="form.proveedor.telefono" class="form-control bg-light border-0" :class="claseError('proveedor.telefono')">
                </div>
                <div class="col-md-6">
                  <label for="contacto" class="form-label fw-semibold small">Contacto</label>
                  <input id="contacto" v-model.trim="form.proveedor.contacto" class="form-control bg-light border-0" :class="claseError('proveedor.contacto')">
                </div>
                <div class="col-md-6">
                  <label for="direccion" class="form-label fw-semibold small">Dirección</label>
                  <input id="direccion" v-model.trim="form.proveedor.direccion" class="form-control bg-light border-0" :class="claseError('proveedor.direccion')">
                </div>
              </div>
            </section>

            <section>
              <h6 class="text-csc-orange border-bottom border-light pb-2 fw-bold">3. Lote e Ingreso</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="codigo-lote" class="form-label fw-semibold small">Código de lote *</label>
                  <input id="codigo-lote" v-model.trim="form.lote.codigo_lote" class="form-control bg-light border-0" :class="claseError('lote.codigo_lote')" required>
                  <div class="invalid-feedback">{{ obtenerError('lote.codigo_lote') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="vencimiento" class="form-label fw-semibold small">Fecha de vencimiento *</label>
                  <input id="vencimiento" v-model="form.lote.fecha_vencimiento" type="date" :min="fechaMinima" class="form-control bg-light border-0" :class="claseError('lote.fecha_vencimiento')" required>
                  <div class="invalid-feedback">{{ obtenerError('lote.fecha_vencimiento') }}</div>
                </div>
                <div class="col-md-4">
                  <label for="cantidad" class="form-label fw-semibold small">Cantidad ingresada *</label>
                  <input id="cantidad" v-model.number="form.lote.cantidad" type="number" min="1" class="form-control bg-light border-0" :class="claseError('lote.cantidad')" required>
                  <div class="invalid-feedback">{{ obtenerError('lote.cantidad') }}</div>
                </div>
              </div>
            </section>

          </div>

          <div class="card-footer bg-white p-4 d-flex justify-content-end border-top border-light">
            <button type="submit" class="btn btn-csc-orange px-5 shadow-sm fw-bold" :disabled="procesando">
              <span v-if="procesando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
              {{ procesando ? 'Guardando...' : 'Registrar Ingreso' }}
            </button>
          </div>
        </form>

        <!-- FORMULARIO DE SALIDA -->
        <RegistrarSalida v-show="vistaActual === 'salida'" />

        <!-- TABLA DE INVENTARIO (KARDEX) -->
        <Inventario v-if="vistaActual === 'inventario'" />

      </main>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import Login from './components/Login.vue';
import Inventario from './components/Inventario.vue';
import RegistrarSalida from './components/RegistrarSalida.vue';

const sesionIniciada = ref(false);
const usuarioActual = ref({ nombre: '', rol: '' });

const iniciarSesion = (datos) => {
  usuarioActual.value = datos;
  sesionIniciada.value = true;
};

const cerrarSesion = () => {
  sesionIniciada.value = false;
  usuarioActual.value = { nombre: '', rol: '' };
};

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
});

const form = ref(crearFormulario());
const vistaActual = ref('ingreso');
const errores = ref({});
const mensajeExito = ref('');
const procesando = ref(false);

const fechaMinima = computed(() => {
  const fecha = new Date();
  fecha.setDate(fecha.getDate() + 1);
  return fecha.toISOString().slice(0, 10);
});

const obtenerError = (campo) => errores.value[campo]?.[0] ?? '';
const claseError = (campo) => ({ 'is-invalid': Boolean(obtenerError(campo)) });

const registrarIngreso = async () => {
  procesando.value = true;
  errores.value = {};
  mensajeExito.value = '';

  try {
    const { data } = await axios.post('/api/ingresos', form.value);
    mensajeExito.value = data.message;
    form.value = crearFormulario();
  } catch (error) {
    if (error.response?.status === 422) {
      errores.value = error.response.data.errors;
      return;
    }
    errores.value = { general: [error.message || 'Ocurrió un error inesperado.'] };
  } finally {
    procesando.value = false;
  }
};
</script>

<style>
/* Paleta limpia: Naranja, Blanco y Azul Suave */
.bg-csc-orange {
  background-color: #e85d04 !important;
  color: #ffffff !important;
}
.text-csc-orange {
  color: #e85d04 !important;
}
.btn-csc-orange {
  background-color: #e85d04;
  color: #ffffff;
  border: none;
}
.btn-csc-orange:hover {
  background-color: #dc2f02;
  color: #ffffff;
}
.bg-soft-blue {
  background-color: #e3f2fd !important;
}
.text-primary {
  color: #1976d2 !important; /* Azul más legible para textos sobre azul suave */
}
</style>
