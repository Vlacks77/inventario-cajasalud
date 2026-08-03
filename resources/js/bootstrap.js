import axios from 'axios';

window.axios = axios;

window.axios.defaults.baseURL =
    window.location.origin + '/inventario-cajasalud/public';

window.axios.defaults.headers.common['X-Requested-With'] =
    'XMLHttpRequest';
