<script setup>
import { ref, onMounted } from 'vue';
import FileUpload from './file-upload.vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    error: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

const documents = ref(props.modelValue || []);
const documentTypes = ref([]);

const addDocument = () => {
    documents.value.push({
        type: '',
        name: '',
        file: null
    });
};

const removeDocument = (index) => {
    documents.value.splice(index, 1);
    emit('update:modelValue', documents.value);
};

const updateDocument = (index, field, value) => {
    documents.value[index][field] = value;
    if (field === 'name') {
        documents.value[index].nameFromFile = false;
    }
    emit('update:modelValue', documents.value);
};

const handleFileUpload = (index, files) => {
    if (files.length > 0) {
        const doc = documents.value[index];
        doc.file = files[0];
        if (!doc.name?.trim() || doc.nameFromFile) {
            doc.name = files[0].name.replace(/\.[^.]+$/, '');
            doc.nameFromFile = true;
        }
        emit('update:modelValue', documents.value);
    }
};

onMounted(() => {
    axios.get(route('document-types.index')).then(response => {
        documentTypes.value = response.data;
    }).catch(error => {
        console.error('Error al obtener los tipos de documentos:', error);
    });
});
</script>

<template>
    <div class="space-y-4">
        <div v-for="(doc, index) in documents" :key="index" class="rounded-lg border p-4">
            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-900">Tipo de Documento</label>
                    <select
                        v-model="doc.type"
                        @change="updateDocument(index, 'type', $event.target.value)"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Selecciona un tipo</option>
                        <option v-for="type in documentTypes" :key="type.id" :value="type.name">
                            {{ type.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-900">Nombre del Documento</label>
                    <input
                        type="text"
                        v-model="doc.name"
                        @input="updateDocument(index, 'name', $event.target.value)"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Ej: INE Frontal"
                    />
                </div>
            </div>
            <FileUpload
                :model-value="doc.file ? [doc.file] : []"
                @update:model-value="(files) => handleFileUpload(index, files)"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                :multiple="false"
                label="Arrastra y suelta el documento aquí"
            />
            <button
                type="button"
                @click="removeDocument(index)"
                class="mt-2 text-sm text-red-500 hover:text-red-700"
            >
                Eliminar documento
            </button>
        </div>

        <button
            type="button"
            @click="addDocument"
            class="inline-flex items-center rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300"
        >
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Agregar Documento
        </button>
    </div>
</template> 