<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
    documentTypes: Array,
});

const toast = useToast();

const documentTypes = ref(props.documentTypes);
const isOpen = ref(false);
const form = useForm({
    id: '',
    name: '',
});

const editDocumentType = (documentType) => {
    form.id = documentType.id;
    form.name = documentType.name;
    isOpen.value = true;
};

const openDialog = () => {
    form.id = '';
    form.name = '';
    isOpen.value = true;
};

const saveDocumentType = () => {
    if (form.id) {
        form.put(route('document-types.update', form.id), {
            onSuccess: (response) => {
                isOpen.value = false;
                form.reset();
                toast.success('Tipo de documento actualizado correctamente');
                documentTypes.value = response.props.documentTypes;
            },
            onError: (error) => {
                toast.error('Error al actualizar el tipo de documento');
            }
        });
    } else {
        form.post(route('document-types.store'), {
            onSuccess: (response) => {
                isOpen.value = false;
                form.reset();
                toast.success('Tipo de documento creado correctamente');
                documentTypes.value = response.props.documentTypes;
            },
            onError: (error) => {
                toast.error('Error al crear el tipo de documento');
            }
        });
    }
};

const updateDocumentType = () => {
    console.log(form);
};

const deleteDocumentType = (documentType) => {
    console.log(documentType);
};
</script>

<template>
    <Head title="Profile" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Tipos de Documentos</CardTitle>
                        <CardDescription>Gestiona los tipos de documentos que se utilizarán en el sistema</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="flex justify-end mb-4">
                            <Button @click="openDialog">Nuevo Tipo de Documento</Button>
                        </div>
                        <Table>
                            <TableCaption>Tipos de Documentos</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>#</TableHead>
                                    <TableHead class="w-[200px]">
                                        Nombre
                                    </TableHead>
                                    <TableHead>Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="documentType in documentTypes" :key="documentType.id">
                                    <TableCell>
                                        {{ documentType.id }}
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ documentType.name }}
                                    </TableCell>
                                    <TableCell>
                                        <Button size="sm" variant="outline" @click="editDocumentType(documentType)">Editar</Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
        <Dialog v-model:open="isOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ form.id ? 'Editar Tipo de Documento' : 'Nuevo Tipo de Documento' }}</DialogTitle>
                    <DialogDescription>
                        {{ form.id ? 'Edita el tipo de documento' : 'Crea un nuevo tipo de documento' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4">
                    <Input type="text" v-model="form.name" placeholder="Nombre del tipo de documento" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="isOpen = false">Cancelar</Button>
                    <Button @click="saveDocumentType">Guardar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>