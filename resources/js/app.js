import './bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';

import { createApp } from 'vue';
import App from './App.vue'; // Importamos el diseño que acabas de crear

// Iniciamos la app inyectando el componente App
createApp(App).mount('#app');