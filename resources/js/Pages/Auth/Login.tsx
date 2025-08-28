import React, { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { IconGavel } from '@tabler/icons-react';
import AuthLayout from '@/Layouts/AuthLayout';
import AuthHeader from '@/Components/Auth/AuthHeader';
import AuthFooter from '@/Components/Auth/AuthFooter';
import FormInput from '@/Components/Form/FormInput';
import PasswordInput from '@/Components/Form/PasswordInput';
import SubmitButton from '@/Components/Form/SubmitButton';
import { authService, LoginData } from '@/Utils/auth';
import { route } from '@/Utils/route';

export default function Login() {
    const [data, setData] = useState<LoginData>({
        email: '',
        password: '',
        remember: false,
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            console.log('Attempting login with:', { email: data.email });
            const result = await authService.login(data);
            console.log('Login successful, result:', result);
            console.log('Token saved:', authService.getToken());
            console.log('User saved:', authService.getUser());
            console.log('Is authenticated:', authService.isAuthenticated());
            
            router.visit('/dashboard');
        } catch (error: any) {
            // Handle Laravel validation errors (field-specific)
            if (error.errors && typeof error.errors === 'object') {
                const formattedErrors: Record<string, string> = {};
                for (const [field, messages] of Object.entries(error.errors)) {
                    if (Array.isArray(messages)) {
                        formattedErrors[field] = messages[0];
                    }
                }
                setErrors(formattedErrors);
            } else {
                // Handle general API errors
                setErrors({ email: error.message || 'Ошибка входа в систему' });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <AuthLayout title="Вход в систему">
            <div className="auth-card card">
                <AuthHeader 
                    icon={<IconGavel size={32} />}
                    title="Добро пожаловать!"
                    subtitle="Войдите в свой аккаунт для продолжения"
                />

                <div className="auth-form">
                    <form onSubmit={handleSubmit}>
                        <FormInput
                            id="email"
                            label="Email"
                            type="email"
                            value={data.email}
                            onChange={(value) => setData({ ...data, email: value })}
                            placeholder="user@example.com"
                            error={errors.email}
                            required
                            autoFocus
                        />

                        <PasswordInput
                            id="password"
                            label="Пароль"
                            value={data.password}
                            onChange={(value) => setData({ ...data, password: value })}
                            placeholder="Введите пароль"
                            error={errors.password}
                            required
                        />

                        <div className="mb-3 form-check">
                            <input
                                type="checkbox"
                                className="form-check-input"
                                id="remember"
                                checked={data.remember || false}
                                onChange={(e) => setData({ ...data, remember: e.target.checked })}
                            />
                            <label className="form-check-label" htmlFor="remember">
                                Запомнить меня
                            </label>
                        </div>

                        <SubmitButton
                            isLoading={processing}
                            loadingText="Вход в систему..."
                        >
                            Войти
                        </SubmitButton>

                        <div className="text-center">
                            <Link href={route('password.request')} className="text-decoration-none">
                                Забыли пароль?
                            </Link>
                        </div>
                    </form>
                </div>

                <AuthFooter 
                    text="Нет аккаунта?"
                    linkText="Зарегистрироваться"
                    linkHref={route('register')}
                />
            </div>
        </AuthLayout>
    );
}