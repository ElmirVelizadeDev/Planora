import './bootstrap';

import { createApp } from 'vue';

const app = createApp({
    mounted() {
        console.log('Planora Vue application is running!');
    },
});

app.mount('#app');
