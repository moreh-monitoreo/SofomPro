<script setup>
import { ref, computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    accept: {
        type: String,
        default: '*/*'
    },
    multiple: {
        type: Boolean,
        default: false
    },
    maxSize: {
        type: Number,
        default: 5242880 // 5MB
    },
    label: {
        type: String,
        default: 'Arrastra y suelta archivos aquí o haz clic para seleccionar'
    },
    error: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'error']);

const isDragging = ref(false);
const fileInput = ref(null);

const files = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const handleDragOver = (e) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = (e) => {
    e.preventDefault();
    isDragging.value = false;
    const droppedFiles = Array.from(e.dataTransfer.files);
    handleFiles(droppedFiles);
};

const handleFileSelect = (e) => {
    const selectedFiles = Array.from(e.target.files);
    handleFiles(selectedFiles);
};

const handleFiles = (newFiles) => {
    const validFiles = newFiles.filter(file => {
        if (file.size > props.maxSize) {
            emit('error', `El archivo ${file.name} excede el tamaño máximo permitido`);
            return false;
        }
        return true;
    });

    if (props.multiple) {
        files.value = [...files.value, ...validFiles];
    } else {
        files.value = validFiles;
    }
};

const removeFile = (index) => {
    files.value = files.value.filter((_, i) => i !== index);
};

const triggerFileInput = () => {
    fileInput.value.click();
};
</script>

<template>
    <div class="w-full">
        <div
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="triggerFileInput"
            :class="[
                'relative flex min-h-[150px] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition-colors',
                isDragging ? 'border-primary bg-primary/10' : 'border-gray-300 hover:border-primary/50',
                error ? 'border-red-500' : ''
            ]"
        >
            <input
                ref="fileInput"
                type="file"
                :accept="accept"
                :multiple="multiple"
                class="hidden"
                @change="handleFileSelect"
            />
            <div class="flex flex-col items-center justify-center text-center">
                <svg
                    class="mb-3 h-10 w-10 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                    ></path>
                </svg>
                <p class="mb-2 text-sm text-gray-500">
                    <span class="font-semibold">{{ label }}</span>
                </p>
                <p class="text-xs text-gray-500">PDF, DOC, DOCX, JPG, PNG (MAX. 5MB)</p>
            </div>
        </div>

        <!-- File List -->
        <div v-if="files.length > 0" class="mt-4 space-y-2">
            <div v-for="(file, index) in files" :key="index" class="flex items-center justify-between rounded-lg border p-3">
                <div class="flex items-center space-x-3">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ file.name }}</p>
                        <p class="text-xs text-gray-500">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</p>
                    </div>
                </div>
                <button
                    @click.stop="removeFile(index)"
                    class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Error Message -->
        <p v-if="error" class="mt-2 text-sm text-red-500">{{ error }}</p>
    </div>
</template> 