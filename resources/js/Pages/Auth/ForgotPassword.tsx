import React, { FormEvent, useState } from 'react';
import { Link } from '@inertiajs/react';
import { IconMail } from '@tabler/icons-react';
import AuthLayout from '@/Layouts/AuthLayout';
import AuthHeader from '@/Components/Auth/AuthHeader';
import AuthFooter from '@/Components/Auth/AuthFooter';
import FormInput from '@/Components/Form/FormInput';
import SubmitButton from '@/Components/Form/SubmitButton';
import { authService } from '@/Utils/auth';
import { route } from '@/Utils/route';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [wasSuccessful, setWasSuccessful] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await authService.forgotPassword(email);
            setWasSuccessful(true);
            setSuccessMessage(response.message);
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
                setErrors({ email: error.message || 'Ошибка отправки письма' });
            }
        } finally {
            setProcessing(false);
        }
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
                                {successMessage || 'Мы отправили ссылку для восстановления пароля на ваш email. Проверьте почту и следуйте инструкциям в письме.'}
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
                                    value={email}
                                    onChange={(value) => setEmail(value)}
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
                        <Link href={route('login')} className="text-decoration-none">
                            ← Вернуться к входу
                        </Link>
                    </div>
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