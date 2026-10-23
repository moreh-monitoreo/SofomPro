<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const toggle = async () => {
    useRecovery.value = !useRecovery.value;
    form.reset();
    form.clearErrors();
    await nextTick();
    document.getElementById(useRecovery.value ? 'recovery_code' : 'code')?.focus();
};

const submit = () => {
    form.transform((data) => (useRecovery.value ? { recovery_code: data.recovery_code } : { code: data.code })).post(route('two-factor.verify'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Verificación en dos pasos" />

        <h1 class="text-lg font-semibold text-gray-900">Verificación en dos pasos</h1>
        <p class="mt-2 text-sm text-gray-600">
            {{
                useRecovery
                    ? 'Escribe uno de los códigos de recuperación que guardaste. Cada código se puede usar una sola vez.'
                    : 'Abre tu aplicación de autenticación y escribe el código de 6 dígitos.'
            }}
        </p>

        <form class="mt-5" @submit.prevent="submit">
            <template v-if="!useRecovery">
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
            </template>
            <template v-else>
                <InputLabel for="recovery_code" value="Código de recuperación" />
                <TextInput
                    id="recovery_code"
                    v-model="form.recovery_code"
                    type="text"
                    autocomplete="off"
                    placeholder="xxxxx-xxxxx"
                    class="mt-1 block w-full text-center font-mono"
                    required
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </template>

            <div class="mt-4 flex items-center justify-between">
                <button type="button" class="text-sm text-gray-600 underline hover:text-gray-900" @click="toggle">
                    {{ useRecovery ? 'Usar mi aplicación' : 'Usar un código de recuperación' }}
                </button>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Verificar</PrimaryButton>
            </div>
        </form>

        <div class="mt-6 border-t pt-4 text-center">
            <Link :href="route('logout')" method="post" as="button" class="text-sm text-gray-600 underline hover:text-gray-900">
                Cerrar sesión
            </Link>
        </div>
    </GuestLayout>
</template>
