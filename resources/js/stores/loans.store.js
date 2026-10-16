import { defineStore } from 'pinia';
import { router, usePage } from '@inertiajs/vue3'

const page = usePage();

const baseUrl = `${import.meta.env.VITE_API_URL || 'https://sofom.test'}/api`;

export const useAgentLoanStore = defineStore('agentloans', {
    state: () => ({
        loading: false,
        error: {},
        loan: {
            customer: {
                addresses: []
            },
            user: {}
        },
        requests: {
            loading: false,
            data: [],
            pagination: {},
        }
    }),
    getters: {
    },
    actions: {
        async getAll() {
            this.requests = { loading: true };
            try {
                const response = await fetchWrapper.get(`${baseUrl}/loans`);

                this.requests = { loading: false, ...response.data };
            } catch (error) {
                this.requests = { loading: false, error };
            }
        },
        async getLoanDetail(loan_id) {
            console.log(loan_id);
            this.loading = true;
            try {
                const response = await fetchWrapper.get(`${baseUrl}/loans/${loan_id}`);

                this.loading = false;
                this.loan = response.data;
            } catch (error) {
                this.loading = false;
                this.error = error;
            }
        },
        async updateLoan({loanId, status}) {
            console.log(this.loan.id);
            this.loading = true;
            return router.put(`${baseUrl}/loans/${loanId}`, { status }).then(response => {
                this.loading = false;
                return response;
            }).catch(error => {
                this.loading = false;
                this.error = error;
                throw error;
            });
        }
    },
})
