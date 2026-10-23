<script setup>
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const messages = {
    403: {
        title: 'No tienes acceso a esta página',
        text: 'Tu cuenta no tiene permiso para ver este contenido. Si crees que es un error, comunícate con nosotros.',
    },
    404: {
        title: 'No encontramos esta página',
        text: 'La dirección que buscas no existe o cambió de lugar. Puedes volver al inicio o calcular tu préstamo en el simulador.',
    },
    419: {
        title: 'Tu sesión expiró',
        text: 'Pasó mucho tiempo sin actividad. Recarga la página e inténtalo de nuevo.',
    },
    429: {
        title: 'Demasiados intentos',
        text: 'Hiciste muchas solicitudes en poco tiempo. Espera un minuto e inténtalo de nuevo.',
    },
    500: {
        title: 'Algo salió mal de nuestro lado',
        text: 'Ya registramos el problema. Intenta de nuevo en unos minutos.',
    },
    503: {
        title: 'Estamos en mantenimiento',
        text: 'Volvemos en unos minutos. Gracias por tu paciencia.',
    },
};

const reload = () => window.location.reload();

const message = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Head :title="`${status} - ${message.title}`" />

    <Header></Header>

    <main class="container mx-auto flex min-h-[60vh] flex-col items-center justify-center px-6 py-20 text-center">
        <p class="text-[80px] font-extrabold leading-none text-primary md:text-[120px]">{{ status }}</p>
        <h1 class="mt-4 text-[28px] font-bold md:text-[36px]">{{ message.title }}</h1>
        <p class="mt-4 max-w-xl text-[16px] text-gray-600">{{ message.text }}</p>

        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <a
                :href="route('site.index')"
                class="rounded-full bg-primary px-8 py-4 text-[14px] font-bold text-white transition hover:bg-orange-500"
            >
                Ir al inicio
            </a>
            <a
                :href="`${route('site.index')}#simulador`"
                class="rounded-full border border-primary px-8 py-4 text-[14px] font-bold text-primary transition hover:bg-orange-50"
            >
                Usar el simulador
            </a>
            <button
                v-if="status === 419"
                type="button"
                class="rounded-full border border-gray-300 px-8 py-4 text-[14px] font-bold text-gray-700 transition hover:bg-gray-50"
                @click="reload"
            >
                Recargar
            </button>
        </div>
    </main>

    <Footer></Footer>
</template>
