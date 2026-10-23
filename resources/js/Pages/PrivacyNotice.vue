<script setup>
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
});

const responsable = computed(() => props.company.legal_name || props.company.name);
const contactEmail = computed(() => props.company.privacy_email || props.company.email);
</script>

<template>
    <Head title="Aviso de Privacidad" />

    <Header></Header>

    <main class="container mx-auto max-w-4xl px-6 py-16">
        <h1 class="text-[32px] font-extrabold md:text-[44px]">Aviso de Privacidad Integral</h1>
        <p class="mt-2 text-sm text-gray-500">Versión {{ company.consent_version }}</p>

        <div class="mt-10 space-y-10 text-[15px] leading-relaxed text-gray-700">
            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">1. Responsable del tratamiento de sus datos</h2>
                <p>
                    <strong>{{ responsable }}</strong>
                    <template v-if="company.rfc">, con RFC {{ company.rfc }},</template>
                    <template v-if="company.address"> con domicilio en {{ company.address }},</template>
                    es responsable del tratamiento de sus datos personales, de conformidad con la Ley Federal de
                    Protección de Datos Personales en Posesión de los Particulares (en adelante, la "Ley").
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">2. Datos personales que recabamos</h2>
                <ul class="list-disc space-y-1 pl-6">
                    <li><strong>Identificación y contacto:</strong> nombre completo, fecha de nacimiento, género, CURP, RFC, domicilio, teléfono y correo electrónico.</li>
                    <li><strong>Situación civil y socioeconómica:</strong> estado civil, nivel de estudios, dependientes económicos y tipo de vivienda.</li>
                    <li><strong>Laborales y financieros:</strong> ocupación, antigüedad laboral, ingresos y egresos mensuales, seguridad social y estados de cuenta bancarios.</li>
                    <li><strong>Documentos:</strong> identificación oficial (INE por ambos lados), comprobante de domicilio y estado de cuenta bancario.</li>
                    <li><strong>Del crédito:</strong> monto, plazo y propósito solicitados, historial de pagos con nosotros y, en su caso, el folio del plan funerario ofrecido como garantía.</li>
                    <li><strong>De navegación:</strong> dirección IP y tipo de navegador, al aceptar este aviso y al usar el sitio.</li>
                </ul>
                <p class="mt-3">
                    No solicitamos datos personales sensibles. Si usted nos los proporciona voluntariamente (por ejemplo, al describir
                    el propósito de su crédito), los trataremos únicamente para evaluar su solicitud.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">3. Finalidades del tratamiento</h2>
                <p class="mb-2 font-semibold text-gray-900">Finalidades necesarias para la relación con usted:</p>
                <ul class="list-disc space-y-1 pl-6">
                    <li>Verificar su identidad y evaluar su solicitud de crédito.</li>
                    <li>Integrar su expediente digital y formalizar el contrato y sus anexos.</li>
                    <li>Consultar su historial crediticio en Sociedades de Información Crediticia.</li>
                    <li>Administrar su crédito, procesar pagos, enviar recibos, recordatorios y realizar gestiones de cobranza.</li>
                    <li>Cumplir obligaciones legales, regulatorias y de prevención de operaciones con recursos de procedencia ilícita.</li>
                    <li>Atender aclaraciones, quejas y solicitudes de contacto o cita.</li>
                </ul>
                <p class="mb-2 mt-4 font-semibold text-gray-900">Finalidades adicionales (puede oponerse en cualquier momento):</p>
                <ul class="list-disc space-y-1 pl-6">
                    <li>Enviarle información sobre promociones y nuevos productos.</li>
                    <li>Realizar encuestas de calidad y análisis estadísticos.</li>
                </ul>
                <p class="mt-3">
                    Si no desea que sus datos se traten para las finalidades adicionales, escríbanos al correo indicado en la sección 6;
                    esto no afectará la atención de su solicitud.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">4. Consulta en Sociedades de Información Crediticia</h2>
                <p>
                    Al aceptar este aviso y marcar la casilla correspondiente en su solicitud, usted autoriza expresamente a
                    {{ responsable }} para que consulte y, en su caso, proporcione información sobre su historial crediticio a las
                    Sociedades de Información Crediticia (Buró de Crédito y Círculo de Crédito), conforme a la Ley para Regular las
                    Sociedades de Información Crediticia. Esta autorización permanece vigente durante la relación jurídica y por tres
                    años posteriores a la consulta, y puede revocarla por escrito sin efectos retroactivos.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">5. Transferencias y encargados</h2>
                <p>Sus datos pueden compartirse, sin requerir su consentimiento adicional cuando la Ley lo permite, con:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>Sociedades de Información Crediticia.</li>
                    <li>Autoridades competentes, cuando exista un requerimiento legal.</li>
                    <li>Proveedores que nos prestan servicios y tratan datos por nuestra cuenta: almacenamiento seguro de documentos en la nube (Google Cloud), procesamiento de pagos con tarjeta (Stripe) y envío de correo electrónico.</li>
                </ul>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">6. Derechos ARCO, revocación y limitación de uso</h2>
                <p>
                    Usted puede ejercer sus derechos de Acceso, Rectificación, Cancelación y Oposición (ARCO), revocar su consentimiento o
                    limitar el uso de sus datos enviando una solicitud
                    <template v-if="contactEmail">
                        al correo <a :href="`mailto:${contactEmail}`" class="font-semibold text-primary underline">{{ contactEmail }}</a>
                    </template>
                    <template v-else>a nuestro domicilio</template>
                    con su nombre completo, una copia de su identificación oficial, la descripción clara del derecho que desea ejercer y
                    los datos a los que se refiere. Responderemos en un plazo máximo de 20 días hábiles.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">7. Seguridad</h2>
                <p>
                    Aplicamos medidas administrativas, técnicas y físicas para proteger sus datos: conexión cifrada, almacenamiento
                    privado de documentos con enlaces temporales, control de acceso por roles y registro de consentimientos.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-xl font-bold text-gray-900">8. Cambios a este aviso</h2>
                <p>
                    Cualquier modificación se publicará en esta página. La versión vigente es la indicada al inicio de este documento.
                </p>
            </section>

            <div class="rounded-lg bg-gray-50 p-5 text-sm">
                <p>
                    ¿Listo para continuar?
                    <a :href="route('site.requestcredit')" class="font-semibold text-primary underline">Regresar a mi solicitud</a>
                </p>
            </div>
        </div>
    </main>

    <Footer></Footer>
</template>
