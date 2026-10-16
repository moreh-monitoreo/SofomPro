<script setup>
</script>
<template>
    <div
        class="mt-[150px] flex h-[80vh] w-full flex-col items-center lg:h-[65vh]"
        style="background-image: url('/Fotos/fonto-gris-claro.png')"
    >
        <!-- CTA -->
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
                src="Fotos/contactoform.jpg"
                alt="contacto"
                class="hidden w-2/4 object-cover lg:block lg:h-full"
            />
            <div class="w-full px-10 py-8 lg:w-2/4">
                <h3 class="text-center text-[24px] font-bold">
                    Agenda tu cita
                </h3>
                <form action="#" method="POST" class="mt-8 space-y-4">
                    <!-- Campo de Nombre -->
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

                    <!-- Campo de Correo -->
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

                    <!-- Campo de Mensaje -->
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

                    <!-- Botón de Envío -->
                    <div class="text-center">
                        <button
                            type="submit"
                            class="mt-4 w-full rounded-[5px] bg-primary px-7 py-4 text-[14px] font-semibold text-white"
                        >
                            Agenda tu cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
