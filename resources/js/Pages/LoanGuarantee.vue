<script setup>
import ContactForm from '@/Layouts/partials/ContactForm.vue';
import Faq from '@/Layouts/partials/Faq.vue';
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';
</script>
<template>
    <Head title="Préstamo con Garantía" />

    <Header></Header>

    <!-- Sección de Portada -->
    <section
        class="relative flex h-[80vh] items-center justify-center bg-cover bg-center"
        style="background-image: url('Fotos/prestamo-garantia-hero.jpg')"
    >
        <div
            class="absolute inset-0 bg-gradient-to-r from-black to-transparent"
        ></div>
        <div class="relative z-10 text-center text-white">
            <h1 class="text-[40px] font-extrabold sm:text-[58px]">
                Préstamo con garantía
            </h1>
            <p class="mt-4 px-6 text-[14px] font-medium sm:text-[16px]">
                Accede a un mayor monto utilizando tus bienes como respaldo.
            </p>
            <a
                href="#steps"
                class="mt-8 inline-block rounded-full bg-primary px-8 py-4 text-[14px] font-bold text-white"
                >Solicita ahora</a
            >
        </div>
    </section>

    <!-- Beneficios del Préstamo -->
    <section class="bg-primary py-8 text-white">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 gap-12 px-6 md:grid-cols-3">
                <div class="flex flex-col items-center text-center">
                    <img
                        src="Fotos/bajo-interes.png"
                        alt="Bajo Interés"
                        class="mb-6 w-[60px]"
                    />
                    <h3 class="text-[20px] font-bold">Intereses bajos</h3>
                </div>
                <div class="flex flex-col items-center text-center">
                    <img
                        src="Fotos/aprobacion-rapida.png"
                        alt="Aprobación Rápida"
                        class="mb-6 w-[60px]"
                    />
                    <h3 class="text-[20px] font-bold">Aprobación rápida</h3>
                </div>
                <div class="flex flex-col items-center text-center">
                    <img
                        src="Fotos/flexibilidad.png"
                        alt="Términos Flexibles"
                        class="mb-6 w-[60px]"
                    />
                    <h3 class="text-[20px] font-bold">Términos flexibles</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Descripción del Préstamo Garantia -->
    <section class="container mx-auto my-16 px-6">
        <div class="flex flex-wrap items-center lg:flex-nowrap">
            <!-- Texto -->
            <div class="mb-8 w-full sm:pr-6 lg:mb-0 lg:w-1/2 lg:pr-12">
                <h2 class="text-[30px] font-bold leading-tight sm:text-[40px]">
                    Préstamos con garantía: seguridad y confianza
                </h2>
                <p class="mt-4 text-[18px] leading-relaxed text-gray-700">
                    Con nuestro préstamo con garantía, utiliza tus bienes como respaldo para obtener los recursos que necesitas, mientras disfruta de tasas competitivas y plazos diseñados para adaptarse a tus necesidades.
                </p>
                <div class="mt-6 space-y-2 text-[16px] text-gray-600 sm:ml-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-[40px] w-[40px] items-center justify-center rounded-lg bg-[#5E646B] text-[20px] font-extrabold text-white sm:h-[70px] sm:w-[70px] sm:text-[50px]"
                        >
                            1
                        </div>
                        <p
                            class="w-3/4 text-[16px] text-gray-700 sm:text-[20px]"
                        >
                            Tasas de interés más bajas gracias al respaldo de tu
                            garantía.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-[40px] w-[40px] items-center justify-center rounded-lg bg-[#5E646B] text-[20px] font-extrabold text-white sm:h-[70px] sm:w-[70px] sm:text-[50px]"
                        >
                            2
                        </div>
                        <p
                            class="w-3/4 text-[16px] text-gray-700 sm:text-[20px]"
                        >
                            Acceso a montos más altos para cubrir tus
                            necesidades.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-[40px] w-[40px] items-center justify-center rounded-lg bg-[#5E646B] text-[20px] font-extrabold text-white sm:h-[70px] sm:w-[70px] sm:text-[50px]"
                        >
                            3
                        </div>
                        <p
                            class="w-3/4 text-[16px] text-gray-700 sm:text-[20px]"
                        >
                            Proceso ágil y sencillo para liberar tu préstamo
                            rápidamente.
                        </p>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-1/2">
                <img
                    src="Fotos/prestamo-garantia.jpg"
                    alt="Préstamo Personal"
                    class="rounded-lg"
                />
            </div>
        </div>
    </section>

    <!-- Pasos para obtener un préstamo con garantía -->

    <section id="steps" class="py-16">
        <div class="container mx-auto">
            <h2 class="mb-8 text-center text-[40px] font-bold">
                ¿Cómo funciona?
            </h2>
            <div class="w-full">
                <picture>
                    <source
                        media="(min-width: 1025px)"
                        srcset="Fotos/prestamo-garantia-pasos.webp"
                    />
                    <img
                        src="Fotos/prestamo-garantia-pasos-movil.webp"
                        alt="Responsive image"
                    />
                </picture>
            </div>
            <!-- <div
                class="grid grid-cols-1 gap-12 px-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    class="rounded-lg bg-primary px-8 pb-16 pt-8 text-white shadow-lg"
                >
                    <p class="mb-12 mt-4 text-[25px] font-bold">01</p>
                    <h3 class="text-[24px] font-bold">Presenta tu garantía</h3>
                    <p class="mt-4 text-[16px]">
                        Proporciona la documentación de tus bienes para iniciar
                        el proceso de evaluación.
                    </p>
                </div>
                <div class="rounded-lg bg-primary p-8 text-white shadow-lg">
                    <p class="mb-12 mt-4 text-[25px] font-bold">02</p>
                    <h3 class="text-[24px] font-bold">Evaluación de bienes</h3>
                    <p class="mt-4 text-[16px]">
                        Realizamos una evaluación para determinar el valor de
                        tus bienes y el monto del préstamo.
                    </p>
                </div>
                <div class="rounded-lg bg-primary p-8 text-white shadow-lg">
                    <p class="mb-12 mt-4 text-[25px] font-bold">03</p>
                    <h3 class="text-[24px] font-bold">
                        Firma y recibe tu dinero
                    </h3>
                    <p class="mt-4 text-[16px]">
                        Una vez aprobado, firmas el contrato y el dinero será
                        depositado en tu cuenta.
                    </p>
                </div>
            </div> -->
        </div>
    </section>

    <Faq></Faq>

    <ContactForm></ContactForm>

    <div class="h-[60vh] bg-[#FAFAFA] sm:h-[40vh] lg:h-[50vh]"></div>

    <Footer></Footer>
</template>
