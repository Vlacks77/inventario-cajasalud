<template>
  <div class="csc-app">
    <div
      v-if="verificandoSesion"
      class="min-vh-100 d-flex align-items-center justify-content-center csc-session-loading"
    >
      <div class="text-center">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <div class="fw-semibold text-primary">Verificando sesión...</div>
      </div>
    </div>

    <Login
      v-else-if="!sesionIniciada"
      @login-exitoso="iniciarSesion"
    />

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

      <main class="csc-main" :class="{ 'csc-main-wide': vistaActual === 'cierre-mensual' }">
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

          <button
            class="csc-nav-link"
            :class="{ active: vistaActual === 'kardex' }"
            @click="vistaActual = 'kardex'"
          >
            Kardex / movimientos
          </button>

          <button class="csc-nav-link" :class="{ active: vistaActual === 'reportes' }" @click="vistaActual = 'reportes'">
            Reportes
          </button>

          <button class="csc-nav-link" :class="{ active: vistaActual === 'cierre-mensual' }" @click="vistaActual = 'cierre-mensual'">
            Inventario mensual
          </button>
        </nav>

        <RegistrarIngreso
          v-show="vistaActual === 'ingreso' && puedeModificar"
          :recibido-por="usuarioActual.nombre"
        />
        <RegistrarSalida v-show="vistaActual === 'salida' && puedeModificar" />
        <Inventario v-show="vistaActual === 'inventario'" />
        <Kardex v-show="vistaActual === 'kardex'" />
        <Reportes v-show="vistaActual === 'reportes'" />
        <CierreMensual v-show="vistaActual === 'cierre-mensual'" />

        <footer class="csc-system-credits" aria-label="Créditos del sistema">
          <a
            href="#sistemas"
            class="csc-system-credits-link"
            title="Área de Sistemas"
            aria-label="Área de Sistemas"
          >
            <img
              src="img/logo-sistemas-la-paz.jpg"
              alt="Sistemas La Paz - Caja de Salud de Caminos y R.A."
              class="csc-system-logo"
            >
            <span class="csc-system-credit-text">
              Desarrollado por V. Leonardo Quisbert Rios, Pasante de Sistemas
            </span>
          </a>
        </footer>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Login from './components/Login.vue';
import Inventario from './components/Inventario.vue';
import Kardex from './components/Kardex.vue';
import RegistrarSalida from './components/RegistrarSalida.vue';
import RegistrarIngreso from './components/RegistrarIngreso.vue';
import Reportes from './components/Reportes.vue';
import CierreMensual from './components/CierreMensual.vue';

const sesionIniciada = ref(false);
const verificandoSesion = ref(true);
const usuarioActual = ref({ nombre: '', rol: '' });
const vistaActual = ref('ingreso');

const puedeModificar = computed(() =>
  ['almacen', 'auxiliar', 'admin'].includes(usuarioActual.value.rol)
);

const limpiarSesionLocal = () => {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('usuario_actual');
  delete axios.defaults.headers.common.Authorization;
  usuarioActual.value = { nombre: '', rol: '' };
  sesionIniciada.value = false;
};

const iniciarSesion = datos => {
  usuarioActual.value = datos;
  sesionIniciada.value = true;
  vistaActual.value = ['almacen', 'auxiliar', 'admin'].includes(datos.rol)
    ? 'ingreso'
    : 'inventario';
};

const cerrarSesion = async () => {
  const token = localStorage.getItem('auth_token');

  try {
    if (token) {
      await axios.post(
        'api/logout',
        {},
        { headers: { Authorization: `Bearer ${token}` } }
      );
    }
  } catch {
    // Aunque el servidor no responda, limpiamos la sesión local.
  } finally {
    limpiarSesionLocal();
    verificandoSesion.value = false;
  }
};

onMounted(async () => {
  const token = localStorage.getItem('auth_token');

  // No hay sesión guardada: mostrar directamente el login.
  if (!token) {
    localStorage.removeItem('usuario_actual');
    verificandoSesion.value = false;
    return;
  }

  try {
    // Nunca confiamos únicamente en usuario_actual de localStorage.
    // Consultamos al servidor para comprobar que el token sigue siendo válido
    // y obtenemos el rol/nombre actuales desde la base de datos.
    axios.defaults.headers.common.Authorization = `Bearer ${token}`;

    const response = await axios.get('api/me');
    const user = response.data;

    const datos = {
      nombre: user.name,
      username: user.username,
      rol: user.role,
      id: user.id,
    };

    localStorage.setItem('usuario_actual', JSON.stringify(user));
    iniciarSesion(datos);
  } catch (error) {
    // Token inválido, eliminado o no autorizado: no mostramos la aplicación.
    if (error.response?.status === 401) {
      limpiarSesionLocal();
    } else {
      // Ante cualquier fallo de validación tampoco confiamos en una sesión
      // almacenada para evitar entrar a una pantalla incompleta.
      limpiarSesionLocal();
    }
  } finally {
    verificandoSesion.value = false;
  }
});
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

/* En Inventario mensual se aprovecha prácticamente todo el ancho de la pantalla. */
.csc-main {
  width: min(1500px, calc(100% - 32px));
  margin: 0 auto;
  padding: 24px 0 40px;
}

.csc-main.csc-main-wide {
  width: calc(100% - 24px);
  max-width: none;
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

.csc-session-loading {
  background: var(--csc-bg);
}

/* Créditos institucionales del sistema.
   Se mantienen dentro del flujo normal de la página (no fixed/sticky),
   por lo que aparecen al final de cada módulo y acompañan al contenido
   al hacer scroll. El logo queda preparado como enlace para la futura
   página del Área de Sistemas. */
.csc-system-credits {
  display: flex;
  justify-content: flex-end;
  margin: 28px 0 4px;
  padding: 18px 8px 8px;
  border-top: 1px solid var(--csc-border);
}

.csc-system-credits-link {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
  max-width: 270px;
  padding: 6px 10px;
  border-radius: 10px;
  text-decoration: none;
  cursor: pointer;
  transition: transform .18s ease, background-color .18s ease;
}

.csc-system-credits-link:hover {
  background: rgba(11, 61, 98, 0.045);
  transform: translateY(-1px);
}

.csc-system-logo {
  display: block;
  width: 180px;
  height: auto;
  max-height: 115px;
  object-fit: contain;
}

.csc-system-credit-text {
  display: block;
  color: #667788;
  font-size: .74rem;
  line-height: 1.25;
  text-align: center;
}

@media (max-width: 900px) {
  .csc-system-credits {
    justify-content: center;
    margin-top: 22px;
  }

  .csc-system-logo {
    width: 155px;
    max-height: 100px;
  }

  .csc-system-credit-text {
    font-size: .72rem;
  }
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
