<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    qr: string;
    secret: string;
}>();

const form = useForm({ code: '' });

const submit = () => {
    form.post(route('two-factor.confirm'), {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Activar verificación en dos pasos" />

        <h1 class="text-lg font-semibold text-gray-900">Activa la verificación en dos pasos</h1>
        <p class="mt-2 text-sm text-gray-600">
            Las cuentas del equipo necesitan un segundo paso para entrar. Sigue estos pasos una sola vez:
        </p>

        <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-gray-700">
            <li>Instala una aplicación de autenticación en tu teléfono (Google Authenticator, Microsoft Authenticator, Authy…).</li>
            <li>Escanea este código QR con la aplicación.</li>
            <li>Escribe aquí el código de 6 dígitos que muestra.</li>
        </ol>

        <div class="mt-5 flex justify-center rounded-lg border border-gray-200 bg-white p-3" v-html="qr" />

        <details class="mt-3 text-sm text-gray-600">
            <summary class="cursor-pointer">¿No puedes escanear? Escribe la clave a mano</summary>
            <p class="mt-2 select-all break-all rounded bg-gray-100 p-2 font-mono text-xs">{{ secret }}</p>
        </details>

        <form class="mt-5" @submit.prevent="submit">
            <InputLabel for="code" value="Código de 6 dígitos" />
            <TextInput
                id="code"
                v-model="form.code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="7"
                class="mt-1 block w-full text-center text-xl tracking-widest"
                required
                autofocus
            />
            <InputError class="mt-2" :message="form.errors.code" />

            <div class="mt-4 flex items-center justify-between">
                <Link :href="route('logout')" method="post" as="button" class="text-sm text-gray-600 underline hover:text-gray-900">
                    Cerrar sesión
                </Link>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Activar</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
