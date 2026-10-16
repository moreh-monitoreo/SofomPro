import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useRequestCreditStore = defineStore('requestCredit', () => {

    const customer = ref({});
    // Estado del formulario
    const personalInfo = ref({
        name: '',
        second_name: '',
        last_name: '',
        maiden_name: '',
        gender: '',
        birthday: '',
        rfc: '',
        curp: '',
        state: '',
        email: '',
        phone: '',
    });

    const addressInfo = ref({
        street: '',
        interior_number: '',
        outdoor_number: '',
        suburb: '',
        postal_code: '',
        years_living: '',
        type: '',
    });

    const civilInfo = ref({
        civil_status: '',
        level_study: '',
        own_car: '',
        economic_dependents: '',
    });

    const employmentInfo = ref({
        type: '',
        area: '',
        main_activity: '',
        company: '',
        job: '',
        seniority_working: '',
        phone: '',
        imss: '',
        insurance_major_medical_expenses: '',
        monthly_income: '',
        other_income: '',
    });

    const loanInfo = ref({
        type: 'NORMAL',
        policy_folio: '',
        customer_id: '',
        amount: '',
        purpose: '',
        purpose_description: '',
        approved_at: '',
        term: '',
        start_at: '',
        paid_at: '',
    });

    // Estado de la navegación
    const currentStep = ref(1);
    const totalSteps = 6;
    const isSubmitting = ref(false);
    const errors = ref({});
    const customerId = ref(null);
    const loanId = ref(null);

    // Getters
    const canProceed = computed(() => {
        return !isSubmitting.value && Object.keys(errors.value).length === 0;
    });

    // Acciones
    const updatePersonalInfo = (data) => {
        personalInfo.value = { ...personalInfo.value, ...data };
    };

    const updateAddressInfo = (data) => {
        addressInfo.value = { ...addressInfo.value, ...data };
    };

    const updateCivilInfo = (data) => {
        civilInfo.value = { ...civilInfo.value, ...data };
    };

    const updateEmploymentInfo = (data) => {
        employmentInfo.value = { ...employmentInfo.value, ...data };
    };

    const updateLoanInfo = (data) => {
        loanInfo.value = { ...loanInfo.value, ...data };
    };

    const setErrors = (newErrors) => {
        errors.value = { ...errors.value, ...newErrors };
    };

    const clearErrors = () => {
        errors.value = {};
    };

    const formatDate = (value) => {
        if (!value) return value;
        if (value instanceof Date) {
            const m = String(value.getMonth() + 1).padStart(2, '0');
            const d = String(value.getDate()).padStart(2, '0');
            return `${value.getFullYear()}-${m}-${d}`;
        }
        return String(value).split('T')[0];
    };

    // Convierte la respuesta de error del servidor en un mensaje visible para el usuario
    const extractError = (error) => {
        const data = error.response?.data;
        if (data?.errors) {
            return Object.values(data.errors).flat().join(' ');
        }
        return data?.message || 'No se pudo guardar la información. Intenta de nuevo.';
    };

    // Envía un paso; si falla muestra el error en lugar de quedarse sin avisar
    const submitStep = async (url, payload, okStatus, onSuccess) => {
        if (isSubmitting.value) return false;
        isSubmitting.value = true;
        errors.value = {};
        try {
            const response = await axios.post(url, payload);
            if (response.status === okStatus) {
                onSuccess(response.data.data);
                currentStep.value++;
                return true;
            }
            errors.value = { submit: 'No se pudo guardar la información.' };
            return false;
        } catch (error) {
            errors.value = { submit: extractError(error) };
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    const sendPersonalInfo = () =>
        submitStep(
            '/request-credit/customer',
            { ...personalInfo.value, birthday: formatDate(personalInfo.value.birthday) },
            201,
            (data) => {
                customerId.value = data.id;
                loanId.value = data.loan?.id ?? null;
                customer.value = data;
            },
        );

    const sendAddress = () =>
        submitStep(
            `/request-credit/customer/${customerId.value}/address`,
            { ...addressInfo.value, loan_id: loanId.value },
            201,
            (data) => {
                customer.value = { ...customer.value, ...data };
            },
        );

    const sendCivilInfo = () =>
        submitStep(
            `/request-credit/customer/${customerId.value}`,
            { ...civilInfo.value, loan_id: loanId.value },
            200,
            (data) => {
                customer.value = { ...customer.value, ...data };
            },
        );

    const sendEmploymentInfo = () =>
        submitStep(
            `/request-credit/customer/${customerId.value}/employment`,
            { ...employmentInfo.value, loan_id: loanId.value },
            201,
            (data) => {
                customer.value = { ...customer.value, ...data };
            },
        );

    const sendLoanInfo = () =>
        submitStep(
            `/request-credit/customer/${customerId.value}/loan`,
            { ...loanInfo.value, loan_id: loanId.value },
            201,
            (data) => {
                customer.value = { ...customer.value, ...data };
            },
        );

    const nextStep = async () => {
        if (!canProceed.value) return false;
        
        isSubmitting.value = true;
        try {
            // Aquí iría la lógica para guardar los datos del paso actual
            // Por ejemplo:
            // await saveStepData();
            
            if (currentStep.value < totalSteps) {
                currentStep.value++;
                return true;
            }
            return false;
        } catch (error) {
            setErrors({ submit: error.message });
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    const previousStep = () => {
        errors.value = {};
        if (currentStep.value > 1) {
            currentStep.value--;
            return true;
        }
        return false;
    };

    const resetForm = () => {
        personalInfo.value = {
            name: '',
            second_name: '',
            last_name: '',
            maiden_name: '',
            gender: '',
            birthday: '',
            rfc: '',
            curp: '',
            state: '',
            email: '',
            phone: '',
        };
        addressInfo.value = {
            street: '',
            number: '',
            interior: '',
            neighborhood: '',
            postal_code: '',
            city: '',
            state: '',
            phone: '',
            email: '',
        };
        civilInfo.value = {
            civil_status: '',
            level_study: '',
            own_car: '',
            economic_dependents: '',
        };
        employmentInfo.value = {
            type: '',
            area: '',
            main_activity: '',
            company: '',
            job: '',
            seniority_working: '',
            phone: '',
            imss: '',
            insurance_major_medical_expenses: '',
            monthly_income: '',
            other_income: '',
        };
        loanInfo.value = {
            type: 'NORMAL',
            policy_folio: '',
            customer_id: '',
            amount: '',
            purpose: '',
            purpose_description: '',
            approved_at: '',
            term: '',
            start_at: '',
            paid_at: '',
        };
        currentStep.value = 1;
        clearErrors();
    };

    return {
        // Estado
        personalInfo,
        addressInfo,
        employmentInfo,
        civilInfo,
        loanInfo,
        currentStep,
        totalSteps,
        isSubmitting,
        errors,
        customer,
        customerId,
        // Getters
        canProceed,
        
        // Acciones
        updatePersonalInfo,
        updateAddressInfo,
        updateEmploymentInfo,
        updateCivilInfo,
        updateLoanInfo,
        setErrors,
        clearErrors,
        nextStep,
        previousStep,
        resetForm,
        sendPersonalInfo,
        sendAddress,
        sendCivilInfo,
        sendEmploymentInfo,
        sendLoanInfo,
    };
}); 