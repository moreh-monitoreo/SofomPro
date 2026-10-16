<script setup lang="ts">
import Simulator from '@/Components/home/Simulator.vue';
import ContactForm from '@/Layouts/partials/ContactForm.vue';
import Faq from '@/Layouts/partials/Faq.vue';
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';

// JavaScript para funcionalidad de acordeón con imágenes
document.querySelectorAll('.faq-question').forEach((question) => {
    question.addEventListener('click', () => {
        const answer = question.nextElementSibling;
        const icon = question.querySelector(
            '.faq-icon img',
        ) as HTMLImageElement;

        // Cerrar otros acordeones abiertos
        document.querySelectorAll('.faq-answer').forEach((answerItem) => {
            if (
                answerItem !== answer &&
                answerItem.classList.contains('open')
            ) {
                answerItem.classList.remove('open');
                if (answerItem.previousElementSibling) {
                    const imgElement =
                        answerItem.previousElementSibling.querySelector(
                            '.faq-icon img',
                        ) as HTMLImageElement;
                    if (imgElement) {
                        imgElement.src = '/Fotos/arrow-right.png';
                    }
                }
            }
        });

        // Alternar el acordeón actual
        answer?.classList.toggle('open');

        // Cambiar imagen del ícono según el estado (abierto/cerrado)
        if (answer?.classList.contains('open') && icon) {
            icon.src = '/Fotos/arrow-down.png'; // Imagen para el acordeón abierto
        } else {
            icon.src = '/Fotos/arrow-right.png'; // Imagen para el acordeón cerrado
        }
    });
});
</script>

