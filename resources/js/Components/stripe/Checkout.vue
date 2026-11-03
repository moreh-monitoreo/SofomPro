<script setup>
import axios from 'axios';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import PrimaryButton from '../ui/button/Button.vue';

const props = defineProps({
    payload: { type: Object, required: true },
});

const toast = useToast();
const loading = ref(false);

async function checkout() {
    if (loading.value) return;
    loading.value = true;

    try {
        const { data } = await axios.post('/create-checkout-session', { ...props.payload });
        if (!data.url) {
            throw new Error('Stripe no devolvió la dirección de pago.');
        }
        window.location.assign(data.url);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo iniciar el pago. Inténtalo de nuevo.');
        loading.value = false;
    }
}
</script>

<template>
    <PrimaryButton :disabled="loading" @click="checkout">
        <slot></slot>
    </PrimaryButton>
</template>
