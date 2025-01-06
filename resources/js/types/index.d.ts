export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
export interface MemberFormData {
    first_name: string;
    last_name?: string;
    birth_year?: string;
    phone_number?: string;
    email: string;
    is_active: boolean;
    parent_contact?: string;
    parent_email?: string;
}

export interface Member {
    id: number;
    first_name: string;
    last_name?: string;
    birth_year: number;
    phone_number: string;
    email: string;
    is_active: boolean;
    parent_contact?: string;
    parent_email?: string;
}
