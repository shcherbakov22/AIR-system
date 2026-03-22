export interface StudentProfile {
    id: number;
    display_name: string;
    status: string;
    notes?: string | null;
    settings?: {
        can_manage_own_schedule: boolean;
        can_use_ad_hoc_timer: boolean;
        preferred_timezone?: string | null;
    } | null;
}

export interface User {
    id: number;
    username: string;
    name: string;
    email?: string | null;
    role: 'admin' | 'student';
    role_label: string;
    is_active: boolean;
    last_login_at?: string | null;
    student?: StudentProfile | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
        enrollment_token?: {
            token: string;
            expires_at?: string | null;
        } | null;
    };
};
