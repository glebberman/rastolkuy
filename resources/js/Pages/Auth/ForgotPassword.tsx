import React, { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconGavel, IconLoader2, IconMail } from '@tabler/icons-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { ForgotPasswordForm } from '../../Types';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors, wasSuccessful } = useForm<ForgotPasswordForm>({
        email: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout title="Восстановление пароля">
            <div className="auth-card card">
                {/* Header */}
                <div className="auth-header">
                    <div className="text-center mb-3">
                        <div className="d-inline-flex align-items-center justify-content-center rounded-circle bg-info text-white mb-3" 
                             style={{ width: '64px', height: '64px' }}>
                            <IconMail size={32} />
                        </div>
                    </div>
                    <h1 className="auth-title">Забыли пароль?</h1>
                    <p className="auth-subtitle">
                        Введите ваш email, и мы отправим ссылку для восстановления пароля
                    </p>
                </div>

                {/* Form */}
                <div className="auth-form">
                    {wasSuccessful ? (
                        <div className="alert alert-success">
                            <h6 className="alert-heading d-flex align-items-center">
                                <IconMail className="me-2" size={20} />
                                Письмо отправлено!
                            </h6>
                            <p className="mb-0">
                                Мы отправили ссылку для восстановления пароля на ваш email. 
                                Проверьте почту и следуйте инструкциям в письме.
                            </p>
                            <hr />
                            <p className="mb-0 small text-muted">
                                Если письмо не пришло в течение 5 минут, проверьте папку "Спам".
                            </p>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label htmlFor="email" className="form-label">Email адрес</label>
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

                            <button
                                type="submit"
                                className="btn btn-primary w-100 mb-3"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <IconLoader2 className="me-2 spinner-border spinner-border-sm" />
                                        Отправка письма...
                                    </>
                                ) : (
                                    'Отправить ссылку для восстановления'
                                )}
                            </button>
                        </form>
                    )}

                    <div className="text-center">
                        <Link href="/login" className="text-decoration-none">
                            ← Вернуться к входу
                        </Link>
                    </div>
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