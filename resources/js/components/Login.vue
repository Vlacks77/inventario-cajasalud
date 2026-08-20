<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-login py-5 position-relative" style="background-image: linear-gradient(rgba(244, 247, 250, .50), rgba(231, 239, 245, .50)), url('img/login-almacen.png');">
    <div class="card shadow-lg border-0 rounded-4 overflow-hidden position-relative" style="max-width: 440px; width: 100%;">

      <!-- Banner Superior Naranja Institucional -->
      <div class="bg-csc-orange text-center text-white p-4 position-relative">

        <div class="bg-white rounded-3 d-inline-flex p-2 mb-3 shadow-sm">
          <img
            src="img/logo-caminos.png"
            alt="Logo Caja de Salud de Caminos"
            style="height: 200px; object-fit: contain;"
          >
        </div>

        <h4 class="fw-bold mb-1 login-main-title">
          SISTEMA DE GESTIÓN <br>
          ALMACÉN DE MEDICAMENTOS
        </h4>

        <p class="login-subtitle mb-0">
          CAJA DE SALUD DE CAMINOS
        </p>
      </div>

      <!-- Formulario de Credenciales -->
      <div class="card-body p-4 p-md-5 bg-white">

        <h5 class="text-csc-orange fw-bold mb-4 text-center">
          Acceso al Sistema
        </h5>

        <!-- Mensaje de error -->
        <div
          v-if="error"
          class="alert alert-danger py-2 small"
          role="alert"
        >
          {{ error }}
        </div>

        <form @submit.prevent="autenticar">

          <div class="mb-3">
            <label class="form-label fw-bold text-secondary small">
              USUARIO
            </label>

            <input
              type="text"
              v-model.trim="usuario"
              class="form-control form-control-lg fs-6 border-soft-blue bg-light"
              placeholder="Ej: dra.carmen-almacen"
              autocomplete="username"
              required
              :disabled="procesando"
            >
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold text-secondary small">
              CONTRASEÑA
            </label>

            <input
              type="password"
              v-model="password"
              class="form-control form-control-lg fs-6 border-soft-blue bg-light"
              placeholder="••••••••"
              autocomplete="current-password"
              required
              :disabled="procesando"
            >
          </div>

          <button
            type="submit"
            class="btn btn-csc-orange btn-lg w-100 fw-bold shadow-sm"
            :disabled="procesando"
          >
            <span
              v-if="procesando"
              class="spinner-border spinner-border-sm me-2"
              aria-hidden="true"
            ></span>

            {{ procesando ? 'Verificando...' : 'Ingresar al Sistema' }}
          </button>

        </form>
      </div>

      <div class="card-footer bg-white text-center py-3 border-top border-soft-blue">
        <small class="text-muted">
          Departamento de Sistemas · La Paz, Bolivia
        </small>
      </div>

    </div>
  </div>
</template>

<script setup>
import axios from 'axios';
import { ref } from 'vue';

const emit = defineEmits(['login-exitoso']);

const usuario = ref('');
const password = ref('');
const error = ref('');
const procesando = ref(false);

const autenticar = async () => {
  error.value = '';
  procesando.value = true;

  try {
    const response = await axios.post('api/login', {
      username: usuario.value,
      password: password.value,
    });

    const datos = response.data;

    // Guardamos el token de autenticación.
    localStorage.setItem('auth_token', datos.token);

    // Guardamos los datos del usuario autenticado.
    localStorage.setItem('usuario_actual', JSON.stringify(datos.user));

    // Informamos a App.vue que el login fue exitoso.
    emit('login-exitoso', {
      nombre: datos.user.name,
      username: datos.user.username,
      rol: datos.user.role,
      id: datos.user.id,
    });

  } catch (err) {

    if (err.response?.status === 401) {
      error.value = 'Usuario o contraseña incorrectos.';
    } else if (err.response?.status === 422) {
      error.value = 'Debe ingresar usuario y contraseña.';
    } else {
      error.value = 'No se pudo conectar con el servidor.';
    }

  } finally {
    procesando.value = false;
  }
};
</script>

<style scoped>
.bg-login {
  min-height: 100vh;
  background-color: #edf2f6;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  animation: loginWarehouseMotion 5s ease-in-out infinite alternate;
}

@keyframes loginWarehouseMotion {
  0% { background-position: 40% 50%; background-size: 100%; }
  100% { background-position: 60% 50%; background-size: 115%; }
}

@media (prefers-reduced-motion: reduce) {
  .bg-login { animation: none; }
}

.bg-csc-orange {
  background-color: #e85d04;
  color: #ffffff;
}

.text-csc-orange {
  color: #e85d04;
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

.btn-csc-orange:disabled {
  opacity: 0.75;
}

.border-soft-blue {
  border: 1px solid #bbdefb !important;
}

.login-main-title {
  color: #0e305a;
  font-family: Arial, sans-serif;
  font-size: 20px;
  font-weight: 1100;
  line-height: 1.2;
  text-align: center;
}

.login-subtitle {
  color: white;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
}
</style>