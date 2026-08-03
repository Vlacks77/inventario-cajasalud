import axios from 'axios';

window.axios = axios;

// Usa automáticamente la carpeta donde está instalado el sistema
const base = window.location.origin + window.location.pathname.replace(/\/$/, '');

window.axios.defaults.baseURL = base;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
