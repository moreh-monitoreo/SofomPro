<template>
    <footer class="flex flex-col items-center bg-[#FAFAFA] py-4 text-[#2D3133]">
        <div
            class="flex w-3/4 flex-col items-center justify-between border-b-[1px] border-[#E6E6E6] py-[40px] sm:flex-row"
        >
            <a :href="route('site.index')">
                <img
                    src="/Fotos/Logo.png"
                    alt="logo"
                    class="mx-auto h-[120px] cursor-pointer"
                />
            </a>
            <div class="flex gap-4">
                <img
                    src="/Fotos/facebook.png"
                    alt="logo"
                    class="h-[24px] w-[24px] cursor-pointer"
                />
                <img
                    src="/Fotos/instagram.png"
                    alt="logo"
                    class="h-[24px] w-[24px] cursor-pointer"
                />
                <img
                    src="/Fotos/twitter.png"
                    alt="logo"
                    class="h-[24px] w-[24px] cursor-pointer"
                />
            </div>
        </div>

        <div
            class="flex w-3/4 flex-col gap-[60px] py-[40px] text-center sm:flex-row sm:text-left"
        >
            <div class="flex flex-col gap-4">
                <p class="text-[16px] font-bold">Navegacion</p>
                <a :href="route('site.index')" class="text-[14px]">Inicio</a>
                <a :href="route('site.whoami')" class="text-[14px]"
                    >Quienes Somos</a
                >
                <a :href="route('site.contact')" class="text-[14px]">Contactanos</a>
            </div>

            <div class="flex flex-col gap-4">
                <p class="text-[16px] font-bold">Navegacion</p>
                <a :href="route('site.personalloan')" class="text-[14px]"
                    >Prestamos Personales</a
                >
                <a :href="route('site.loanguarantee')" class="text-[14px]"
                    >Prestamos con Garantia</a
                >
            </div>

            <div class="flex flex-col gap-4">
                <p class="text-[16px] font-bold">Legal</p>
                <a :href="route('site.privacy')" class="text-[14px]"
                    >Aviso de Privacidad</a
                >
            </div>
        </div>

        <p
            class="mt-[50px] px-6 text-center text-[10px] font-bold text-[#2D3133]"
        >
            Copyright © 2026 PROMÁS+. Todos los derechos reservados.
        </p>
    </footer>
</template>
