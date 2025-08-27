import React from 'react';
import { Link, router } from '@inertiajs/react';
import { User } from '../../Types';
import { IconUser, IconSettings, IconLogout, IconShield } from '@tabler/icons-react';

interface Props {
    user: User;
}

export default function UserDropdown({ user }: Props) {
    const handleLogout = () => {
        router.post('/logout');
    };

    const getInitials = (name: string): string => {
        return name
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <div className="dropdown">
            <button
                className="btn btn-link p-0 d-flex align-items-center text-decoration-none"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <div className="d-flex align-items-center gap-2">
                    <div
                        className="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                        style={{
                            width: '32px',
                            height: '32px',
                            backgroundColor: 'var(--bs-primary)',
                            fontSize: '0.875rem'
                        }}
                    >
                        {getInitials(user.name)}
                    </div>
                    <div className="d-none d-md-block text-start">
                        <div className="fw-semibold text-dark" style={{ fontSize: '0.875rem' }}>
                            {user.name}
                        </div>
                        <div className="text-muted" style={{ fontSize: '0.75rem', lineHeight: 1 }}>
                            {user.roles?.[0] || 'Пользователь'}
                        </div>
                    </div>
                </div>
            </button>

            <ul className="dropdown-menu dropdown-menu-end shadow-sm">
                <li className="dropdown-header">
                    <div className="fw-semibold">{user.name}</div>
                    <div className="text-muted small">{user.email}</div>
                </li>
                
                <li><hr className="dropdown-divider" /></li>
                
                <li>
                    <Link className="dropdown-item d-flex align-items-center gap-2" href="/profile">
                        <IconUser size={16} />
                        Профиль
                    </Link>
                </li>
                
                <li>
                    <Link className="dropdown-item d-flex align-items-center gap-2" href="/settings">
                        <IconSettings size={16} />
                        Настройки
                    </Link>
                </li>
                
                {user.roles?.includes('admin') && (
                    <>
                        <li><hr className="dropdown-divider" /></li>
                        <li>
                            <Link className="dropdown-item d-flex align-items-center gap-2" href="/admin">
                                <IconShield size={16} />
                                Панель администратора
                            </Link>
                        </li>
                    </>
                )}
                
                <li><hr className="dropdown-divider" /></li>
                
                <li>
                    <button
                        className="dropdown-item d-flex align-items-center gap-2 text-danger"
                        onClick={handleLogout}
                    >
                        <IconLogout size={16} />
                        Выйти
                    </button>
                </li>
            </ul>
        </div>
    );
}