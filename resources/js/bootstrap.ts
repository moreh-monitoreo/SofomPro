import axios from 'axios';
import 'https://js.stripe.com/v3/';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
