<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Object, required: true },
});

const toast = useToast();

const roleLabel = (role) => props.roles[role]?.label ?? role ?? 'Sin rol';
const when = (iso) => (iso ? new Date(iso).toLocaleDateString('es-MX', { timeZone: 'America/Mexico_City' }) : '—');

const firstError = (errors) => Object.values(errors)[0] || 'No se pudo completar la acción.';

const createForm = useForm({ name: '', email: '', role: 'Advisor' });
const create = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Usuario creado. Le enviamos la invitación a ${createForm.email}.`);
            createForm.reset();
        },
        onError: (errors) => {
            // La cuenta sí se creó; solo falló el correo
            if (errors.invitation) {
                toast.error(errors.invitation, { timeout: 10000 });
                createForm.reset();
                return;
            }
            toast.error('Revisa los campos marcados en rojo.');
        },
    });
};

// Edición en línea de nombre y rol
const editing = ref(null);
const editForm = useForm({ name: '', role: '' });
const startEdit = (user) => {
    editing.value = user.id;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.role = user.role;
};
const saveEdit = (user) => {
    editForm.put(route('admin.users.update', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            toast.success('Usuario actualizado.');
        },
        onError: (errors) => toast.error(firstError(errors)),
    });
};

const confirmAndPost = async (routeName, user, { title, text, confirm, success }) => {
    const result = await Swal.fire({ title, text, icon: 'question', showCancelButton: true, cancelButtonText: 'Cancelar', confirmButtonText: confirm });
    if (!result.isConfirmed) return;

    router.post(route(routeName, user.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(success),
        onError: (errors) => toast.error(firstError(errors)),
    });
};

const deactivate = (user) =>
    confirmAndPost('admin.users.deactivate', user, {
        title: `¿Desactivar a ${user.name}?`,
        text: 'No podrá entrar al sistema y se cerrarán sus sesiones abiertas. Su historial en la bitácora se conserva.',
        confirm: 'Sí, desactivar',
        success: `${user.name} ya no puede entrar.`,
    });

const reactivate = (user) =>
    confirmAndPost('admin.users.reactivate', user, {
        title: `¿Reactivar a ${user.name}?`,
        text: 'Podrá volver a entrar con su contraseña y su verificación en dos pasos.',
        confirm: 'Sí, reactivar',
        success: `${user.name} puede volver a entrar.`,
    });

const resetTwoFactor = (user) =>
    confirmAndPost('admin.users.two-factor.reset', user, {
        title: '¿Restablecer la verificación en dos pasos?',
        text: `Úsalo si ${user.name} perdió su teléfono. Al entrar tendrá que escanear un código QR nuevo.`,
        confirm: 'Sí, restablecer',
        success: 'Listo. Deberá configurarla de nuevo al entrar.',
    });

const invite = (user) =>
    confirmAndPost('admin.users.invite', user, {
        title: `¿Enviar invitación a ${user.email}?`,
        text: 'Le llegará un enlace para crear (o cambiar) su contraseña. Los enlaces anteriores dejan de funcionar.',
        confirm: 'Enviar',
        success: `Invitación enviada a ${user.email}.`,
    });
</script>

<template>
    <Head title="Usuarios" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 pt-0">
            <div class="mt-5">
                <h1 class="text-2xl font-bold text-gray-900">Usuarios del equipo</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Da de alta al personal y asigna su rol. Cada persona recibe un correo para crear su contraseña y, al entrar, activa la verificación en dos pasos.
                    Las cuentas de clientes se crean solas al aprobar su crédito.
                </p>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <h2 class="text-base font-semibold text-gray-900">Agregar usuario</h2>
                <form class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4 md:items-start" @submit.prevent="create">
                    <div>
                        <label for="new-name" class="mb-1 block text-sm font-medium text-gray-700">Nombre completo</label>
                        <input id="new-name" v-model="createForm.name" type="text" required class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                        <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="new-email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                        <input id="new-email" v-model="createForm.email" type="email" required class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                        <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</p>
                    </div>
                    <div>
                        <label for="new-role" class="mb-1 block text-sm font-medium text-gray-700">Rol</label>
                        <select id="new-role" v-model="createForm.role" class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm">
                            <option v-for="(role, key) in roles" :key="key" :value="key">{{ role.label }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">{{ roles[createForm.role]?.description }}</p>
                    </div>
                    <div class="md:pt-6">
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                        >
                            Crear y enviar invitación
                        </button>
                    </div>
                </form>
            </section>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Verificación en dos pasos</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Alta</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" :class="['border-t border-gray-100 align-top', !user.active && 'bg-gray-50 text-gray-500']">
                            <td class="px-4 py-3">
                                <input v-if="editing === user.id" v-model="editForm.name" type="text" class="w-full rounded-lg border border-gray-300 p-2 text-sm" />
                                <template v-else>
                                    <span class="font-medium text-gray-900">{{ user.name }}</span>
                                    <span v-if="user.is_me" class="ml-1 text-xs text-gray-500">(tú)</span>
                                </template>
                                <span class="block text-xs text-gray-500">{{ user.email }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="editing === user.id">
                                    <select v-model="editForm.role" :disabled="user.is_me" class="rounded-lg border border-gray-300 p-2 text-sm">
                                        <option v-for="(role, key) in roles" :key="key" :value="key">{{ role.label }}</option>
                                    </select>
                                    <p v-if="editForm.errors.role" class="mt-1 text-xs text-red-600">{{ editForm.errors.role }}</p>
                                </template>
                                <span v-else>{{ roleLabel(user.role) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="user.two_factor" class="text-green-700">✓ Activa</span>
                                <span v-else class="text-amber-700">⚠ Pendiente</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="user.active">Activo</span>
                                <span v-else>Desactivado el {{ when(user.deactivated_at) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ when(user.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <template v-if="editing === user.id">
                                        <button type="button" class="rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800" :disabled="editForm.processing" @click="saveEdit(user)">Guardar</button>
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100" @click="editing = null">Cancelar</button>
                                    </template>
                                    <template v-else-if="user.active">
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100" @click="startEdit(user)">Editar</button>
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100" @click="invite(user)">Reenviar invitación</button>
                                        <button v-if="user.two_factor" type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100" @click="resetTwoFactor(user)">Restablecer 2 pasos</button>
                                        <button v-if="!user.is_me" type="button" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50" @click="deactivate(user)">Desactivar</button>
                                    </template>
                                    <button v-else type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100" @click="reactivate(user)">Reactivar</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </DashboardLayout>
</template>
