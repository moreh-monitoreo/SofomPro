<script setup>
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';
</script>
<template>
    <Head title="Quienes Somos" />

    <Header></Header>

    <!-- Portada -->
    <section
        class="relative flex h-[70vh] items-start justify-center bg-cover bg-center"
        style="background-image: url('Fotos/quienes-somos.jpg')"
    >
        <div
            class="absolute inset-0 bg-gradient-to-r from-black to-transparent opacity-75"
        ></div>
        <div class="relative z-10 text-center text-white mt-4">
            <h1 class="text-[40px] font-extrabold sm:text-[58px]">
                Sobre Nosotros
            </h1>
            <p
                class="mt-4 px-6 text-[16px] font-medium sm:w-[520px] sm:text-[16px]"
            >
                Somos una institución financiera nacida en el noroeste de México en 2024, cuyo compromiso es ofrecer soluciones crediticias accesibles con un proceso ágil y sencillo.
            </p>
        </div>
    </section>
    <!-- Misión, Visión y Valores -->
    <section id="mision-vision-valores" class="py-20">
        <div class="flex gap-10">
            <div
                class="mt-10 flex w-full flex-col items-end justify-center px-6 md:w-1/2"
            >
                <div
                    class="mb-[40px] w-full rounded-[15px] bg-primary px-10 px-6 py-10 text-white lg:w-5/6"
                >
                    <h2 class="mb-4 text-[40px] font-bold">Misión</h2>
                    <p class="mb-8 text-[16px] font-medium">
                        Proporcionar a nuestros clientes opciones financieras accesibles y seguras, tanto en préstamos como en fondos de inversión. Nos comprometemos a ofrecer un apoyo genuino en momentos de necesidad, a través de un proceso de tramitación ágil y sencillo, mientras cumplimos con todas las normativas vigentes. Nos esforzamos por ser un socio de confianza para nuestros clientes, brindando soluciones responsables que impulsen su crecimiento financiero y les permitan alcanzar sus metas.
                    </p>
                </div>

                <!-- Visión -->
                <div
                    class="w-full rounded-[15px] border-4 border-primary px-10 px-6 py-10 text-primary lg:w-5/6"
                >
                    <h2 class="mb-4 text-[40px] font-bold">Visión</h2>
                    <p class="mb-8 text-[16px] font-medium">
                        En PROMÁS+ visualizamos un mundo donde todas las personas tengan acceso a soluciones financieras responsables y confiables en momentos de necesidad. Nos esforzamos por ser líderes en el mercado, ofreciendo servicios innovadores que promuevan la estabilidad financiera y el bienestar de nuestros clientes, cumpliendo con los más altos estándares de integridad y transparencia.
                    </p>
                </div>
            </div>

            <div
                class="hidden w-1/2 flex-col items-center justify-center md:flex"
            >
                <img
                    src="Fotos/planet.jpg"
                    alt="planeta"
                    class="mx-auto w-3/4"
                />
            </div>
        </div>
    </section>

    <!-- Valores -->
    <div class="mt-[100px] flex flex-col items-center">
        <div class="mx-auto px-6 min-[500px]:w-[468px]">
            <h2 class="text-center text-[40px] font-bold">Valores</h2>
            <p class="mt-[10px] text-center text-[14px] font-medium">
                En nuestra empresa basamos nuestro trabajo en principios sólidos que garantizan un servicio de calidad y confianza para nuestros clientes.
            </p>
        </div>

        <div
            class="mt-[50px] grid grid-cols-1 gap-[30px] px-6 min-[440px]:px-0 min-[880px]:grid-cols-2"
        >
            <div
                class="flex flex-col justify-center rounded-[10px] border border-[#E9E9E9] bg-primary px-[40px] py-[35px] min-[440px]:w-[400px]"
            >
                <img
                    src="Fotos/confianza_nosotros.png"
                    alt="planeta"
                    class="w-[80px]"
                />
                <h3 class="mt-[30px] text-[26px] font-bold text-white">
                    Confianza
                </h3>
                <p class="text-[14px] text-white">
                    Establecemos relaciones sólidas y duraderas con nuestros clientes, basadas en la honestidad y el respeto mutuo.
                </p>
            </div>

            <div
                class="flex flex-col justify-center rounded-[10px] border border-[#E9E9E9] bg-secondary px-[40px] py-[35px] min-[440px]:w-[400px]"
            >
                <img
                    src="Fotos/transparencia.png"
                    alt="planeta"
                    class="w-[80px]"
                />
                <h3 class="mt-[30px] text-[26px] font-bold text-white">
                    Transparencia
                </h3>
                <p class="text-[14px] text-white">
                    Ofrecemos información clara y precisa en todo momento, para que nuestros clientes puedan tomar decisiones informadas.
                </p>
            </div>

            <div
                class="flex flex-col justify-center rounded-[10px] border border-[#E9E9E9] bg-secondary px-[40px] py-[35px] min-[440px]:w-[400px]"
            >
                <img
                    src="Fotos/innovacion.png"
                    alt="planeta"
                    class="w-[80px]"
                />
                <h3 class="mt-[30px] text-[26px] font-bold text-white">
                    Innovación
                </h3>
                <p class="text-[14px] text-white">
                    Nos esforzamos por implementar soluciones creativas y eficientes que marquen la diferencia en el mercado.
                </p>
            </div>

            <div
                class="flex flex-col justify-center rounded-[10px] border border-[#E9E9E9] bg-primary px-[40px] py-[35px] min-[440px]:w-[400px]"
            >
                <img
                    src="Fotos/responsabilidad.png"
                    alt="planeta"
                    class="w-[80px]"
                />
                <h3 class="mt-[30px] text-[26px] font-bold text-white">
                    Responsabilidad
                </h3>
                <p class="text-[14px] text-white">
                    Asumimos con seriedad nuestro compromiso con nuestros clientes, entregando resultados que cumplen con los más altos estándares.
                </p>
            </div>
        </div>
    </div>

    <!-- Diferenciador -->
    <section id="diferenciador" class="mt-[100px] py-20">
        <div
            class="container mx-auto flex flex-col items-center justify-between md:flex-row"
        >
            <div
                class="mb-6 w-full px-6 text-center md:mb-0 md:w-1/2 md:text-left"
            >
                <h2 class="mb-4 text-[40px] font-bold">Algo único</h2>
                <p
                    class="mb-[30px] w-full text-[16px] font-medium md:mb-[70px] md:w-3/4"
                >
                    Nos diferenciamos por nuestra atención personalizada y un equipo experto que te acompañará en todo el proceso. Nos enorgullece brindar un servicio que inspira confianza y seguridad.
                </p>
                <a
                    href="prestamo-personal.html"
                    class="rounded-full bg-primary px-8 py-4 text-[14px] font-bold text-white"
                    >Conoce nuestros servicios</a
                >
            </div>
            <div class="mt-10 w-full px-6 md:mt-0 md:w-1/2">
                <img
                    src="Fotos/diferenciador.jpg"
                    alt="Nuestro Diferenciador"
                    class="w-full rounded-lg shadow-lg"
                />
            </div>
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
