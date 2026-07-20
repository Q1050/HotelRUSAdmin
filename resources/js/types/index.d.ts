import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'super_admin' | 'manager' | 'front_desk' | 'housekeeping' | 'maintenance';
    status: 'active' | 'suspended';
    is_platform_admin: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
    system: { version: string; releaseName: string };
    notifications: { unreadCount: number; latest: Array<{ id: string; message: string; url: string | null; read: boolean }> };
    impersonating: boolean;
};
