<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-medium text-gray-900">Documentos</h2>
            <p class="mt-1 text-sm text-gray-600">
                Sube una foto o PDF legible de cada documento. Formatos: PDF, JPG o PNG, máximo 5 MB por archivo.
                Tus documentos se guardan en un almacenamiento seguro y solo los revisa nuestro equipo.
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
            <div
                v-for="doc in documents"
                :key="doc.field"
                :class="[
                    'rounded-lg border p-4 transition-colors',
                    errors[doc.field] ? 'border-red-300 bg-red-50' : 'border-gray-200',
                ]"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ doc.label }}
                            <span class="text-red-500">*</span>
                        </p>
                        <p class="text-xs text-gray-500">{{ doc.hint }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <template v-if="files[doc.field]">
                            <span class="max-w-[220px] truncate text-sm text-gray-700" :title="files[doc.field].name">
                                {{ files[doc.field].name }}
                                <span class="text-xs text-gray-500">({{ size(files[doc.field]) }})</span>
                            </span>
                            <button
                                type="button"
                                class="text-sm text-red-600 hover:underline"
                                @click="remove(doc.field)"
                            >
                                Quitar
                            </button>
                        </template>
                        <label
                            v-else
                            class="cursor-pointer rounded-md border border-vermilion-500 px-3 py-2 text-sm font-medium text-vermilion-500 hover:bg-vermilion-50"
                        >
                            Elegir archivo
                            <input
                                type="file"
                                class="sr-only"
                                accept=".pdf,.jpg,.jpeg,.png,image/jpeg,image/png,application/pdf"
                                @change="pick(doc.field, $event)"
                            />
                        </label>
                    </div>
                </div>
                <p v-if="errors[doc.field]" class="mt-2 text-sm text-red-600">{{ errors[doc.field] }}</p>
            </div>

            <div class="flex justify-between pt-2">
                <button
                    type="button"
                    class="rounded-md bg-gray-500 px-4 py-2 text-white transition-colors duration-200 hover:bg-gray-600"
                    :disabled="store.isSubmitting"
                    @click="store.previousStep()"
                >
                    Anterior
                </button>
                <button
                    type="submit"
                    class="rounded-md bg-vermilion-500 px-4 py-2 text-white transition-colors duration-200 hover:bg-vermilion-600 disabled:cursor-wait disabled:opacity-60"
                    :disabled="store.isSubmitting"
                >
                    {{ store.isSubmitting ? 'Enviando documentos…' : 'Enviar solicitud' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { useRequestCreditStore } from '@/stores/requestCredit';
import { ref } from 'vue';

const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED = ['application/pdf', 'image/jpeg', 'image/png'];

const store = useRequestCreditStore();

const documents = [
    { field: 'ine_front', label: 'INE por el frente', hint: 'Foto clara donde se lea toda la información.' },
    { field: 'ine_back', label: 'INE por el reverso', hint: 'Asegúrate de que se vea el código de barras.' },
    { field: 'proof_of_address', label: 'Comprobante de domicilio', hint: 'Recibo de luz, agua, teléfono o predial con antigüedad máxima de 3 meses.' },
    { field: 'bank_statement', label: 'Estado de cuenta bancario', hint: 'Del último mes, donde aparezca tu nombre.' },
];

const files = ref({});
const errors = ref({});

const size = (file) => `${(file.size / 1024 / 1024).toFixed(2)} MB`;

const pick = (field, event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    errors.value[field] = '';
    if (!ALLOWED.includes(file.type)) {
        errors.value[field] = 'El archivo debe ser PDF, JPG o PNG.';
        return;
    }
    if (file.size > MAX_BYTES) {
        errors.value[field] = `El archivo pesa ${size(file)} y el máximo es 5 MB.`;
        return;
    }
    files.value[field] = file;
};

const remove = (field) => {
    delete files.value[field];
};

const handleSubmit = async () => {
    errors.value = {};
    documents.forEach(({ field, label }) => {
        if (!files.value[field]) {
            errors.value[field] = `Falta subir: ${label}.`;
        }
    });

    if (Object.values(errors.value).some(Boolean)) {
        store.setErrors({ submit: 'Sube los cuatro documentos para enviar tu solicitud.' });
        return;
    }

    await store.sendDocuments(files.value);
};
</script>
