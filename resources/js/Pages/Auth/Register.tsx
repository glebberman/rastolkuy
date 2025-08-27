import React, { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconGavel } from '@tabler/icons-react';
import AuthLayout from '@/Layouts/AuthLayout';
import AuthHeader from '@/Components/Auth/AuthHeader';
import AuthFooter from '@/Components/Auth/AuthFooter';
import FormInput from '@/Components/Form/FormInput';
import PasswordInput from '@/Components/Form/PasswordInput';
import SubmitButton from '@/Components/Form/SubmitButton';
import { RegisterForm } from '@/Types';
import { route } from '@/Utils/route';

export default function Register() {
    
    const { data, setData, post, processing, errors } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <AuthLayout title="Регистрация">
            <div className="auth-card card">
                <AuthHeader 
                    icon={<IconGavel size={32} />}
                    title="Создать аккаунт"
                    subtitle="Зарегистрируйтесь для доступа к сервису"
                />

                {/* Form */}
                <div className="auth-form">
                    <form onSubmit={handleSubmit}>
                        <FormInput
                            id="name"
                            label="Имя"
                            value={data.name}
                            onChange={(value) => setData('name', value)}
                            placeholder="Ваше имя"
                            error={errors.name}
                            required
                            autoFocus
                        />

                        <FormInput
                            id="email"
                            label="Email"
                            type="email"
                            value={data.email}
                            onChange={(value) => setData('email', value)}
                            placeholder="user@example.com"
                            error={errors.email}
                            required
                        />

                        <PasswordInput
                            id="password"
                            label="Пароль"
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            placeholder="Минимум 8 символов"
                            error={errors.password}
                            required
                        />

                        <PasswordInput
                            id="password_confirmation"
                            label="Подтверждение пароля"
                            value={data.password_confirmation}
                            onChange={(value) => setData('password_confirmation', value)}
                            placeholder="Повторите пароль"
                            error={errors.password_confirmation}
                            required
                        />

                        <div className="mb-3 form-check">
                            <input
                                type="checkbox"
                                className={`form-check-input ${errors.terms ? 'is-invalid' : ''}`}
                                id="terms"
                                checked={data.terms}
                                onChange={(e) => setData('terms', e.target.checked)}
                                required
                            />
                            <label className="form-check-label" htmlFor="terms">
                                Я согласен с{' '}
                                <Link href="/terms" className="text-decoration-none" target="_blank">
                                    условиями использования
                                </Link>{' '}
                                и{' '}
                                <Link href="/privacy" className="text-decoration-none" target="_blank">
                                    политикой конфиденциальности
                                </Link>
                            </label>
                            {errors.terms && (
                                <div className="invalid-feedback d-block">
                                    {errors.terms}
                                </div>
                            )}
                        </div>

                        <SubmitButton
                            isLoading={processing}
                            loadingText="Создание аккаунта..."
                        >
                            Зарегистрироваться
                        </SubmitButton>
                    </form>
                </div>

                <AuthFooter 
                    text="Уже есть аккаунт?"
                    linkText="Войти"
                    linkHref={route('login')}
                />
            </div>
        </AuthLayout>
    );
}