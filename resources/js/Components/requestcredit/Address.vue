<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Información domicilio</h2>

        <form @submit.prevent="saveAndContinue" class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
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
                    :error="errors.outdoor_number"
                    @blur="validateField('outdoor_number')"
                />
                <FormInput
                    v-model="form.suburb"
                    label="Colonia"
                    required
                    :error="errors.suburb"
                    @blur="validateField('suburb')"
                />
                <FormInput
                    v-model="form.postal_code"
                    label="Código postal"
                    required
                    :error="errors.postal_code"
                    @blur="validateField('postal_code')"
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
import { onMounted, ref, watch } from 'vue';
import FormInput from '../FormInput.vue';
import { useRequestCreditStore } from '../../stores/requestCredit';

export default {
    props: {
        formData: {
            type: Object,
            required: true,
        },
    },

    components: { FormInput },

    emits: ['update-form', 'save-step'],

    setup(props, { emit }) {
        const requestCreditStore = useRequestCreditStore();
        const form = ref({ ...requestCreditStore.addressInfo });
        
        const errors = ref({});

        // Cargar datos existentes si los hay
        onMounted(() => {
            if (requestCreditStore.addressInfo) {
                form.value = { ...requestCreditStore.addressInfo };
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                requestCreditStore.updateAddressInfo(newValue);
            },
            { deep: true },
        );

        const validateField = (field) => {
            errors.value[field] = '';

            switch (field) {
                case 'street':
                case 'outdoor_number':
                case 'suburb':
                case 'postal_code':
                case 'years_living':
                case 'type':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
            }
        };

        const validateForm = () => {
            const requiredFields = ['street', 'outdoor_number', 'suburb', 'postal_code', 'years_living', 'type'];
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
            form,
            errors,
            handleSubmit,
            back,
        };
    },
};
</script>
