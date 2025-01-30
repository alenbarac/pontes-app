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
}
export interface MemberFormData {
    first_name: string;
    last_name?: string;
    birth_year?: string;
    phone_number?: string;
    email: string;
    is_active: boolean;
    parent_contact?: string;
    parent_email?: string;
    group_ids?: number[]; // Array of selected group IDs
    workshop_ids?: number[]; // Array of selected workshop IDs
    membership_plan_id: number; // Single membership plan (if applicable)
    workshopsWithMemberships?: {
        workshop_id: number;
        membership_plan: string;
    }[]; // Array of workshops and their associated plans
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
    groups: { id: number; name: string }[];
    workshops: { id: number; name: string }[];
    membership: { plan: string; fee: number };
}
