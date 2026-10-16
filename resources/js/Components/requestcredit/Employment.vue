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
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                            errors.type
                                ? 'border-red-300'
                                : 'border-gray-300',
                        ]"
                    >
                        <option value="">Seleccione una situación laboral</option>
                        <option value="Emprendedor">Emprendedor</option>
                        <option value="Dueño de Negocio">
                            Dueño de Negocio
                        </option>
                        <option value="Empleado formal">Empleado Formal</option>
                        <option value="Freelancer">Freelancer</option>
                        <option value="Empleado informal">
                            Empleado Informal
                        </option>
                        <option value="Jornalero">Jornalero</option>
                    </select>
                    <span v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Área de Trabajo
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.area"
                        @change="validateField('area')"
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                            errors.area
                                ? 'border-red-300'
                                : 'border-gray-300',
                        ]"
                    >
                        <option value="">Seleccione un área</option>
                        <option value="Medicina">Medicina</option>
                        <option value="Negocios">Negocios</option>
                        <option value="Tecnología">Tecnología</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Agricultura">Agricultura</option>
                        <option value="Pesca">Pesca</option>
                        <option value="Apicultura">Apicultura</option>
                        <option value="Ganadería">Ganadería</option>
                        <option value="Albañilería">Albañilería</option>
                    </select>
                    <span v-if="errors.area" class="mt-1 text-sm text-red-600">{{ errors.area }}</span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Actividad Principal
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.main_activity"
                        @change="validateField('main_activity')"
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                            errors.main_activity
                                ? 'border-red-300'
                                : 'border-gray-300',
                        ]"
                    >
                        <option value="">Seleccione una actividad</option>
                        <option value="Agua">Agua</option>
                        <option value="Armas y productos militares">
                            Armas y productos militares
                        </option>
                        <option value="Automotriz / autopartes">
                            Automotriz / autopartes
                        </option>
                        <option value="Cómputo y electrónica">
                            Cómputo y electrónica
                        </option>
                        <option value="Comunicaciones">Comunicaciones</option>
                        <option value="Cosméticos">Cosméticos</option>
                        <option value="Cuero y calzado">Cuero y calzado</option>
                        <option value="Electricidad">Electricidad</option>
                        <option value="Electrodomésticos">
                            Electrodomésticos
                        </option>
                        <option value="Farmacéutico">Farmacéutico</option>
                        <option value="Gas">Gas</option>
                        <option value="Joyas">Joyas</option>
                        <option value="Juguetes">Juguetes</option>
                        <option value="Maquinaria y equipo">
                            Maquinaria y equipo
                        </option>
                        <option value="Metalúrgica">Metalúrgica</option>
                        <option value="Minería">Minería</option>
                        <option value="Muebles">Muebles</option>
                        <option value="Piedras y metales preciosos">
                            Piedras y metales preciosos
                        </option>
                        <option value="Tabaco">Tabaco</option>
                        <option value="Vehículos de transporte aéreos">
                            Vehículos de transporte aéreos
                        </option>
                        <option value="Vehículos de transporte marítimos">
                            Vehículos de transporte marítimos
                        </option>
                        <option value="Vehículos de transporte terrestres">
                            Vehículos de transporte terrestres
                        </option>
                    </select>
                    <span v-if="errors.main_activity" class="mt-1 text-sm text-red-600">{{ errors.main_activity }}</span>
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
                    <select
                        v-model="form.imss"
                        id=""
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                        ]"
                    >
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
                    <select
                        v-model="form.insurance_major_medical_expenses"
                        id=""
                        :class="[
                            'mt-1 block w-full rounded-md border-gray-300 shadow-sm',
                            'focus:border-blue-500 focus:ring-blue-500',
                        ]"
                    >
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

        const validateField = (field) => {
            errors.value[field] = '';

            switch (field) {
                case 'type':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'area':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'main_activity':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'company':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'job':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'seniority_working':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'phone':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'imss':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'insurance_major_medical_expenses':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'monthly_income':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
                case 'other_income':
                    if (!form.value[field]) {
                        errors.value[field] = 'Este campo es requerido';
                    }
                    break;
            }
        };

        const validateForm = () => {
            const requiredFields = ['type', 'area', 'main_activity', 'company', 'job', 'seniority_working', 'phone', 'imss', 'insurance_major_medical_expenses', 'monthly_income', 'other_income'];
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
            form,
            errors,
            handleSubmit,
            back,
        };
    },
};
</script>
