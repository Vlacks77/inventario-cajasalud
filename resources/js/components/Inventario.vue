<template>
    <div class="card shadow-sm border-0 rounded-3 mt-4">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i> Kardex de Inventario Actual</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Medicamento</th>
                            <th>Presentación</th>
                            <th class="text-center">Stock Total</th>
                            <th>Próximo Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cargando">
                            <td colspan="5" class="text-center py-4 text-muted">Cargando inventario...</td>
                        </tr>
                        <tr v-else-if="medicamentos.length === 0">
                            <td colspan="5" class="text-center py-4 text-muted">No hay medicamentos registrados en el sistema.</td>
                        </tr>
                        <tr v-else v-for="item in medicamentos" :key="item.id">
                            <td class="fw-bold text-secondary">{{ item.codigo }}</td>
                            <td class="fw-bold">{{ item.nombre }} <br><small class="text-muted fw-normal">{{ item.concentracion }}</small></td>
                            <td>{{ item.forma_farmaceutica }}</td>
                            <td class="text-center">
                                <span class="badge" :class="item.stock_total > 10 ? 'bg-primary' : 'bg-danger'">
                                    {{ item.stock_total }}
                                </span>
                            </td>
                            <td :class="{'text-danger fw-bold': esProximoAVencer(item.proximo_vencimiento)}">
                                {{ formatearFecha(item.proximo_vencimiento) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const medicamentos = ref([]);
const cargando = ref(true);

const cargarInventario = async () => {
    try {
        const respuesta = await axios.get('api/inventario');
        medicamentos.value = respuesta.data;
    } catch (error) {
        console.error("Error cargando el inventario:", error);
    } finally {
        cargando.value = false;
    }
};

// Función para poner en rojo si vence en menos de 90 días
const esProximoAVencer = (fecha) => {
    if (fecha === 'Sin stock') return false;
    const fechaVencimiento = new Date(fecha);
    const hoy = new Date();
    const diferenciaDias = (fechaVencimiento - hoy) / (1000 * 60 * 60 * 24);
    return diferenciaDias <= 90;
};

// Función para limpiar la fecha (de formato máquina a formato humano)
const formatearFecha = (fechaRaw) => {
    if (fechaRaw === 'Sin stock' || !fechaRaw) return 'Sin stock';
    
    // Convertimos el texto del servidor a un objeto de fecha de Javascript
    const fecha = new Date(fechaRaw);
    
    // Lo devolvemos en formato Boliviano (Día/Mes/Año)
    return fecha.toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

// Al cargar el componente, llamamos a la API
onMounted(() => {
    cargarInventario();
});
</script>