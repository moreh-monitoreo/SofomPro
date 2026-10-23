<script setup>
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { nextTick, onMounted, ref } from 'vue';

const page = usePage();
const siteKey = page.props.recaptchaSiteKey;

const form = ref({ name: '', email: '', phone: '', message: '', website: '' });
const errors = ref({});
const sending = ref(false);
const sent = ref(false);
const failure = ref('');

const captchaBox = ref(null);
const captchaToken = ref('');
let captchaWidget = null;

const loadRecaptcha = () =>
    new Promise((resolve) => {
        if (window.grecaptcha?.render) return resolve();
        window.onRecaptchaReady = resolve;
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?onload=onRecaptchaReady&render=explicit';
        script.async = true;
        document.head.appendChild(script);
    });

const renderCaptcha = async () => {
    await loadRecaptcha();
    await nextTick();
    if (!captchaBox.value) return;
    window.grecaptcha.ready(() => {
        captchaWidget = window.grecaptcha.render(captchaBox.value, {
            sitekey: siteKey,
            callback: (token) => (captchaToken.value = token),
            'expired-callback': () => (captchaToken.value = ''),
        });
    });
};

onMounted(() => {
    if (siteKey) renderCaptcha();
});

const validate = () => {
    errors.value = {};
    if (!form.value.name.trim()) errors.value.name = 'Escribe tu nombre.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) errors.value.email = 'Escribe un correo válido.';
    if (form.value.phone && !/^[0-9]{10}$/.test(form.value.phone)) errors.value.phone = 'El teléfono debe tener 10 dígitos.';
    if (siteKey && !captchaToken.value) errors.value.captcha = 'Confirma que no eres un robot.';
    return Object.keys(errors.value).length === 0;
};

const submit = async () => {
    failure.value = '';
    if (!validate()) return;

    sending.value = true;
    try {
        await axios.post(route('site.contact.store'), {
            ...form.value,
            'g-recaptcha-response': captchaToken.value,
        });
        sent.value = true;
        form.value = { name: '', email: '', phone: '', message: '', website: '' };
    } catch (error) {
        const data = error.response?.data;
        if (data?.errors) {
            errors.value = Object.fromEntries(Object.entries(data.errors).map(([key, value]) => [key, value[0]]));
        } else {
            failure.value =
                error.response?.status === 429
                    ? 'Enviaste muchas solicitudes. Espera un minuto e inténtalo de nuevo.'
                    : 'No pudimos enviar tu solicitud. Inténtalo de nuevo en unos minutos.';
        }
        if (captchaWidget !== null) window.grecaptcha.reset(captchaWidget);
        captchaToken.value = '';
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <div
        class="mt-[150px] flex min-h-[80vh] w-full flex-col items-center pb-16 lg:min-h-[65vh]"
        style="background-image: url('/Fotos/fonto-gris-claro.png')"
    >
        <section class="py-16 text-center text-white">
            <h2 class="text-[40px] font-bold">
                ¿Listo para solicitar tu préstamo?
            </h2>
            <p class="mt-4 text-[20px] font-medium">
                Haz clic aquí para comenzar tu proceso de solicitud en línea.
            </p>
            <a
                :href="route('site.requestcredit')"
                class="mt-8 inline-block rounded-full bg-white px-8 py-4 text-[14px] font-bold text-primary"
                >Solicita tu préstamo</a
            >
        </section>

        <div
            class="mt-[3vh] flex w-full justify-self-end bg-white lg:w-[885px]"
        >
            <img
                src="/Fotos/contactoform.jpg"
                alt="contacto"
                class="hidden w-2/4 object-cover lg:block lg:h-full"
            />
            <div class="relative w-full px-10 py-8 lg:w-2/4">
                <h3 class="text-center text-[24px] font-bold">
                    Agenda tu cita
                </h3>

                <div
                    v-if="sent"
                    role="status"
                    class="mt-8 rounded-lg border border-green-300 bg-green-50 px-4 py-6 text-center text-[14px] text-green-800"
                >
                    <p class="font-semibold">¡Gracias por contactarnos!</p>
                    <p class="mt-1">
                        Recibimos tu solicitud y te enviamos un correo de confirmación. Un asesor te contactará pronto.
                    </p>
                    <button type="button" class="mt-4 text-[13px] font-semibold underline" @click="sent = false">
                        Enviar otra solicitud
                    </button>
                </div>

                <form v-else class="mt-8 space-y-4" novalidate @submit.prevent="submit">
                    <div>
                        <label for="name" class="mb-2 block text-[14px] font-semibold">Nombre *</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            name="name"
                            maxlength="100"
                            placeholder="Ingresa tu nombre"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-[14px] font-semibold">Correo *</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            placeholder="Ingresa tu correo"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label for="phone" class="mb-2 block text-[14px] font-semibold">Teléfono (10 dígitos)</label>
                        <input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            name="phone"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="6671234567"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
                    </div>

                    <div>
                        <label for="message" class="mb-2 block text-[14px] font-semibold">Mensaje</label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            name="message"
                            rows="5"
                            maxlength="2000"
                            placeholder="Escribe tu mensaje"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <label for="website">No llenes este campo</label>
                        <input id="website" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                    </div>

                    <div v-if="siteKey">
                        <div ref="captchaBox"></div>
                        <p v-if="errors.captcha" class="mt-1 text-sm text-red-600">{{ errors.captcha }}</p>
                    </div>

                    <p v-if="failure" role="alert" class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ failure }}
                    </p>

                    <p class="text-[12px] text-gray-500">
                        Al enviar aceptas el
                        <a :href="route('site.privacy')" target="_blank" class="font-semibold underline">Aviso de Privacidad</a>.
                    </p>

                    <div class="text-center">
                        <button
                            type="submit"
                            :disabled="sending"
                            class="mt-2 w-full rounded-[5px] bg-primary px-7 py-4 text-[14px] font-semibold text-white disabled:cursor-wait disabled:opacity-60"
                        >
                            {{ sending ? 'Enviando…' : 'Agenda tu cita' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