<template>
    <Head title="PRO MÁS" />

    <Header></Header>

    <div
        class="relative flex h-[90vh] flex-col justify-center bg-cover bg-center"
        style="background-image: url('Fotos/hero.jpg')"
    >
        <div
            class="absolute inset-0 bg-gradient-to-r from-black to-transparent"
        ></div>
        <div class="container relative mx-auto px-8 py-20 text-white md:px-0">
            <h1
                class="md:text[40px] w-full text-[40px] font-extrabold md:text-[58px]"
            >
                Préstamo rápido y sencillo
            </h1>
            <p
                class="mt-4 w-full text-[16px] font-medium md:w-[380px] md:text-[20px]"
            >
                Descubre la mejor manera de financiar tus sueños con confianza y seguridad.
            </p>
            <div class="flex flex-wrap gap-x-4">
                <a
                    :href="route('site.requestcredit')"
                    class="mt-8 rounded-full border border-transparent bg-primary px-5 py-3 text-[12px] font-bold text-white transition duration-200 hover:border-primary hover:bg-orange-500 hover:text-white sm:px-8 sm:py-4 sm:text-[14px]"
                >
                    Solicita tu préstamo
                </a>
                <button
                    class="mt-8 rounded-full border border-orange-500 bg-orange-500 px-5 py-3 text-[12px] font-bold transition duration-200 hover:bg-orange-500 hover:text-white sm:bg-transparent sm:px-8 sm:py-4 sm:text-[14px] sm:text-white"
                >
                    Contacta con nuestro equipo
                </button>
            </div>
        </div>
    </div>

    <!-- Simulador de Préstamos -->
    <div
        class="mt-[150px] flex h-[120vh] md:h-[80vh] w-full flex-col items-center lg:h-[80vh]"
        style="background-image: url('/Fotos/texture.webp')"
    >
        <div class="simulador container mx-auto mt-16 text-center">
            <h2 class="text-[32px] font-bold md:text-[40px] text-white">
                Simulador de préstamos
            </h2>
            <p class="mt-4 text-[16px] text-white">
                Calcula tu préstamo de manera rápida y sencilla.
            </p>
            <Simulator></Simulator>
        </div>
    </div>

    <div class="gancho-venta container mx-auto mt-12">
        <h2 class="text-center text-[40px] font-bold">Acerca de nosotros</h2>
        <div
            class="flex flex-col items-center justify-center gap-x-8 md:flex-row"
        >
            <div class="w-full md:w-1/2">
                <img src="Fotos/about-us.jpg" alt="logo" class="mx-auto py-6" />
            </div>
            <div class="flex w-full flex-col justify-center px-6 md:w-1/2">
                <div class="md:w-[400px]">
                    <h3 class="text-[24px] font-bold">
                        Tu confianza es nuestra prioridad
                    </h3>
                    <p class="pt-[10px] text-[14px] font-medium">
                        <strong>PROMÁS+</strong> nació en 2024 con el objetivo de ofrecer soluciones financieras accesibles y que realmente hagan una diferencia en tu vida. Nos caracteriza nuestro enfoque humano y tasas competitivas, diseñadas para brindarte tranquilidad en momentos de necesidad.
                    </p>
                </div>

                <div class="flex flex-col gap-2 pt-[30px] md:w-[400px]">
                    <div class="flex items-center gap-3">
                        <img
                            src="Fotos/vision.png"
                            alt="vision"
                            class="w-[30px]"
                        />
                        <h3 class="text-[16px] font-bold">
                            Visión clara, futuro brillante
                        </h3>
                    </div>
                    <div>
                        <p class="text-[14px]">
                            Aspiramos a ser líderes en el mercado financiero, ofreciendo soluciones responsables que promuevan la estabilidad económica de nuestros clientes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-[200px] flex flex-col items-center">
        <div class="mx-auto md:w-[468px]">
            <h2 class="text-center text-[40px] font-bold">
                Estrategias financieras para un futuro seguro
            </h2>
            <p class="mt-[10px] text-center text-[14px] font-medium">
                En <strong>PROMÁS+</strong>, diseñamos soluciones personalizadas para resolver tus necesidades financieras con rapidez, transparencia y confianza.
            </p>
        </div>

        <div class="w-full md:w-3/4">
            <img src="Fotos/servicios.webp" alt="logo" class="mx-auto py-6" />
        </div>

        <!-- <div
            class="mt-[50px] grid grid-cols-1 gap-[30px] md:grid-cols-2 lg:grid-cols-3"
        >
            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/prestamos-personales.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Préstamos Personales
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Soluciones rápidas y accesibles para cubrir tus necesidades
                    inmediatas, sin complicaciones.
                </p>
            </div>

            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/prestamos-garantia.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Préstamos con Garantía
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Obtén el financiamiento que necesitas utilizando tus bienes
                    como respaldo, con tasas competitivas.
                </p>
            </div>

            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/flexible.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Planes de Pago Flexibles
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Diseñamos planes adaptados a tu capacidad de pago para que
                    logres estabilidad financiera.
                </p>
            </div>

            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/asesoria-financiera.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Asesoría Financiera
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Recibe orientación personalizada para tomar decisiones
                    informadas y mejorar tu salud financiera.
                </p>
            </div>

            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/atencion.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Atención Personalizada
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Nuestro equipo está siempre disponible para resolver tus
                    dudas y ofrecerte el mejor servicio.
                </p>
            </div>

            <div
                class="flex w-[330px] flex-col justify-center rounded-[3px] border border-[#E9E9E9] px-[40px] py-[35px]"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="Fotos/confianza.png"
                        class="mx-auto h-[38px] w-[38px]"
                    />
                    <h3 class="w-[174px] text-[16px] font-bold">
                        Seguridad y Confianza
                    </h3>
                </div>
                <p class="pt-[10px] text-[14px] font-medium">
                    Garantizamos transparencia y privacidad en cada transacción
                    para tu tranquilidad.
                </p>
            </div>
        </div> -->
    </div>

    <Faq></Faq>

    <ContactForm></ContactForm>

    <div class="h-[60vh] bg-[#FAFAFA] sm:h-[40vh] lg:h-[50vh]"></div>

    <Footer></Footer>
</template>
