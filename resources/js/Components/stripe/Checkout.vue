<template>
    <PrimaryButton @click="checkout">
        <slot></slot>
    </PrimaryButton>
</template>

<script>
import PrimaryButton from '../ui/button/Button.vue';

export default {
    components: { PrimaryButton },
    props: ['payload'],
    methods: {
        async checkout() {
            const response = await axios.post('/create-checkout-session', {
                ...this.payload,
            });

            const stripe = Stripe('pk_test_ienVbB7rmBmGjaydr01N8cLY00jX4KmqfG');
            const { error } = await stripe.redirectToCheckout({
                sessionId: response.data.id,
            });

            if (error) {
                console.error(error);
            }
        },
    },
};
</script>
