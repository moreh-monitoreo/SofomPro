<script setup>
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';
</script>
<template>
    <Head title="Contáctanos" />

    <Header></Header>
    <!-- Portada -->
    <section
        class="relative flex h-[60vh] items-center justify-center bg-cover bg-center"
        style="background-image: url('Fotos/contacto.jpg')"
    >
        <div
            class="absolute inset-0 bg-gradient-to-r from-black to-transparent opacity-75"
        ></div>
        <div class="relative z-10 text-center text-white">
            <h1 class="text-[40px] font-extrabold sm:text-[58px]">
                Contáctanos
            </h1>
            <p class="mt-4 px-6 text-[14px] font-medium sm:text-[16px]">
                Estamos aquí para ayudarte.
            </p>
        </div>
    </section>

    <!-- Información de contacto -->
    <section class="container mx-auto my-16">
        <div class="grid grid-cols-1 gap-12 px-6 min-[800px]:grid-cols-2">
            <div>
                <h2 class="mb-6 text-[40px] font-bold">Escríbenos</h2>
                <form action="#" method="POST" class="mt-8 space-y-4">
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-[14px] font-semibold"
                            >Nombre *</label
                        >
                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Ingresa tu nombre"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-[14px] font-semibold"
                            >Correo *</label
                        >
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Ingresa tu correo"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label
                            for="message"
                            class="mb-2 block text-[14px] font-semibold"
                            >Mensaje</label
                        >
                        <textarea
                            id="message"
                            name="message"
                            rows="5"
                            placeholder="Escribe tu mensaje"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div class="">
                        <button
                            type="submit"
                            class="mt-4 rounded-[5px] bg-primary px-7 py-4 text-[14px] font-semibold text-white"
                        >
                            Enviar
                        </button>
                    </div>
                </form>
            </div>
            <div>
                <h2 class="mb-6 text-[40px] font-bold">
                    Nuestra ubicación
                </h2>
                <p class="mb-4 text-[16px]">
                    Si tienes alguna pregunta o necesitas asistencia, no dudes
                    en comunicarte con nosotros.
                </p>
                <ul class="space-y-4 text-[16px]">
                    <li>
                        <strong>Dirección:</strong> Av. Ejemplo 123, Ciudad,
                        País
                    </li>
                    <li><strong>Teléfono:</strong> +123 456 7890</li>
                    <li>
                        <strong>Correo Electrónico:</strong>
                        contacto@empresa.com
                    </li>
                </ul>
                <div class="mt-8">
                    <h3 class="mb-4 text-[24px] font-bold">
                        Horario de Atención
                    </h3>
                    <ul class="space-y-2 text-[16px]">
                        <li>
                            <strong>Lunes a Viernes:</strong> 9:00 AM - 6:00 PM
                        </li>
                        <li><strong>Sábados:</strong> 9:00 AM - 2:00 PM</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Mapa -->
    <section class="container mx-auto my-16 px-6">
        <h2 class="mb-8 text-center text-[40px] font-bold">
            Nuestra ubicación
        </h2>
        <div class="h-[450px] w-full overflow-hidden rounded-lg bg-gray-200">
            <!-- Aquí puedes insertar un iframe de Google Maps o un mapa estático -->
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3168.871306364922!2d-122.0842496843703!3d37.42199977982357!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x808fb5d9c9442c59%3A0x4ddaa65bfa751799!2sGoogleplex!5e0!3m2!1sen!2sus!4v1623375845362!5m2!1sen!2sus"
                width="100%"
                height="100%"
                style="border: 0"
                allowfullscreen=""
                loading="lazy"
            ></iframe>
        </div>
    </section>

    <!-- CTA -->
    <section
        class="bg-primary py-16 text-center text-white"
        style="background-image: url('Fotos/fondo-naranja.png')"
    >
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

    <Footer></Footer>
</template>
