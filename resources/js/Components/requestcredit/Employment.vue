<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Situación Laboral</h2>

        <form class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Situación Laboral
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.type"
                        @change="validateField('type')"
                        :class="selectClass(errors.type)"
                    >
                        <option value="">Seleccione una situación laboral</option>
                        <option value="Emprendedor">Emprendedor</option>
                        <option value="Dueño de Negocio">Dueño de Negocio</option>
                        <option value="Empleado formal">Empleado Formal</option>
                        <option value="Freelancer">Freelancer</option>
                        <option value="Empleado informal">Empleado Informal</option>
                        <option value="Jornalero">Jornalero</option>
                    </select>
                    <span v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Área de Trabajo
                        <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.area" @change="validateField('area')" :class="selectClass(errors.area)">
                        <option value="">Seleccione un área</option>
                        <option v-for="area in catalogs.workAreas" :key="area" :value="area">{{ area }}</option>
                    </select>
                    <span v-if="errors.area" class="mt-1 text-sm text-red-600">{{ errors.area }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Actividad Principal (giro)
                        <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.main_activity" @change="validateField('main_activity')" :class="selectClass(errors.main_activity)">
                        <option value="">Seleccione una actividad</option>
                        <option v-for="activity in catalogs.mainActivities" :key="activity" :value="activity">{{ activity }}</option>
                    </select>
                    <span v-if="errors.main_activity" class="mt-1 text-sm text-red-600">{{ errors.main_activity }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        ¿Tu actividad se relaciona con alguno de estos sectores?
                        <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.pld_activity" @change="validateField('pld_activity')" :class="selectClass(errors.pld_activity)">
                        <option value="">Seleccione una opción</option>
                        <option v-for="activity in catalogs.pldActivities" :key="activity" :value="activity">{{ activity }}</option>
                    </select>
                    <span v-if="errors.pld_activity" class="mt-1 text-sm text-red-600">{{ errors.pld_activity }}</span>
                </div>
                <FormInput
                    v-model="form.company"
                    label="Empresa"
                    required
                    :error="errors.company"
                />
                <FormInput
                    v-model="form.job"
                    label="Puesto"
                    required
                    :error="errors.job"
                />
                <FormInput
                    v-model="form.seniority_working"
                    label="Años de experiencia"
                    type="number"
                    placeholder="Ej. 3"
                    required
                    :error="errors.seniority_working"
                />
                <FormInput
                    v-model="form.phone"
                    label="Teléfono"
                    required
                    :error="errors.phone"
                />
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        ¿Tienes seguro social?
                    </label>
                    <select v-model="form.imss" :class="selectClass()">
                        <option value="">Seleccione una opción</option>
                        <option value="Si">Si</option>
                        <option value="No">No</option>
                    </select>
                    <span v-if="errors.imss" class="mt-1 text-sm text-red-600">{{ errors.imss }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        ¿Tienes seguro de gastos médicos mayores?
                        <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.insurance_major_medical_expenses" :class="selectClass()">
                        <option value="">Seleccione una opción</option>
                        <option value="Si">Si</option>
                        <option value="No">No</option>
                    </select>
                    <span v-if="errors.insurance_major_medical_expenses" class="mt-1 text-sm text-red-600">{{ errors.insurance_major_medical_expenses }}</span>
                </div>
                <FormInput
                    v-model="form.monthly_income"
                    label="Ingreso mensual"
                    required
                    :error="errors.monthly_income"
                    type="number"
                />
                <FormInput
                    v-model="form.monthly_expenses"
                    label="Egresos mensuales"
                    required
                    :error="errors.monthly_expenses"
                    type="number"
                />
                <FormInput
                    v-model="form.other_income"
                    label="Otros ingresos"
                    required
                    :error="errors.other_income"
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
import { usePage } from '@inertiajs/vue3';
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
        // Los catálogos vienen del servidor (config/catalogs.php), que valida contra las mismas listas
        const catalogs = usePage().props.catalogs ?? { workAreas: [], mainActivities: [], pldActivities: [] };
        const form = ref({ ...requestCreditStore.employmentInfo });
        const errors = ref({});

        // Cargar datos existentes si los hay
        onMounted(() => {
            if (requestCreditStore.employmentInfo) {
                form.value = { ...requestCreditStore.employmentInfo };
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                requestCreditStore.updateEmploymentInfo(newValue);
            },
            { deep: true },
        );

        const selectClass = (error) => [
            'mt-1 block w-full rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500',
            error ? 'border-red-300' : 'border-gray-300',
        ];

        const validateField = (field) => {
            errors.value[field] = '';

            switch (field) {
                case 'seniority_working':
                    if (form.value[field] === '' || form.value[field] === null) {
                        errors.value[field] = 'Este campo es requerido';
                    } else if (!/^\d{1,2}$/.test(String(form.value[field]))) {
                        errors.value[field] = 'Captura los años como número entero (0 a 80)';
                    }
                    break;
                case 'monthly_expenses':
                    if (form.value[field] === '' || form.value[field] === null || Number(form.value[field]) < 0) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                default:
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
            }
        };

        const validateForm = () => {
            const requiredFields = ['type', 'area', 'main_activity', 'pld_activity', 'company', 'job', 'seniority_working', 'phone', 'imss', 'insurance_major_medical_expenses', 'monthly_income', 'monthly_expenses', 'other_income'];
            requiredFields.forEach(field => validateField(field));
            return !Object.values(errors.value).some(error => error !== '');
        };

        const handleSubmit = async () => {
            if (validateForm()) {
                await requestCreditStore.sendEmploymentInfo();
            } else {
                requestCreditStore.setErrors({ submit: 'Revisa los campos marcados en rojo antes de continuar.' });
            }
        };

        const back = () => {
            requestCreditStore.previousStep();
        };

        return {
            catalogs,
            form,
            errors,
            selectClass,
            validateField,
            handleSubmit,
            back,
        };
    },
};
</script>
