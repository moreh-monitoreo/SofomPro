<script>
import AddressInfo from '@/Components/requestcredit/Address.vue';
import CivilInfo from '@/Components/requestcredit/CivilInfo.vue';
import Employment from '@/Components/requestcredit/Employment.vue';
import EmploymentDetails from '@/Components/requestcredit/EmploymentDetails.vue';
import Loan from '@/Components/requestcredit/Loan.vue';
import PersonalInfo from '@/Components/requestcredit/PersonalInfo.vue';
import RequestFinished from '@/Components/requestcredit/RequestFinished.vue';
import Footer from '@/Layouts/partials/Footer.vue';
import Header from '@/Layouts/partials/Header.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useRequestCreditStore } from '@/stores/requestCredit';

export default {
    components: {
        Head,
        Header,
        Footer,
        PersonalInfo,
        AddressInfo,
        CivilInfo,
        Employment,
        EmploymentDetails,
        Loan,
        RequestFinished,
    },

    setup() {
        const store = useRequestCreditStore();
        const currentStep = ref(1);
        const formData = ref({
            id: 7,
            personal: {},
            address: {},
            loan: {},
            employment: {},
            documents: {},
            references: {},
            guarantor: {},
            review: {},
        });

        const tabs = [
            { name: 'Información Personal', component: 'PersonalInfo' },
            { name: 'Información Dirección', component: 'AddressInfo' },
            { name: 'Información Civil', component: 'CivilInfo' },
            { name: 'Situación Laboral', component: 'Employment' },
            { name: 'Préstamo', component: 'Loan' },
            { name: 'Solicitud Finilizada', component: 'RequestFinished' },
        ];

        const currentComponent = computed(
            () => tabs[currentStep.value - 1].component,
        );
        const progressPercentage = computed(() =>
            Math.round((currentStep.value / 7) * 100),
        );

        const updateFormData = (stepData, step) => {
            formData.value[step] = stepData;
        };

        const saveStep = async () => {
            try {
                return await axios.post(`${getStepPath(currentStep.value)}`, {
                    customer_id: formData.value.id,
                    ...formData.value[getStepKey(currentStep.value)],
                });
            } catch (error) {
                console.error('Error saving step:', error);
            }
        };

        const getStepKey = (step) => {
            const stepKeys = [
                'personal',
                'address',
                'civil',
                'employment',
                'employmentdetails',
                'loan',
                'finish',
            ];
            return stepKeys[step - 1];
        };

        const getStepPath = (step) => {
            const stepUrls = [
                '/request-credit/customer',
                `/request-credit/customer/${formData.value.id}/address`,
                `/request-credit/customer/${formData.value.id}`,
                `/request-credit/customer/${formData.value.id}/employment`,
                `/request-credit/customer/${formData.value.id}/employment/update`,
                `/request-credit/customer/${formData.value.id}/loan`,
                '',
            ];
            return stepUrls[step - 1];
        };

        const changeTab = (step) => {
            currentStep.value = step;
        };

        const nextStep = async () => {
            const response = await saveStep();
            if (
                (response.status == '200' || response.status == '201') &&
                currentStep.value < 7
            ) {
                currentStep.value++;
            }
            if (response.data.message == 'PROFILE REGISTERED') {
                formData.value.id = response.data.data.id;
            }
        };

        const prevStep = () => {
            if (currentStep.value > 1) {
                currentStep.value--;
            }
        };

        const submitForm = async () => {
            window.location.href = '/prestamo-personal';
        };

        return {
            store,
            currentStep,
            formData,
            tabs,
            currentComponent,
            progressPercentage,
            updateFormData,
            saveStep,
            changeTab,
            nextStep,
            prevStep,
            submitForm,
        };
    },
};
</script>
<template>
    <Head title="Préstamo Personal - Solicitud" />

    <Header></Header>

    <section class="container mx-auto my-16 px-6">
        <div class="flex flex-wrap items-center lg:flex-nowrap">
            <h1>Inicia tu solicitud</h1>
        </div>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="mb-8">
                            <h1 class="text-2xl font-semibold text-gray-900 text-center">Solicitud de Crédito</h1>
                            <div class="mt-4">
                                <div class="flex items-center justify-center">
                                    <div
                                        v-for="step in store.totalSteps"
                                        :key="step"
                                        class="flex items-center"
                                    >
                                        <div
                                            :class="[
                                                'w-8 h-8 rounded-full flex items-center justify-center',
                                                step <= store.currentStep
                                                    ? 'bg-vermilion-500 text-white'
                                                    : 'bg-gray-200 text-gray-600',
                                            ]"
                                        >
                                            {{ step }}
                                        </div>
                                        <div
                                            v-if="step < store.totalSteps"
                                            :class="[
                                                'h-1 w-16',
                                                step < store.currentStep
                                                    ? 'bg-vermilion-500'
                                                    : 'bg-gray-200',
                                            ]"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="store.errors.submit"
                            role="alert"
                            class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            {{ store.errors.submit }}
                        </div>

                        <div v-if="store.currentStep === 1">
                            <PersonalInfo />
                        </div>
                        <div v-else-if="store.currentStep === 2">
                            <AddressInfo />
                        </div>
                        <div v-else-if="store.currentStep === 3">
                            <CivilInfo />
                        </div>
                        <div v-else-if="store.currentStep === 4">
                            <Employment />
                        </div>
                        <div v-else-if="store.currentStep === 5">
                            <Loan />
                        </div>
                        <div v-else-if="store.currentStep === 6">
                            <RequestFinished />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <Footer></Footer>
</template>
