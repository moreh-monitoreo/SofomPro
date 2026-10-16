<template>
    <div class="space-y-6">
        <h2 class="text-lg font-medium text-gray-900">Información Personal</h2>

        <form @submit.prevent="saveAndContinue" class="space-y-4">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label
                        for="firstName"
                        class="block text-sm font-medium text-gray-700"
                    >
                        Nombres
                    </label>
                    <input
                        id="firstName"
                        v-model="form.firstName"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <p
                        v-if="errors.firstName"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.firstName }}
                    </p>
                </div>

                <div>
                    <label
                        for="lastName"
                        class="block text-sm font-medium text-gray-700"
                    >
                        Apellidos
                    </label>
                    <input
                        id="lastName"
                        v-model="form.lastName"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <p v-if="errors.lastName" class="mt-1 text-sm text-red-600">
                        {{ errors.lastName }}
                    </p>
                </div>

                <div>
                    <label
                        for="email"
                        class="block text-sm font-medium text-gray-700"
                    >
                        Correo Electrónico
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">
                        {{ errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        for="phone"
                        class="block text-sm font-medium text-gray-700"
                    >
                        Teléfono
                    </label>
                    <input
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <p v-if="errors.phone" class="mt-1 text-sm text-red-600">
                        {{ errors.phone }}
                    </p>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
import { onMounted, ref, watch } from 'vue';

export default {
    props: {
        formData: {
            type: Object,
            required: true,
        },
    },

    emits: ['update-form', 'save-step'],

    setup(props, { emit }) {
        const form = ref({
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
        });
        const errors = ref({});

        // Cargar datos existentes si los hay
        onMounted(() => {
            if (props.formData.personal) {
                form.value = { ...props.formData.personal };
            }
        });

        // Actualizar datos del formulario principal cuando cambie el formulario local
        watch(
            form,
            (newValue) => {
                emit('update-form', newValue, 'documents');
            },
            { deep: true },
        );

        const validate = () => {
            errors.value = {};

            if (!form.value.firstName) {
                errors.value.firstName = 'El nombre es requerido';
            }
            if (!form.value.lastName) {
                errors.value.lastName = 'El apellido es requerido';
            }
            if (!form.value.email) {
                errors.value.email = 'El correo es requerido';
            }
            if (!form.value.phone) {
                errors.value.phone = 'El teléfono es requerido';
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
