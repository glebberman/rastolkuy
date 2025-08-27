import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { PageProps, User } from '@/Types';
import ThemeSwitcher from '@/Components/UI/ThemeSwitcher';
import UserDropdown from '@/Components/Layout/UserDropdown';
import { IconGavel } from '@tabler/icons-react';
import { route } from '@/Utils/route';

export default function Header() {
    const { auth } = usePage<PageProps<Record<string, unknown>>>().props;

    return (
        <header className="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div className="container-fluid">
                {/* Brand */}
                <Link className="navbar-brand d-flex align-items-center" href={route('dashboard')}>
                    <IconGavel className="me-2" size={28} />
                    <span className="fw-bold">Legal Translator</span>
                </Link>

                {/* Mobile menu toggle */}
                <button
                    className="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span className="navbar-toggler-icon"></span>
                </button>

                {/* Navigation */}
                <div className="collapse navbar-collapse" id="navbarNav">
                    <nav className="navbar-nav me-auto">
                        {auth.user && (
                            <>
                                <Link
                                    className="nav-link"
                                    href={route('dashboard')}
                                >
                                    Главная
                                </Link>
                                <Link
                                    className="nav-link"
                                    href={route('documents.index') || '/documents'}
                                >
                                    Документы
                                </Link>
                                {auth.user.roles?.includes('admin') && (
                                    <div className="nav-item dropdown">
                                        <button
                                            className="nav-link dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                        >
                                            Администрирование
                                        </button>
                                        <ul className="dropdown-menu">
                                            <li>
                                                <Link className="dropdown-item" href="/admin/users">
                                                    Пользователи
                                                </Link>
                                            </li>
                                            <li>
                                                <Link className="dropdown-item" href="/admin/documents">
                                                    Документы
                                                </Link>
                                            </li>
                                            <li>
                                                <Link className="dropdown-item" href="/admin/statistics">
                                                    Статистика
                                                </Link>
                                            </li>
                                        </ul>
                                    </div>
                                )}
                            </>
                        )}
                    </nav>

                    {/* Right side */}
                    <div className="d-flex align-items-center gap-3">
                        <ThemeSwitcher />
                        
                        {auth.user ? (
                            <UserDropdown user={auth.user} />
                        ) : (
                            <div className="d-flex align-items-center gap-2">
                                <Link
                                    href={route('login')}
                                    className="btn btn-outline-primary btn-sm"
                                >
                                    Войти
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="btn btn-primary btn-sm"
                                >
                                    Регистрация
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}