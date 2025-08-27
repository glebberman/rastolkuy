import axios, { AxiosResponse } from 'axios';
import {
    LoginForm,
    RegisterForm,
    ForgotPasswordForm,
    ResetPasswordForm,
    User,
    ApiResponse,
} from '../Types';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request interceptor
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export const authAPI = {
    register: (data: RegisterForm): Promise<AxiosResponse<ApiResponse<{ user: User; token: string }>>> =>
        api.post('/auth/register', data),
    
    login: (data: LoginForm): Promise<AxiosResponse<ApiResponse<{ user: User; token: string }>>> =>
        api.post('/auth/login', data),
    
    logout: (): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.post('/auth/logout'),
    
    user: (): Promise<AxiosResponse<ApiResponse<User>>> =>
        api.get('/auth/user'),
    
    updateUser: (data: Partial<User>): Promise<AxiosResponse<ApiResponse<User>>> =>
        api.put('/auth/user', data),
    
    forgotPassword: (data: ForgotPasswordForm): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.post('/auth/forgot-password', data),
    
    resetPassword: (data: ResetPasswordForm): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.post('/auth/reset-password', data),
    
    refreshToken: (): Promise<AxiosResponse<ApiResponse<{ user: User; token: string }>>> =>
        api.post('/auth/refresh'),
    
    resendVerification: (): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.post('/auth/resend-verification'),
};

export const documentsAPI = {
    upload: (file: File, options?: Record<string, unknown>): Promise<AxiosResponse<ApiResponse<{ uuid: string }>>> => {
        const formData = new FormData();
        formData.append('file', file);
        if (options) {
            Object.entries(options).forEach(([key, value]) => {
                formData.append(key, String(value));
            });
        }
        
        return api.post('/v1/documents', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
    },
    
    status: (uuid: string): Promise<AxiosResponse<ApiResponse<any>>> =>
        api.get(`/v1/documents/${uuid}/status`),
    
    result: (uuid: string): Promise<AxiosResponse<ApiResponse<any>>> =>
        api.get(`/v1/documents/${uuid}/result`),
    
    cancel: (uuid: string): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.post(`/v1/documents/${uuid}/cancel`),
    
    delete: (uuid: string): Promise<AxiosResponse<ApiResponse<null>>> =>
        api.delete(`/v1/documents/${uuid}`),
};

export default api;