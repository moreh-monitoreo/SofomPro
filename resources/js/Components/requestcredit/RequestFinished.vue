<template>
    <div class="space-y-6">
        <h2 class="text-center text-lg font-medium text-gray-900">
            Solicitud Finalizada
        </h2>

        <h3 class="text-center text-lg font-medium text-gray-900">
            ¡Gracias! Vamos a procesar su solicitud y en breve tendrá más
            noticias
        </h3>
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

    components: {},

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
                emit('update-form', newValue, 'personal');
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
