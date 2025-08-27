import React, { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { User } from '@/Types';
import ThemeSwitcher from '@/Components/UI/ThemeSwitcher';
import UserDropdown from '@/Components/Layout/UserDropdown';
import { IconGavel } from '@tabler/icons-react';
import { authService } from '@/Utils/auth';
import { route } from '@/Utils/route';

export default function Header() {
    const [user, setUser] = useState<User | null>(null);

    useEffect(() => {
        const currentUser = authService.getUser();
        setUser(currentUser);

        // If user is logged in but not loaded, try to fetch from API
        if (authService.isAuthenticated() && !currentUser) {
            authService.getCurrentUser()
                .then(setUser)
                .catch(() => setUser(null));
        }
    }, []);

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
                        {user && (
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
                            </>
                        )}
                    </nav>

                    {/* Right side */}
                    <div className="d-flex align-items-center gap-3">
                        <ThemeSwitcher />
                        
                        {user ? (
                            <UserDropdown user={user} />
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