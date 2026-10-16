import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        [x: string]: { name: String; }[];
        user: User;
    };
    ziggy: Config & { location: string };
};

export interface Loan {
    id: number;
    customer_id: number;
    amount: string;
    term: number;
    purpose: string;
    purpose_description: string;
    score: string;
    approved_at: string;
    reject_at: string | null;
    start_at: string;
    paid_at: string | null;
    payment: {
        details: Array;
    };
    total_payed: string;
    created_at: string;
    updated_at: string;
}
