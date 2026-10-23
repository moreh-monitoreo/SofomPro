<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    codes: string[];
}>();

const copied = ref(false);

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.codes.join('\n'));
        copied.value = true;
    } catch {
        copied.value = false;
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Códigos de recuperación" />

        <h1 class="text-lg font-semibold text-gray-900">Guarda tus códigos de recuperación</h1>
        <p class="mt-2 text-sm text-gray-600">
            Si pierdes tu teléfono, cada uno de estos códigos te deja entrar una vez. <strong>Esta es la única vez que se muestran</strong>:
            guárdalos en un lugar seguro (por ejemplo, un gestor de contraseñas).
        </p>

        <ul class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-4 font-mono text-sm">
            <li v-for="code in codes" :key="code" class="select-all">{{ code }}</li>
        </ul>

        <div class="mt-4 flex items-center justify-between">
            <button type="button" class="text-sm text-gray-600 underline hover:text-gray-900" @click="copy">
                {{ copied ? '¡Copiados!' : 'Copiar códigos' }}
            </button>
            <Link :href="route('dashboard')">
                <PrimaryButton type="button">Ya los guardé, continuar</PrimaryButton>
            </Link>
        </div>
    </GuestLayout>
</template>
