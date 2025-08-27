export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles?: string[];
    permissions?: string[];
    created_at: string;
    updated_at: string;
}

export interface Auth {
    user: User | null;
}

export interface PageProps<T extends Record<string, unknown> = Record<string, unknown>> {
    auth: Auth;
    csrf_token: string;
    flash: {
        message?: string;
        error?: string;
        success?: string;
        info?: string;
        warning?: string;
    };
    ziggy: {
        routes: Record<string, any>;
        url: string;
        port: number | null;
        defaults: Record<string, any>;
        location: string;
    };
}

export type PagePropsWithData<T> = PageProps & T;

// API Response types
export interface ApiResponse<T = unknown> {
    data: T;
    message?: string;
    success: boolean;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

// Form types
export interface LoginForm {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms?: boolean;
}

export interface ForgotPasswordForm {
    email: string;
}

export interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

// Document types
export interface DocumentProcessing {
    id: number;
    uuid: string;
    user_id: number;
    filename: string;
    original_filename: string;
    file_path: string;
    mime_type: string;
    size_bytes: number;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    processed_content?: string;
    metadata?: Record<string, unknown>;
    processing_started_at?: string;
    processing_completed_at?: string;
    error_message?: string;
    created_at: string;
    updated_at: string;
}

// Theme types
export type Theme = 'light' | 'dark' | 'auto';

// Error types
export interface ValidationErrors {
    [key: string]: string[];
}

export interface FormError {
    message: string;
    errors?: ValidationErrors;
}

// Global window extensions
declare global {
    interface Window {
        axios: typeof import('axios').default;
    }
    
    var route: (name?: string, params?: any, absolute?: boolean) => string;
}