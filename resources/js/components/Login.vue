<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-login py-5">
    <div class="card shadow-lg border-0 rounded-4 overflow-hidden" style="max-width: 440px; width: 100%;">
      
      <!-- Banner Superior Naranja Institucional -->
      <div class="bg-csc-orange text-center text-white p-4 position-relative">
        
        <!-- AQUÍ ESTÁ EL CAMBIO: Contenedor blanco con el logo -->
        <div class="bg-white rounded-3 d-inline-flex p-2 mb-3 shadow-sm">
          <img src="img/logo-caminos.png" alt="Logo Caja de Salud de Caminos" style="height: 200px; object-fit: contain;">
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
        <h5 class="text-csc-orange fw-bold mb-4 text-center">Acceso al Sistema</h5>

        <form @submit.prevent="autenticar">
          <div class="mb-3">
            <label class="form-label fw-bold text-secondary small">USUARIO / CÓDIGO</label>
            <input 
              type="text" 
              v-model="usuario" 
              class="form-control form-control-lg fs-6 border-soft-blue bg-light" 
              placeholder="Ej: dra.almacen" 
              required
            >
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-secondary small">CONTRASEÑA</label>
            <input 
              type="password" 
              v-model="password" 
              class="form-control form-control-lg fs-6 border-soft-blue bg-light" 
              placeholder="••••••••" 
              required
            >
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold text-secondary small">PERFIL / ROL</label>
            <select v-model="rol" class="form-select form-select-lg fs-6 border-soft-blue bg-light" required>
              <option value="Dra. Encargada de Almacén">👩‍⚕️ Dra. Encargada de Almacén</option>
              <option value="Auxiliar de Farmacia">👨‍⚕️ Auxiliar / Farmacia</option>
              <option value="Ingeniero Administrador">💻 Administrador / Sistemas</option>
            </select>
          </div>

          <button type="submit" class="btn btn-csc-orange btn-lg w-100 fw-bold shadow-sm">
            Ingresar al Sistema
          </button>
        </form>
      </div>

      <div class="card-footer bg-white text-center py-3 border-top border-soft-blue">
        <small class="text-muted">Departamento de Sistemas · La Paz, Bolivia</small>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['login-exitoso']);

const usuario = ref('');
const password = ref('');
const rol = ref('Dra. Encargada de Almacén');

const autenticar = () => {
  if (usuario.value.trim() !== '') {
    emit('login-exitoso', {
      nombre: usuario.value,
      rol: rol.value
    });
  }
};
</script>

<style scoped>
/* Fondo general: degradado sutil de blanco a azul muy suave */
.bg-login {
  background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
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
.border-soft-blue {
  border: 1px solid #bbdefb !important;
}
.login-main-title {
    color: #123b6d;
    font-size: 20px;
    line-height: 1.2;
    text-align: center;
}

.login-subtitle {
    color: white;
    font-size: 15px;
    font-weight: 400;
    text-align: center;
}
</style>