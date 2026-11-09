<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Información domicilio</h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <FormInput
                    v-model="form.postal_code"
                    label="Código postal"
                    required
                    placeholder="5 dígitos"
                    :error="errors.postal_code"
                    @blur="validateField('postal_code')"
                />
                <p class="self-end pb-2 text-sm text-gray-500">
                    <template v-if="lookup.loading">Buscando tu código postal…</template>
                    <template v-else-if="lookup.found">Encontramos {{ lookup.settlements.length }} colonia(s) para este código.</template>
                    <template v-else-if="lookup.notFound">No encontramos este código; escribe tu colonia, municipio y estado.</template>
                    <template v-else>Escribe tu código postal y llenamos municipio y estado.</template>
                </p>

                <div v-if="lookup.found && !manualSuburb">
                    <label class="block text-sm font-medium text-gray-700">
                        Colonia
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.suburb"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.suburb ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @change="onSuburbSelected"
                    >
                        <option value="">Seleccione su colonia</option>
                        <option v-for="settlement in lookup.settlements" :key="settlement" :value="settlement">{{ settlement }}</option>
                        <option :value="OTHER">Mi colonia no aparece</option>
                    </select>
                    <span v-if="errors.suburb" class="mt-1 text-sm text-red-600">{{ errors.suburb }}</span>
                </div>
                <FormInput
                    v-else
                    v-model="form.suburb"
                    label="Colonia"
                    required
                    :error="errors.suburb"
                    @blur="validateField('suburb')"
                />

                <FormInput
                    v-model="form.municipality"
                    label="Municipio o alcaldía"
                    required
                    :disabled="lookup.found"
                    :error="errors.municipality"
                    @blur="validateField('municipality')"
                />
                <FormInput
                    v-model="form.state"
                    label="Estado"
                    required
                    :disabled="lookup.found"
                    :error="errors.state"
                    @blur="validateField('state')"
                />
                <FormInput
                    v-model="form.street"
                    label="Calle"
                    required
                    :error="errors.street"
                    @blur="validateField('street')"
                />
                <FormInput
                    v-model="form.outdoor_number"
                    label="Número exterior"
                    required
                    placeholder="Ej. 123, 12-A, S/N"
                    :error="errors.outdoor_number"
                    @blur="validateField('outdoor_number')"
                />
                <FormInput
                    v-model="form.interior_number"
                    label="Número interior (depto.)"
                    placeholder="Ej. 4, B-2"
                />
                <FormInput
                    v-model="form.years_living"
                    label="Número de años viviendo en la dirección"
                    required
                    type="number"
                    :error="errors.years_living"
                    @blur="validateField('years_living')"
                />
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Tipo de vivienda
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.type"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.type
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('type')"
                    >
                        <option value="">Seleccione un tipo de vivienda</option>
                        <option value="Propietario">Propietario</option>
                        <option value="Arrendatario">Arrendatario</option>
                        <option value="Familiar">Familiar</option>
                        <option value="Amigo">Amigo</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <span v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</span>
                </div>
            </div>

            <div class="flex justify-between mt-4">
                <button
                    type="button"
                    class="rounded-md bg-gray-500 px-4 py-2 text-white hover:bg-gray-600 transition-colors duration-200"
                    @click="back"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    class="rounded-md bg-vermilion-500 px-4 py-2 text-white hover:bg-vermilion-600 transition-colors duration-200"
                    @click="handleSubmit"
                >
                    Siguiente
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import axios from 'axios';
import { onMounted, reactive, ref, watch } from 'vue';
import FormInput from '../FormInput.vue';
import { useRequestCreditStore } from '../../stores/requestCredit';

const OTHER = '__other__';

export default {
    props: {
        formData: {
            type: Object,
            required: true,
        },
    },

    components: { FormInput },

    emits: ['update-form', 'save-step'],

    setup() {
        const requestCreditStore = useRequestCreditStore();
        const form = ref({ ...requestCreditStore.addressInfo });
        const errors = ref({});
        const lookup = reactive({ loading: false, found: false, notFound: false, settlements: [] });
        const manualSuburb = ref(false);

        // Catálogo de SEPOMEX: con el código postal se llenan municipio y estado, y la colonia se elige de la lista
        const searchPostalCode = async (code) => {
            Object.assign(lookup, { loading: true, found: false, notFound: false, settlements: [] });
            try {
                const { data } = await axios.get(route('postal-codes.show', code));
                if (form.value.postal_code !== code) return;
                Object.assign(lookup, { found: true, settlements: data.data.settlements });
                form.value.municipality = data.data.municipality;
                form.value.state = data.data.state;
                manualSuburb.value = Boolean(form.value.suburb) && !data.data.settlements.includes(form.value.suburb);
            } catch {
                if (form.value.postal_code === code) lookup.notFound = true;
            } finally {
                lookup.loading = false;
            }
        };

        onMounted(() => {
            if (requestCreditStore.addressInfo) {
                form.value = { ...requestCreditStore.addressInfo };
            }
            if (/^\d{5}$/.test(form.value.postal_code || '')) searchPostalCode(form.value.postal_code);
        });

        watch(
            () => form.value.postal_code,
            (code, previous) => {
                if (code === previous) return;
                if (/^\d{5}$/.test(code || '')) {
                    if (previous && /^\d{5}$/.test(previous)) form.value.suburb = '';
                    searchPostalCode(code);
                } else {
                    Object.assign(lookup, { found: false, notFound: false, settlements: [] });
                }
            },
        );

        watch(
            form,
            (newValue) => {
                requestCreditStore.updateAddressInfo(newValue);
            },
            { deep: true },
        );

        const onSuburbSelected = () => {
            if (form.value.suburb === OTHER) {
                form.value.suburb = '';
                manualSuburb.value = true;
            }
            validateField('suburb');
        };

        const validateField = (field) => {
            errors.value[field] = '';

            if (field === 'postal_code' && !/^\d{5}$/.test(form.value.postal_code || '')) {
                errors.value.postal_code = 'El código postal debe tener 5 dígitos';
                return;
            }
            if (!form.value[field]) {
                errors.value[field] = 'Este campo es requerido';
            }
        };

        const validateForm = () => {
            const requiredFields = ['postal_code', 'suburb', 'municipality', 'state', 'street', 'outdoor_number', 'years_living', 'type'];
            requiredFields.forEach(field => validateField(field));
            return !Object.values(errors.value).some(error => error !== '');
        };

        const handleSubmit = async () => {
            if (validateForm()) {
                await requestCreditStore.sendAddress();
            } else {
                requestCreditStore.setErrors({ submit: 'Revisa los campos marcados en rojo antes de continuar.' });
            }
        };

        const back = () => {
            requestCreditStore.previousStep();
        };

        return {
            OTHER,
            form,
            errors,
            lookup,
            manualSuburb,
            onSuburbSelected,
            validateField,
            handleSubmit,
            back,
        };
    },
};
</script>
