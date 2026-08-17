<template>
  <div class="csc-app">
    <Login v-if="!sesionIniciada" @login-exitoso="iniciarSesion" />

    <div v-else class="min-vh-100 csc-page">
      <header class="csc-topbar shadow-sm">
        <div class="csc-header">
          <div class="csc-header-left" aria-hidden="true">
            <img src="img/sello-presidencia-bolivia.png" alt="" class="csc-header-seal">
          </div>

          <div class="csc-header-title text-center">
            <h1 class="fw-bold mb-0 text-white">Caja de Salud de Caminos y R.A.</h1>
            <p class="mb-0 csc-header-subtitle">Sistema de gestión de almacén de medicamentos</p>
          </div>

          <div class="csc-header-right">
            <div class="text-end text-white me-2">
              <small>{{ usuarioActual.nombre }} ({{ usuarioActual.rol }})</small>
              <br>
              <button class="btn btn-light btn-sm mt-1 csc-logout" @click="cerrarSesion">Salir</button>
            </div>
            <img src="img/logo-csc-icon.png" alt="Caja de Salud de Caminos y R.A." class="csc-header-icon">
          </div>
        </div>
      </header>

      <main class="csc-main">
        <nav class="csc-nav">
          <button
            v-if="puedeModificar"
            class="csc-nav-link"
            :class="{ active: vistaActual === 'ingreso' }"
            @click="vistaActual = 'ingreso'"
          >
            Registrar ingreso
          </button>

          <button
            v-if="puedeModificar"
            class="csc-nav-link"
            :class="{ active: vistaActual === 'salida' }"
            @click="vistaActual = 'salida'"
          >
            Registrar salida
          </button>

          <button
            class="csc-nav-link"
            :class="{ active: vistaActual === 'inventario' }"
            @click="vistaActual = 'inventario'"
          >
            Ver inventario
          </button>
        </nav>

        <RegistrarIngreso
          v-if="vistaActual === 'ingreso' && puedeModificar"
          :recibido-por="usuarioActual.nombre"
        />
        <RegistrarSalida v-if="vistaActual === 'salida' && puedeModificar" />
        <Inventario v-if="vistaActual === 'inventario'" />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import Login from './components/Login.vue';
import Inventario from './components/Inventario.vue';
import RegistrarSalida from './components/RegistrarSalida.vue';
import RegistrarIngreso from './components/RegistrarIngreso.vue';

const sesionIniciada = ref(false);
const usuarioActual = ref({ nombre: '', rol: '' });
const vistaActual = ref('ingreso');

const puedeModificar = computed(() =>
  ['almacen', 'auxiliar', 'admin'].includes(usuarioActual.value.rol)
);

onMounted(() => {
  const u = localStorage.getItem('usuario_actual');
  const t = localStorage.getItem('auth_token');

  if (u && t) {
    usuarioActual.value = JSON.parse(u);
    sesionIniciada.value = true;
  }
});

const iniciarSesion = datos => {
  usuarioActual.value = datos;
  sesionIniciada.value = true;
};

const cerrarSesion = () => {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('usuario_actual');
  sesionIniciada.value = false;
};
</script>

<style>
:root {
  --csc-blue-dark: #0b3d62;
  --csc-blue: #164f78;
  --csc-orange: #e85d04;
  --csc-orange-hover: #d94f00;
  --csc-bg: #f4f7fa;
  --csc-border: #e1e7ed;
  --csc-text: #243447;
  --csc-muted: #667788;
}

* {
  box-sizing: border-box;
}

body {
  background: var(--csc-bg);
  color: var(--csc-text);
}

.bg-csc-orange {
  background-color: var(--csc-orange) !important;
  color: #fff !important;
}

.bg-csc-blue-dark {
  background-color: var(--csc-blue-dark) !important;
}

.text-csc-orange {
  color: var(--csc-orange) !important;
}

.btn-csc-orange {
  background-color: var(--csc-orange);
  color: #fff;
  border: 0;
}

.btn-csc-orange:hover {
  background-color: var(--csc-orange-hover);
  color: #fff;
  transform: translateY(-1px);
}

.bg-soft-blue {
  background-color: #eef5fa !important;
}

.text-primary {
  color: var(--csc-blue-dark) !important;
}

.csc-page {
  background:
    radial-gradient(circle at top left, rgba(11, 61, 98, 0.035), transparent 30%),
    var(--csc-bg);
}

.csc-topbar {
  background: var(--csc-orange);
  border-bottom: 4px solid rgba(11, 61, 98, 0.12);
}

.csc-header {
  width: 100%;
  min-height: 78px;
  padding: 8px 18px;
  display: grid;
  grid-template-columns: 1fr minmax(520px, auto) 1fr;
  align-items: center;
  gap: 16px;
}

.csc-header-left {
  display: flex;
  align-items: center;
  justify-content: flex-start;
}

.csc-header-seal {
  width: 58px;
  height: 58px;
  object-fit: contain;
  background: #fff;
  border-radius: 50%;
  padding: 2px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.csc-header-title {
  justify-self: center;
  min-width: 0;
}

.csc-header-title h1 {
  font-size: 1.62rem;
  line-height: 1.15;
  white-space: nowrap;
  letter-spacing: -0.02em;
}

.csc-header-subtitle {
  font-size: 1.03rem;
  line-height: 1.25;
  color: rgba(255, 255, 255, 0.96);
}

.csc-header-right {
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 10px;
}

.csc-header-right small {
  white-space: nowrap;
}

.csc-logout {
  border: 0;
  font-weight: 600;
}

.csc-header-icon {
  width: 58px;
  height: 58px;
  object-fit: contain;
  background: #fff;
  border-radius: 10px;
  padding: 3px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.csc-main {
  width: min(1500px, calc(100% - 32px));
  margin: 0 auto;
  padding: 24px 0 40px;
}

.csc-nav {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px;
  margin-bottom: 22px;
  background: #fff;
  border: 1px solid var(--csc-border);
  border-radius: 12px;
  box-shadow: 0 3px 12px rgba(20, 48, 70, 0.06);
}

.csc-nav-link {
  appearance: none;
  border: 0;
  background: transparent;
  color: #536779;
  font-weight: 700;
  padding: 10px 18px;
  border-radius: 8px;
  transition: all 0.18s ease;
}

.csc-nav-link:hover {
  color: var(--csc-blue-dark);
  background: #f2f6f9;
}

.csc-nav-link.active {
  color: #fff;
  background: var(--csc-orange);
  box-shadow: 0 3px 8px rgba(232, 93, 4, 0.18);
}

@media (max-width: 900px) {
  .csc-header {
    grid-template-columns: auto 1fr auto;
    gap: 8px;
    padding: 8px 10px;
  }

  .csc-header-title h1 {
    font-size: 1.35rem;
  }

  .csc-header-subtitle {
    font-size: 0.9rem;
  }

  .csc-header-seal,
  .csc-header-icon {
    width: 48px;
    height: 48px;
  }

  .csc-header-right small {
    display: none;
  }

  .csc-main {
    width: min(100% - 18px, 1500px);
    padding-top: 14px;
  }

  .csc-nav {
    overflow-x: auto;
    white-space: nowrap;
  }
}
</style>
