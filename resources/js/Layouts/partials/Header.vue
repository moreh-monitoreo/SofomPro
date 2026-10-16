<template>
    <header>
        <div class="flex items-center gap-x-20">
            <a :href="route('site.index')">
                <img
                    src="/Fotos/Logo.png"
                    alt="logo"
                    class="mx-auto h-[120px] cursor-pointer"
                />
            </a>
        </div>
        <button id="abrir" class="abrir-menu">
            <i class="bi bi-list"></i>
        </button>
        <nav class="nav" id="nav">
            <button class="cerrar-menu" id="cerrar">
                <i class="bi bi-x"></i>
            </button>
            <ul class="nav-list flex space-x-6 text-[13px] md:text-[16px] font-semibold">
                <li>
                    <a :href="route('site.index')" class="hover:text-primary transition"
                        >Inicio</a
                    >
                </li>
                <li>
                    <a
                        :href="route('site.whoami')"
                        class="hover:text-primary transition"
                        >Quienes Somos</a
                    >
                </li>
                <li>
                    <a
                        :href="route('site.personalloan')"
                        class="hover:text-primary transition"
                        >Préstamo Personal</a
                    >
                </li>
                <li>
                    <a
                        :href="route('site.loanguarantee')"
                        class="hover:text-primary transition"
                        >Préstamo Garantía</a
                    >
                </li>
                <li>
                    <a
                        :href="route('site.contact')"
                        class="hover:text-primary transition"
                        >Contáctanos</a
                    >
                </li>
            </ul>
            <a
                :href="route('login')"
                class="hover:text-primary text-[13px] md:text-[16px] font-bold text-white transition md:hidden"
                >Inicia sesión</a
            >
            <a
                :href="route('site.requestcredit')"
                class="bg-primary rounded-[5px] px-7 py-4 text-[14px] font-semibold text-white md:hidden"
            >
                Solicita tu préstamo
            </a>
        </nav>

        <div class="hidden space-x-4 md:block">
            <a
                :href="route('login')"
                class="hover:text-primary text-[13px] font-bold transition"
                >Inicia sesión</a
            >
            <a
                :href="route('site.requestcredit')"
                class="bg-primary rounded-[5px] px-7 py-4 text-[14px] font-semibold text-white"
            >
                Solicita tu préstamo
            </a>
        </div>
    </header>
</template>
