import React, { useState, FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconEye, IconEyeOff, IconGavel, IconLoader2 } from '@tabler/icons-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { RegisterForm } from '../../Types';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <AuthLayout title="Регистрация">
            <div className="auth-card card">
                {/* Header */}
                <div className="auth-header">
                    <div className="text-center mb-3">
                        <div className="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3" 
                             style={{ width: '64px', height: '64px' }}>
                            <IconGavel size={32} />
                        </div>
                    </div>
                    <h1 className="auth-title">Создать аккаунт</h1>
                    <p className="auth-subtitle">Зарегистрируйтесь для доступа к сервису</p>
                </div>

                {/* Form */}
                <div className="auth-form">
                    <form onSubmit={handleSubmit}>
                        <div className="mb-3">
                            <label htmlFor="name" className="form-label">Имя</label>
                            <input
                                type="text"
                                className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Ваше имя"
                                required
                                autoFocus
                            />
                            {errors.name && (
                                <div className="invalid-feedback">
                                    {errors.name}
                                </div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="email" className="form-label">Email</label>
                            <input
                                type="email"
                                className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                                id="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="user@example.com"
                                required
                            />
                            {errors.email && (
                                <div className="invalid-feedback">
                                    {errors.email}
                                </div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="password" className="form-label">Пароль</label>
                            <div className="position-relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                    id="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Минимум 8 символов"
                                    required
                                />
                                <button
                                    type="button"
                                    className="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-3 text-muted"
                                    onClick={() => setShowPassword(!showPassword)}
                                    tabIndex={-1}
                                    style={{ border: 'none', background: 'none' }}
                                >
                                    {showPassword ? <IconEyeOff size={20} /> : <IconEye size={20} />}
                                </button>
                            </div>
                            {errors.password && (
                                <div className="invalid-feedback d-block">
                                    {errors.password}
                                </div>
                            )}
                        </div>

                        <div className="mb-3">
                            <label htmlFor="password_confirmation" className="form-label">Подтверждение пароля</label>
                            <div className="position-relative">
                                <input
                                    type={showPasswordConfirmation ? 'text' : 'password'}
                                    className={`form-control ${errors.password_confirmation ? 'is-invalid' : ''}`}
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="Повторите пароль"
                                    required
                                />
                                <button
                                    type="button"
                                    className="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-3 text-muted"
                                    onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                                    tabIndex={-1}
                                    style={{ border: 'none', background: 'none' }}
                                >
                                    {showPasswordConfirmation ? <IconEyeOff size={20} /> : <IconEye size={20} />}
                                </button>
                            </div>
                            {errors.password_confirmation && (
                                <div className="invalid-feedback d-block">
                                    {errors.password_confirmation}
                                </div>
                            )}
                        </div>

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

                        <button
                            type="submit"
                            className="btn btn-primary w-100 mb-3"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <IconLoader2 className="me-2 spinner-border spinner-border-sm" />
                                    Создание аккаунта...
                                </>
                            ) : (
                                'Зарегистрироваться'
                            )}
                        </button>
                    </form>
                </div>

                {/* Footer */}
                <div className="auth-footer">
                    <p>
                        Уже есть аккаунт?{' '}
                        <Link href="/login" className="text-decoration-none">
                            Войти
                        </Link>
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}