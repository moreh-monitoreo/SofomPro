<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

defineProps<{
    enabled: boolean;
    recoveryCodesLeft: number;
}>();

const form = useForm({ password: '' });

const regenerate = () => {
    form.post(route('two-factor.regenerate'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Verificación en dos pasos</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Protege tu cuenta con un código temporal de tu aplicación de autenticación además de tu contraseña.
            </p>
        </header>

        <div class="mt-4">
            <span
                :class="[
                    'rounded px-3 py-1 text-sm font-semibold',
                    enabled ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800',
                ]"
            >
                {{ enabled ? 'Activada' : 'Desactivada' }}
            </span>
            <span v-if="enabled" class="ml-3 text-sm text-gray-600">
                Te quedan {{ recoveryCodesLeft }} código(s) de recuperación.
            </span>
        </div>

        <form v-if="enabled" class="mt-6 max-w-xl" @submit.prevent="regenerate">
            <p class="text-sm text-gray-600">Si ya usaste varios códigos de recuperación o crees que alguien los vio, genera unos nuevos; los anteriores dejan de funcionar.</p>
            <div class="mt-3">
                <InputLabel for="two_factor_password" value="Tu contraseña actual" />
                <TextInput id="two_factor_password" v-model="form.password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <PrimaryButton class="mt-4" :disabled="form.processing || !form.password">Generar códigos nuevos</PrimaryButton>
        </form>
    </section>
</template>
