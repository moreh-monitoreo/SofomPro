<script setup>
import axios from 'axios';
import { defineEmits, defineProps, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { useForm, useField } from 'vee-validate';
import * as yup from 'yup';
import FormInput from '../FormInput.vue';

const emit = defineEmits(['paymentsCreated']);
const props = defineProps({
    loan: Object,
});
const toast = useToast();

const loan = ref(props.loan || {});

// Esquema de validación con Yup
const schema = yup.object({
    amount: yup
        .number()
        .typeError('El monto debe ser un número')
        .required('El monto es obligatorio')
        .positive('El monto debe ser positivo')
        .min(1000, 'El monto mínimo es $1,000'),
    months: yup
        .number()
        .typeError('El plazo debe ser un número')
        .required('El plazo en meses es obligatorio')
        .integer('El plazo debe ser en meses completos')
        .min(1, 'El plazo mínimo es 1 mes')
        .max(360, 'El plazo máximo es 360 meses'),
    opening_fees: yup
        .number()
        .typeError('La comisión debe ser un número')
        .required('La comisión por apertura es obligatoria')
        .min(0, 'La comisión no puede ser negativa')
        .max(100, 'La comisión máxima es 100%'),
    annual_rate: yup
        .number()
        .typeError('La tasa anual debe ser un número')
        .required('La tasa anual es obligatoria')
        .min(0, 'La tasa no puede ser negativa')
        .max(100, 'La tasa máxima es 100%'),
});

// Usar useForm de vee-validate
const { handleSubmit, errors } = useForm({
    validationSchema: schema,
    initialValues: {
        loan_id: props.loan.id,
        amount: props.loan.amount || '',
        // Precargado con lo solicitado y las condiciones del contrato (74.28% con garantía, 79.56% sin garantía)
        months: props.loan.term || '',
        opening_fees: 3,
        annual_rate: props.loan.type === 'FUNERARIO' ? 74.28 : 79.56,
        willSave: false,
    },
});

// Campos individuales con validación
const { value: amount } = useField('amount');
const { value: months } = useField('months');
const { value: opening_fees } = useField('opening_fees');
const { value: annual_rate } = useField('annual_rate');
const { value: willSave } = useField('willSave');

const isSubmitting = ref(false);

// save=false solo calcula la tabla; save=true la guarda. Los campos nunca se limpian
// para poder generar y después guardar con los mismos datos.
const submit = (save) =>
    handleSubmit(async (values) => {
        try {
            isSubmitting.value = true;
            const response = await axios.post(`/loans/${loan.value.id}/payments`, {
                ...values,
                loan_id: loan.value.id,
                willSave: save,
            });

            emit('paymentsCreated', response.data.result);
            toast.success(save ? 'Plan de pagos guardado correctamente' : 'Plan de pagos generado');
        } catch (error) {
            const data = error.response?.data;
            toast.error(
                data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || 'Error al crear la tabla de pagos',
            );
        } finally {
            isSubmitting.value = false;
        }
    })();

const onSubmit = () => submit(false);
function generateAndSave() {
    submit(true);
}
</script>

<template>
    <div class="flex flex-wrap justify-start gap-4">
        <div class="flex w-full flex-wrap gap-4">
            <div class="w-full">
                <h4>Generar plan de pagos para el cliente</h4>
            </div>
            <div class="flex-1">
                <label
                    for="customer"
                    class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"
                    >Cliente</label
                >
                <FormInput
                    type="text"
                    name="customer"
                    id="customer"
                    placeholder="name"
                    :model-value="
                        loan.customer.name +
                        ' ' +
                        loan.customer.second_name +
                        ' ' +
                        loan.customer.last_name +
                        ' ' +
                        loan.customer.maiden_name
                    "
                    disabled
                ></FormInput>
            </div>
            <div class="flex-1">
                <label
                    for="amount"
                    class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"
                    >Monto <span class="text-red-500">*</span></label
                >
                <FormInput
                    type="number"
                    name="amount"
                    id="amount"
                    v-model="amount"
                    :error="errors.amount"
                    placeholder="Ingresa el monto del préstamo"
                />
            </div>
        </div>
        <div class="w-full flex flex-wrap gap-4">
            <div class="flex-1">
                <label
                    for="months"
                    class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"
                    >Plazo (meses) <span class="text-red-500">*</span></label
                >
                <FormInput
                    type="number"
                    name="months"
                    id="months"
                    placeholder="Ingresa la cantidad de meses para el pago del préstamo"
                    v-model="months"
                    :error="errors.months"
                />
            </div>
            <div class="flex-1">
                <label
                    for="opening_fees"
                    class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"
                    >Comisión por apertura (%) <span class="text-red-500">*</span></label
                >
                <FormInput
                    type="number"
                    name="opening_fees"
                    id="opening_fees"
                    placeholder="Ingresa la comisión por apertura"
                    v-model="opening_fees"
                    :error="errors.opening_fees"
                    step="0.01"
                />
            </div>
            <div class="flex-1">
                <label
                    for="annual_rate"
                    class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"
                    >Tasa anual (%) <span class="text-red-500">*</span></label
                >
                <FormInput
                    type="number"
                    name="annual_rate"
                    id="annual_rate"
                    placeholder="Ingresa la tasa anual"
                    v-model="annual_rate"
                    :error="errors.annual_rate"
                    step="0.01"
                />
            </div>
        </div>
    </div>
    <div class="w-full mt-4 flex justify-between mb-4">
        <button
            type="button"
            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-75"
            :disabled="isSubmitting"
            @click="onSubmit"
        >
            Generar plan
        </button>
        <button
            type="button"
            class="rounded-lg bg-green-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-green-800 focus:outline-none focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-75"
            :disabled="isSubmitting"
            @click="generateAndSave"
        >
            Guardar plan
        </button>
    </div>
</template>
