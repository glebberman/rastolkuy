import React, { useState, FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconEye, IconEyeOff, IconGavel, IconLoader2 } from '@tabler/icons-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { LoginForm } from '../../Types';

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <AuthLayout title="Вход в систему">
            <div className="auth-card card">
                {/* Header */}
                <div className="auth-header">
                    <div className="text-center mb-3">
                        <div className="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3" 
                             style={{ width: '64px', height: '64px' }}>
                            <IconGavel size={32} />
                        </div>
                    </div>
                    <h1 className="auth-title">Добро пожаловать!</h1>
                    <p className="auth-subtitle">Войдите в свой аккаунт для продолжения</p>
                </div>

                {/* Form */}
                <div className="auth-form">
                    <form onSubmit={handleSubmit}>
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
                                autoFocus
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
                                    placeholder="Введите пароль"
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

                        <div className="mb-3 form-check">
                            <input
                                type="checkbox"
                                className="form-check-input"
                                id="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            <label className="form-check-label" htmlFor="remember">
                                Запомнить меня
                            </label>
                        </div>

                        <button
                            type="submit"
                            className="btn btn-primary w-100 mb-3"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <IconLoader2 className="me-2 spinner-border spinner-border-sm" />
                                    Вход в систему...
                                </>
                            ) : (
                                'Войти'
                            )}
                        </button>

                        <div className="text-center">
                            <Link href="/forgot-password" className="text-decoration-none">
                                Забыли пароль?
                            </Link>
                        </div>
                    </form>
                </div>

                {/* Footer */}
                <div className="auth-footer">
                    <p>
                        Нет аккаунта?{' '}
                        <Link href="/register" className="text-decoration-none">
                            Зарегистрироваться
                        </Link>
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}