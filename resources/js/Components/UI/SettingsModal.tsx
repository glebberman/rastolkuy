import React, { useState, useEffect } from 'react';
import { IconSettings, IconSun, IconMoon, IconDeviceDesktop, IconEye, IconEyeOff, IconKey } from '@tabler/icons-react';
import { themeManager } from '../../Utils/theme';
import { Theme } from '../../Types';
import { router } from '@inertiajs/react';

interface PasswordData {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export default function SettingsModal() {
    const [isOpen, setIsOpen] = useState(false);
    const [activeView, setActiveView] = useState<'main' | 'password'>('main');
    const [currentTheme, setCurrentTheme] = useState<Theme>(themeManager.getTheme());
    
    // Password form state
    const [passwordData, setPasswordData] = useState<PasswordData>({
        current_password: '',
        password: '',
        password_confirmation: ''
    });
    const [passwordVisible, setPasswordVisible] = useState({
        current: false,
        new: false,
        confirm: false
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        const handleThemeChange = (theme: Theme) => {
            setCurrentTheme(theme);
        };

        themeManager.addListener(handleThemeChange);

        return () => {
            themeManager.removeListener(handleThemeChange);
        };
    }, []);

    const handleThemeChange = (theme: Theme) => {
        themeManager.setTheme(theme);
    };

    const getThemeIcon = (theme: Theme) => {
        switch (theme) {
            case 'light':
                return <IconSun size={16} />;
            case 'dark':
                return <IconMoon size={16} />;
            case 'auto':
                return <IconDeviceDesktop size={16} />;
            default:
                return <IconSun size={16} />;
        }
    };

    const getThemeLabel = (theme: Theme) => {
        switch (theme) {
            case 'light':
                return 'Светлая';
            case 'dark':
                return 'Тёмная';
            case 'auto':
                return 'Автоматическая';
            default:
                return 'Светлая';
        }
    };

    const handlePasswordChange = (field: keyof PasswordData, value: string) => {
        setPasswordData(prev => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
    };

    const togglePasswordVisibility = (field: 'current' | 'new' | 'confirm') => {
        setPasswordVisible(prev => ({ ...prev, [field]: !prev[field] }));
    };

    const validatePassword = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!passwordData.current_password) {
            newErrors.current_password = 'Введите текущий пароль';
        }

        if (!passwordData.password) {
            newErrors.password = 'Введите новый пароль';
        } else if (passwordData.password.length < 8) {
            newErrors.password = 'Пароль должен содержать минимум 8 символов';
        }

        if (!passwordData.password_confirmation) {
            newErrors.password_confirmation = 'Повторите новый пароль';
        } else if (passwordData.password !== passwordData.password_confirmation) {
            newErrors.password_confirmation = 'Пароли не совпадают';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handlePasswordSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!validatePassword()) return;

        setIsSubmitting(true);
        
        router.put('/profile/password', passwordData, {
            onSuccess: () => {
                setPasswordData({
                    current_password: '',
                    password: '',
                    password_confirmation: ''
                });
                setActiveView('main');
                setIsOpen(false);
                setErrors({});
            },
            onError: (errors) => {
                console.error('Password change error:', errors);
                setErrors(errors as Record<string, string>);
            },
            onFinish: () => setIsSubmitting(false)
        });
    };

    const resetModal = () => {
        setActiveView('main');
        setPasswordData({
            current_password: '',
            password: '',
            password_confirmation: ''
        });
        setPasswordVisible({
            current: false,
            new: false,
            confirm: false
        });
        setErrors({});
        setIsSubmitting(false);
    };

    const handleClose = () => {
        setIsOpen(false);
        setTimeout(resetModal, 300);
    };

