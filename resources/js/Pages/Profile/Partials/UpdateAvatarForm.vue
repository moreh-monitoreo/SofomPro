<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FileUpload from '@/Components/ui/file-upload.vue';

defineProps({
    avatar: String,
});

const form = useForm({
    avatar: null,
});

const files = computed({
    get: () => form.avatar ? [form.avatar] : [],
    set: (value) => {
        form.avatar = value[0] || null;
    }
});

const submit = () => {
    if (form.avatar) {
        form.post(route('profile.update-avatar'), {
            onSuccess: () => {
                form.reset('avatar');
            },
        });
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Actualizar Avatar
            </h2>
        </header>
        <form @submit.prevent="submit">
            <div class="mt-6 flex items-center gap-4">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-50">
                    <img :src="avatar" alt="Avatar" class="w-full h-full object-cover rounded-full" />
                </div>
                <div class="flex-1">
                    <FileUpload
                        v-model="files"
                        accept="image/*"
                        :multiple="false"
                        label="Arrastra y suelta tu avatar aquí o haz clic para seleccionar"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4 mt-4">
                <PrimaryButton 
                    :disabled="form.processing || !form.avatar" 
                    type="submit"
                >
                    Actualizar
                </PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

