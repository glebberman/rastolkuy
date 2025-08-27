import React, { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { IconMail } from '@tabler/icons-react';
import AuthLayout from '@/Layouts/AuthLayout';
import AuthHeader from '@/Components/Auth/AuthHeader';
import AuthFooter from '@/Components/Auth/AuthFooter';
import FormInput from '@/Components/Form/FormInput';
import SubmitButton from '@/Components/Form/SubmitButton';
import { ForgotPasswordForm } from '@/Types';

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
                <AuthHeader 
                    icon={<IconMail size={32} />}
                    title="Забыли пароль?"
                    subtitle="Введите ваш email, и мы отправим ссылку для восстановления пароля"
                    iconBgColor="bg-info"
                />

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
                                <FormInput
                                    id="email"
                                    label="Email адрес"
                                    type="email"
                                    value={data.email}
                                    onChange={(value) => setData('email', value)}
                                    placeholder="user@example.com"
                                    error={errors.email}
                                    required
                                    autoFocus
                                />
                            </div>

                            <SubmitButton
                                isLoading={processing}
                                loadingText="Отправка письма..."
                            >
                                Отправить ссылку для восстановления
                            </SubmitButton>
                        </form>
                    )}

                    <div className="text-center">
                        <Link href="/login" className="text-decoration-none">
                            ← Вернуться к входу
                        </Link>
                    </div>
                </div>

                <AuthFooter 
                    text="Нет аккаунта?"
                    linkText="Зарегистрироваться"
                    linkHref="/register"
                />
            </div>
        </AuthLayout>
    );
}