    return (
        <>
            {/* Settings button */}
            <button
                className="btn btn-ghost btn-sm"
                onClick={() => setIsOpen(true)}
                title="Настройки"
            >
                <IconSettings size={18} />
            </button>

            {/* Modal */}
            {isOpen && (
                <div className="modal show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">
                                    {activeView === 'main' ? 'Настройки' : 'Смена пароля'}
                                </h5>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={handleClose}
                                ></button>
                            </div>

                            <div className="modal-body">
                                {activeView === 'main' ? (
                                    <div>
                                        {/* Theme selection */}
                                        <div className="mb-4">
                                            <h6 className="mb-3">Тема оформления</h6>
                                            <div className="d-grid gap-2">
                                                {(['light', 'dark', 'auto'] as Theme[]).map((theme) => (
                                                    <button
                                                        key={theme}
                                                        className={`btn ${
                                                            currentTheme === theme 
                                                                ? 'btn-primary' 
                                                                : 'btn-outline-secondary'
                                                        } d-flex align-items-center justify-content-start gap-2`}
                                                        onClick={() => handleThemeChange(theme)}
                                                    >
                                                        {getThemeIcon(theme)}
                                                        {getThemeLabel(theme)}
                                                        {theme === 'auto' && (
                                                            <small className="text-muted ms-auto">по умолчанию</small>
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Password change button */}
                                        <div>
                                            <h6 className="mb-3">Безопасность</h6>
                                            <button
                                                className="btn btn-outline-primary d-flex align-items-center gap-2 w-100"
                                                onClick={() => setActiveView('password')}
                                            >
                                                <IconKey size={16} />
                                                Сменить пароль
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <form onSubmit={handlePasswordSubmit}>
                                        {errors.general && (
                                            <div className="alert alert-danger mb-3">
                                                {errors.general}
                                            </div>
                                        )}
                                        <div className="mb-3">
                                            <label htmlFor="current_password" className="form-label">
                                                Старый пароль
                                            </label>
                                            <div className="input-group">
                                                <input
                                                    type={passwordVisible.current ? "text" : "password"}
                                                    className={`form-control ${errors.current_password ? 'is-invalid' : ''}`}
                                                    id="current_password"
                                                    value={passwordData.current_password}
                                                    onChange={(e) => handlePasswordChange('current_password', e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary"
                                                    onClick={() => togglePasswordVisibility('current')}
                                                    tabIndex={-1}
                                                >
                                                    {passwordVisible.current ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                                                </button>
                                            </div>
                                            {errors.current_password && (
                                                <div className="invalid-feedback d-block">
                                                    {errors.current_password}
                                                </div>
                                            )}
                                        </div>

                                        <div className="mb-3">
                                            <label htmlFor="password" className="form-label">
                                                Новый пароль
                                            </label>
                                            <div className="input-group">
                                                <input
                                                    type={passwordVisible.new ? "text" : "password"}
                                                    className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                                    id="password"
                                                    value={passwordData.password}
                                                    onChange={(e) => handlePasswordChange('password', e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary"
                                                    onClick={() => togglePasswordVisibility('new')}
                                                    tabIndex={-1}
                                                >
                                                    {passwordVisible.new ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                                                </button>
                                            </div>
                                            {errors.password && (
                                                <div className="invalid-feedback d-block">
                                                    {errors.password}
                                                </div>
                                            )}
                                            <div className="form-text">
                                                Минимум 8 символов
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label htmlFor="password_confirmation" className="form-label">
                                                Повторить пароль
                                            </label>
                                            <div className="input-group">
                                                <input
                                                    type={passwordVisible.confirm ? "text" : "password"}
                                                    className={`form-control ${errors.password_confirmation ? 'is-invalid' : ''}`}
                                                    id="password_confirmation"
                                                    value={passwordData.password_confirmation}
                                                    onChange={(e) => handlePasswordChange('password_confirmation', e.target.value)}
                                                    disabled={isSubmitting}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary"
                                                    onClick={() => togglePasswordVisibility('confirm')}
                                                    tabIndex={-1}
                                                >
                                                    {passwordVisible.confirm ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                                                </button>
                                            </div>
                                            {errors.password_confirmation && (
                                                <div className="invalid-feedback d-block">
                                                    {errors.password_confirmation}
                                                </div>
                                            )}
                                        </div>

                                        <div className="d-flex gap-2">
                                            <button
                                                type="submit"
                                                className="btn btn-primary"
                                                disabled={isSubmitting}
                                            >
                                                {isSubmitting ? (
                                                    <>
                                                        <span className="spinner-border spinner-border-sm me-2"></span>
                                                        Сохранение...
                                                    </>
                                                ) : (
                                                    'Сохранить'
                                                )}
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-secondary"
                                                onClick={() => setActiveView('main')}
                                                disabled={isSubmitting}
                                            >
                                                Отмена
                                            </button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}