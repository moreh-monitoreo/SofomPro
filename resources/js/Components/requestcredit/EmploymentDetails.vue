<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Detalles Laborales</h2>

        <form @submit.prevent="saveAndContinue" class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <FormInput
                    v-model="form.company"
                    label="Nombre de la Empresa"
                    required
                    :error="errors.company"
                />
                <FormInput
                    v-model="form.job"
                    label="Puesto de Trabajo"
                    :error="errors.job"
                />
                <FormInput
                    v-model="form.seniority_working"
                    label="Antigüedad"
                    required
                    :error="errors.seniority_working"
                />
                <FormInput
                    v-model="form.phone"
                    label="Télefono"
                    required
                    :error="errors.phone"
                />
                <FormInput
                    v-model="form.imss"
                    label="¿Cuentas con seguro médico?"
                    required
                    :error="errors.imss"
                />
                <FormInput
                    v-model="form.insurance_major_medical_expenses"
                    label="¿Tienes seguro de gastos médicos?"
                    required
                    :error="errors.insurance_major_medical_expenses"
                />
                <FormInput
                    v-model="form.monthly_income"
                    label="¿Cual es tu ingreso mensual?"
                    required
                    :error="errors.monthly_income"
                />
                <FormInput
                    v-model="form.other_income"
                    label="¿Tienes otros ingresos? Ingresa el monto en su defecto 0"
                    required
                    :error="errors.other_income"
                />
            </div>
        </form>
    </div>
</template>

<script>
import { onMounted, ref, watch } from 'vue';
import FormInput from '../FormInput.vue';

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
        const form = ref({
            company: '',
            job: '',
            seniority_working: '',
            phone: '',
            suburimssb: '',
            insurance_major_medical_expenses: '',
            monthly_income: '',
            other_income: '',
        });
        const errors = ref({});

        // Cargar datos existentes si los hay
        onMounted(() => {
            if (props.formData.employmentdetails) {
                form.value = { ...props.formData.employmentdetails };
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                emit('update-form', newValue, 'employmentdetails');
            },
            { deep: true },
        );

        const validate = () => {
            errors.value = {};

            if (!form.value.street) {
                errors.value.street = 'La calle es requerido';
            }
            if (!form.value.outdoor_number) {
                errors.value.outdoor_number = 'El número exterior es requerido';
            }
            if (!form.value.suburb) {
                errors.value.suburb = 'La colonia es requerida';
            }
            if (!form.value.postal_code) {
                errors.value.postal_code = 'El código postal es requerido';
            }
            if (!form.value.years_living) {
                errors.value.years_living = 'El número de años es requerido';
            }
            if (!form.value.type) {
                errors.value.type = 'El tipo de vivienda es requerido';
            }

            return Object.keys(errors.value).length === 0;
        };

        const saveAndContinue = () => {
            if (validate()) {
                emit('save-step');
            }
        };

        return {
            form,
            errors,
            saveAndContinue,
        };
    },
};
</script>
