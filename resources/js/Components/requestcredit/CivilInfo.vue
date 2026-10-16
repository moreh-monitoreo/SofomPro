<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Información adicional</h2>

        <form class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Estado Civil
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.civil_status"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.civil_status
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('civil_status')"
                    >
                        <option value="">Seleccione un estado civil</option>
                        <option value="Soltero">Soltero</option>
                        <option value="Casado">Casado</option>
                        <option value="Divorciado">Divorciado</option>
                        <option value="Viudo">Viudo</option>
                        <option value="Unión Libre">Unión Libre</option>
                        <option value="Concubinato">Concubinato</option>
                    </select>
                    <span v-if="errors.civil_status" class="mt-1 text-sm text-red-600">{{ errors.civil_status }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Nivel de Estudios
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.level_study"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.level_study
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('level_study')"
                    >
                        <option value="">Seleccione un nivel de estudios</option>
                        <option value="Primaria">Primaria</option>
                        <option value="Secundaria">Secundaria</option>
                        <option value="Preparatoria">Preparatoria</option>
                        <option value="Universidad">Universidad</option>
                        <option value="Postgrado">Postgrado</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <span v-if="errors.level_study" class="mt-1 text-sm text-red-600">{{ errors.level_study }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        ¿Tienes auto propio?
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.own_car"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.own_car
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('own_car')"
                    >
                        <option value="">¿Tienes auto propio?</option>
                        <option value="Si">Si</option>
                        <option value="No">No</option>
                    </select>
                    <span v-if="errors.own_car" class="mt-1 text-sm text-red-600">{{ errors.own_car }}</span>
                </div>
                <FormInput
                    v-model="form.economic_dependents"
                    label="Número de dependientes economicos"
                    required
                    :error="errors.economic_dependents"
                    type="number"
                />
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
import { useRequestCreditStore } from '@/stores/requestCredit';

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
        const form = ref({ ...requestCreditStore.civilInfo });
        const errors = ref({});

        // Cargar datos existentes si los hay
        onMounted(() => {
            if (requestCreditStore.civilInfo) {
                form.value = { ...requestCreditStore.civilInfo };
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                requestCreditStore.updateCivilInfo(newValue);
            },
            { deep: true },
        );

        const validateField = (field) => {
            errors.value[field] = '';

            switch (field) {
                case 'civil_status':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'level_study':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'own_car':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'economic_dependents':
                    if (form.value[field] === '' || form.value[field] === null) {
                        errors.value[field] = 'Este campo es requerido y solo se aceptan números';
                    }
                    if (form.value[field] < 0) {
                        errors.value[field] = 'El número de dependientes económicos no puede ser negativo';
                    }
                    if (isNaN(form.value[field])) {
                        errors.value[field] = 'El número de dependientes económicos debe ser un número';
                    }
                    break;
            }
        };

        const validateForm = () => {
            const requiredFields = ['civil_status', 'level_study', 'own_car', 'economic_dependents'];
            requiredFields.forEach(field => validateField(field));
            return !Object.values(errors.value).some(error => error !== '');
        };

        const handleSubmit = async () => {
            if (validateForm()) {
                await requestCreditStore.sendCivilInfo();
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
