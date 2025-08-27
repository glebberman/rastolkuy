import axios from 'axios';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface LoginData {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms: boolean;
}

export interface AuthResponse {
    success: boolean;
    message: string;
    data?: {
        user: User;
        token: string;
    };
    user?: User;
    token?: string;
}

export interface ApiErrorResponse {
    success: false;
    error: string;
    message: string;
    errors?: Record<string, string[]>;
}

class AuthService {
    private token: string | null = null;
    private user: User | null = null;

    constructor() {
        this.token = localStorage.getItem('auth_token');
        this.setupAxiosDefaults();
        this.setupAxiosInterceptors();
    }

    private setupAxiosDefaults() {
        // Set CSRF token for axios
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }
        
        // Set default headers for JSON API
        axios.defaults.headers.common['Accept'] = 'application/json';
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    }

    private setupAxiosInterceptors() {
        // Request interceptor to add token
        axios.interceptors.request.use((config) => {
            const token = this.getToken();
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
            return config;
        });

        // Response interceptor to handle 401 errors
        axios.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response?.status === 401) {
                    this.logout();
                    window.location.href = '/login';
                }
                return Promise.reject(error);
            }
        );
    }

    async login(credentials: LoginData): Promise<AuthResponse> {
        try {
            // Get CSRF cookie for SPA
            await axios.get('/sanctum/csrf-cookie');
            
            const response = await axios.post('/api/auth/login', credentials);
            const data = response.data as AuthResponse;

            if (data.success && data.data) {
                this.setToken(data.data.token);
                this.setUser(data.data.user);
            }

            return data;
        } catch (error: any) {
            if (error.response?.data) {
                throw error.response.data as ApiErrorResponse;
            }
            throw new Error('Ошибка соединения с сервером');
        }
    }

    async register(userData: RegisterData): Promise<AuthResponse> {
        try {
            // Get CSRF cookie for SPA
            await axios.get('/sanctum/csrf-cookie');
            
            const response = await axios.post('/api/auth/register', userData);
            const data = response.data as AuthResponse;

            if (data.success && data.data) {
                this.setToken(data.data.token);
                this.setUser(data.data.user);
            }

            return data;
        } catch (error: any) {
            if (error.response?.data) {
                throw error.response.data as ApiErrorResponse;
            }
            throw new Error('Ошибка соединения с сервером');
        }
    }

    async logout(): Promise<void> {
        try {
            if (this.token) {
                await axios.post('/api/auth/logout');
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.clearAuth();
        }
    }

    async getCurrentUser(): Promise<User> {
        try {
            const response = await axios.get('/api/auth/user');
            const data = response.data;
            
            if (data.success && data.data) {
                this.setUser(data.data);
                return data.data;
            }
            throw new Error('Не удалось получить данные пользователя');
        } catch (error: any) {
            this.clearAuth();
            throw error;
        }
    }

    async forgotPassword(email: string): Promise<{ message: string }> {
        try {
            const response = await axios.post('/api/auth/forgot-password', { email });
            return response.data;
        } catch (error: any) {
            if (error.response?.data) {
                throw error.response.data as ApiErrorResponse;
            }
            throw new Error('Ошибка соединения с сервером');
        }
    }

    setToken(token: string): void {
        this.token = token;
        localStorage.setItem('auth_token', token);
    }

    getToken(): string | null {
        return this.token || localStorage.getItem('auth_token');
    }

    setUser(user: User): void {
        this.user = user;
        localStorage.setItem('auth_user', JSON.stringify(user));
    }

    getUser(): User | null {
        if (this.user) {
            return this.user;
        }

        const storedUser = localStorage.getItem('auth_user');
        if (storedUser) {
            try {
                this.user = JSON.parse(storedUser);
                return this.user;
            } catch (error) {
                localStorage.removeItem('auth_user');
            }
        }

        return null;
    }

    isAuthenticated(): boolean {
        return !!this.getToken();
    }

    clearAuth(): void {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
    }
}

export const authService = new AuthService();