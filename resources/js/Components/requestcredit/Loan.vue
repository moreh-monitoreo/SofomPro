<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Préstamo</h2>

        <form class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <p>¿Cuenta con póliza de gastos funerarios?</p>
                <Switch
                    :model-value="hasPolicy"
                    @update:model-value="setPolicy"
                />
                <FormInput
                    v-if="hasPolicy"
                    v-model="form.policy_folio"
                    label="Folio del Plan Funerario"
                    required
                    placeholder="Ej. PF-000123"
                    :error="errors.policy_folio"
                />
            </div>
            <hr v-if="form.type === 'FUNERARIO'" />
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <FormInput
                    v-model="form.amount"
                    label="Monto del préstamo"
                    required
                    :error="errors.amount"
                    :help-text="amountHint"
                    type="number"
                    @focusout="validateField('amount')"
                />
                <FormInput
                    v-model="form.term"
                    label="Plazo en meses para pagar"
                    required
                    :error="errors.term"
                    :help-text="termHint"
                    type="number"
                    @focusout="validateField('term')"
                />
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Propósito del préstamo
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.purpose"
                        @change="validateField('purpose')"
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                            errors.purpose
                                ? 'border-red-300'
                                : 'border-gray-300',
                        ]"
                    >
                        <option value="">Seleccione un propósito</option>
                        <option value="Inversión en Negocio">
                            Inversión en Negocio
                        </option>
                        <option value="Consolidación">Consolidación</option>
                        <option value="Compra de maquinaria para negocio">
                            Compra de maquinaria para negocio
                        </option>
                        <option value="Colegiaturas">Colegiaturas</option>
                        <option value="Reparación vehículo">
                            Reparación vehículo
                        </option>
                        <option value="Emergéncia médica">
                            Emergéncia médica
                        </option>
                        <option value="Otras emergencias">
                            Otras emergencias
                        </option>
                        <option value="Compras para el hogar">
                            Compras para el hogar
                        </option>
                        <option value="Vacaciones">Vacaciones</option>
                        <option value="Regalos">Regalos</option>
                    </select>
                    <span v-if="errors.purpose" class="mt-1 text-sm text-red-600">{{ errors.purpose }}</span>
                </div>
                <FormInput
                    v-model="form.purpose_description"
                    label="Agrega más detalles del propósito del préstamo"
                    required
                    :error="errors.purpose_description"
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
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import FormInput from '../FormInput.vue';
import { Switch } from '@/Components/ui/switch';
import { useRequestCreditStore } from '@/stores/requestCredit';

export default {
    props: {
        formData: {
            type: Object,
            required: true,
        },
    },

    components: { FormInput, Switch },

    emits: ['update-form', 'save-step'],

    setup(props, { emit }) {
        const requestCreditStore = useRequestCreditStore();
        const form = ref({ ...requestCreditStore.loanInfo });
        const errors = ref({});

        // Límites vigentes de cada producto (los mismos que usa el simulador); el servidor vuelve a validarlos
        const products = ref({});
        const money = (value) => `$${Number(value).toLocaleString('es-MX')}`;

        // Cargar datos existentes si los hay
        onMounted(async () => {
            if (requestCreditStore.loanInfo) {
                form.value = { ...requestCreditStore.loanInfo };
            }
            try {
                const { data } = await axios.get('/api/simulator/config');
                products.value = data.products;
            } catch {
                // Sin la configuración solo se omiten los avisos; el servidor valida igual
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                requestCreditStore.updateLoanInfo(newValue);
            },
            { deep: true },
        );

        const hasPolicy = computed(() => form.value.type === 'FUNERARIO');
        const setPolicy = (checked) => {
            form.value.type = checked ? 'FUNERARIO' : 'NORMAL';
            if (!checked) {
                form.value.policy_folio = '';
                errors.value.policy_folio = '';
            }
        };

        const limits = computed(() => products.value[hasPolicy.value ? 'FUNERARIO' : 'NORMAL'] ?? null);

        const amountHint = computed(() => {
            const p = limits.value;
            return p ? `Entre ${money(p.min_amount)} y ${money(p.max_amount)}, en múltiplos de ${money(p.amount_step)}.` : '';
        });
        const termHint = computed(() => {
            const p = limits.value;
            return p ? `Entre ${p.min_term} y ${p.max_term} meses.` : '';
        });

        const validateField = (field) => {
            errors.value[field] = '';

            switch (field) {
                case 'policy_folio':
                    if (hasPolicy.value && !String(form.value.policy_folio || '').trim()) {
                        errors.value[field] = 'El folio del Plan Funerario es requerido';
                    }
                    break;
                case 'amount': {
                    const amount = Number(form.value.amount);
                    const p = limits.value;
                    if (!form.value.amount || amount <= 0) {
                        errors.value[field] = 'Ingresa un monto mayor a 0';
                    } else if (p && amount < p.min_amount) {
                        errors.value[field] = `El monto mínimo es de ${money(p.min_amount)}`;
                    } else if (p && amount > p.max_amount) {
                        errors.value[field] = `El monto máximo es de ${money(p.max_amount)}`;
                    } else if (p && (amount - p.min_amount) % p.amount_step !== 0) {
                        errors.value[field] = `El monto debe ser múltiplo de ${money(p.amount_step)}`;
                    }
                    break;
                }
                case 'term': {
                    const term = Number(form.value.term);
                    const p = limits.value;
                    if (!form.value.term || term < 1) {
                        errors.value[field] = 'Ingresa un plazo de al menos 1 mes';
                    } else if (p && (term < p.min_term || term > p.max_term)) {
                        errors.value[field] = `El plazo debe estar entre ${p.min_term} y ${p.max_term} meses`;
                    } else if (!Number.isInteger(term)) {
                        errors.value[field] = 'El plazo debe ser en meses completos';
                    }
                    break;
                }
                case 'purpose':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'purpose_description':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
            }
        };

        const validateForm = () => {
            const requiredFields = ['policy_folio', 'amount', 'term', 'purpose', 'purpose_description'];
            requiredFields.forEach(field => validateField(field));
            return !Object.values(errors.value).some(error => error !== '');
        };

        const handleSubmit = async () => {
            if (validateForm()) {
                await requestCreditStore.sendLoanInfo();
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
            hasPolicy,
            amountHint,
            termHint,
            setPolicy,
            validateField,
            handleSubmit,
            back,
        };
    },
};
</script>
