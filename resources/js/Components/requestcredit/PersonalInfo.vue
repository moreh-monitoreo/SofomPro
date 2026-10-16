<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Información Personal</h2>

        <form class="space-y-4">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Nombre
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.name
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('name')"
                    />
                    <span v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Segundo Nombre
                    </label>
                    <input
                        v-model="form.second_name"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.second_name
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Primer Apellido
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.last_name"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.last_name
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('last_name')"
                    />
                    <span v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Segundo Apellido
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.maiden_name"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.maiden_name
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('maiden_name')"
                    />
                    <span v-if="errors.maiden_name" class="mt-1 text-sm text-red-600">{{ errors.maiden_name }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Genero
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.gender"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.gender
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('gender')"
                    >
                        <option value="">Seleccione un género</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Masculino">Masculino</option>
                    </select>
                    <span v-if="errors.gender" class="mt-1 text-sm text-red-600">{{ errors.gender }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Fecha de Nacimiento
                        <span class="text-red-500">*</span>
                    </label>
                    <Datepicker
                        v-model="form.birthday"
                        calendar-class="w-full"
                        format="yyyy-MM-dd"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.birthday
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @selected="onSelectedDate"
                    />
                    <span v-if="errors.birthday" class="mt-1 text-sm text-red-600">{{ errors.birthday }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        RFC
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.rfc"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.rfc
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('rfc')"
                    />
                    <span v-if="errors.rfc" class="mt-1 text-sm text-red-600">{{ errors.rfc }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        CURP
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.curp"
                        type="text"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.curp
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('curp')"
                    />
                    <span v-if="errors.curp" class="mt-1 text-sm text-red-600">{{ errors.curp }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Estado
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.state"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.state
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('state')"
                    >
                        <option value="">Seleccione un estado</option>
                        <option value="Aguascalientes">Aguascalientes</option>
                        <option value="Baja California">Baja California</option>
                        <option value="Baja California Sur">Baja California Sur</option>
                        <option value="Campeche">Campeche</option>
                        <option value="Coahuila de Zaragoza">Coahuila de Zaragoza</option>
                        <option value="Colima">Colima</option>
                        <option value="Chiapas">Chiapas</option>
                        <option value="Chihuahua">Chihuahua</option>
                        <option value="Ciudad de México">Ciudad de México</option>
                        <option value="Durango">Durango</option>
                        <option value="Guanajuato">Guanajuato</option>
                        <option value="Guerrero">Guerrero</option>
                        <option value="Hidalgo">Hidalgo</option>
                        <option value="Jalisco">Jalisco</option>
                        <option value="México">México</option>
                        <option value="Michoacán de Ocampo">Michoacán de Ocampo</option>
                        <option value="Morelos">Morelos</option>
                        <option value="Nayarit">Nayarit</option>
                        <option value="Nuevo León">Nuevo León</option>
                        <option value="Oaxaca">Oaxaca</option>
                        <option value="Puebla">Puebla</option>
                        <option value="Querétaro">Querétaro</option>
                        <option value="Quintana Roo">Quintana Roo</option>
                        <option value="San Luis Potosí">San Luis Potosí</option>
                        <option value="Sinaloa">Sinaloa</option>
                        <option value="Sonora">Sonora</option>
                        <option value="Tabasco">Tabasco</option>
                        <option value="Tamaulipas">Tamaulipas</option>
                        <option value="Tlaxcala">Tlaxcala</option>
                        <option value="Veracruz de Ignacio de la Llave">Veracruz de Ignacio de la Llave</option>
                        <option value="Yucatán">Yucatán</option>
                        <option value="Zacatecas">Zacatecas</option>
                    </select>
                    <span v-if="errors.state" class="mt-1 text-sm text-red-600">{{ errors.state }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Correo electrónico
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.email
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('email')"
                    />
                    <span v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Teléfono (10 dígitos)
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        placeholder="6671234567"
                        :class="[
                            'mt-1 block w-full rounded-md shadow-sm transition-colors duration-200',
                            errors.phone
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                        ]"
                        @blur="validateField('phone')"
                    />
                    <span v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</span>
                </div>
            </div>

            <div class="flex justify-end mt-4">
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

<script setup>
import { onMounted, ref, watch } from 'vue';
import Datepicker from 'vuejs3-datepicker';
import { useRequestCreditStore } from '@/stores/requestCredit';

const store = useRequestCreditStore();
const form = ref({ ...store.personalInfo });
const errors = ref({});

// Cargar datos existentes si los hay
onMounted(() => {
    if (store.personalInfo) {
        form.value = { ...store.personalInfo };
    }
});

// Actualizar datos del store cuando cambie el formulario local
watch(
    form,
    (newValue) => {
        store.updatePersonalInfo(newValue);
    },
    { deep: true },
);

const validateField = (field) => {
    errors.value[field] = '';

    switch (field) {
        case 'name':
        case 'last_name':
        case 'maiden_name':
        case 'gender':
        case 'birthday':
        case 'state':
            if (!form.value[field]) {
                errors.value[field] = 'Este campo es requerido';
            }
            break;
        case 'email':
            if (!form.value.email) {
                errors.value.email = 'El correo es requerido';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
                errors.value.email = 'El correo no es válido';
            }
            break;
        case 'phone':
            if (!form.value.phone) {
                errors.value.phone = 'El teléfono es requerido';
            } else if (!/^[0-9]{10}$/.test(form.value.phone)) {
                errors.value.phone = 'El teléfono debe tener 10 dígitos';
            }
            break;
        case 'rfc':
            if (!form.value.rfc) {
                errors.value.rfc = 'El RFC es requerido';
            } else {
                const rfcRegex = /^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
                if (!rfcRegex.test(form.value.rfc)) {
                    errors.value.rfc = 'El RFC no es válido';
                }
            }
            break;
        case 'curp':
            if (!form.value.curp) {
                errors.value.curp = 'La CURP es requerida';
            } else {
                const curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z]{2}$/;
                if (!curpRegex.test(form.value.curp)) {
                    errors.value.curp = 'La CURP no es válida';
                }
            }
            break;
    }
};

const validateForm = () => {
    const requiredFields = ['name', 'last_name', 'maiden_name', 'gender', 'birthday', 'rfc', 'curp', 'state', 'email', 'phone'];
    requiredFields.forEach(field => validateField(field));
    return !Object.values(errors.value).some(error => error !== '');
};

const handleSubmit = async () => {
    form.value.curp = (form.value.curp || '').toUpperCase().trim();
    form.value.rfc = (form.value.rfc || '').toUpperCase().trim();
    if (validateForm()) {
        await store.sendPersonalInfo();
    } else {
        store.setErrors({ submit: 'Revisa los campos marcados en rojo antes de continuar.' });
    }
};

const onSelectedDate = (date) => {
    if (date) {
        // Convertir la fecha a string en formato YYYY-MM-DD sin considerar la zona horaria
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        form.value.birthday = `${year}-${month}-${day}`;
    } else {
        form.value.birthday = null;
    }
    validateField('birthday');
};
</script>

<style scoped>
.transition-colors {
    transition: all 0.2s ease-in-out;
}
</style>
