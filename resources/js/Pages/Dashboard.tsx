import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    IconPlus, 
    IconFile, 
    IconClock, 
    IconCheck, 
    IconAlertTriangle,
    IconTrendingUp,
    IconUsers,
    IconCurrencyDollar
} from '@tabler/icons-react';
import AppLayout from '../Layouts/AppLayout';
import { authService } from '@/Utils/auth';

interface DashboardProps {
    recentDocuments: Array<{
        id: number;
        title: string;
        status: 'pending' | 'processing' | 'completed' | 'failed';
        created_at: string;
        pages_count?: number;
    }>;
    stats: {
        total_documents: number;
        processed_today: number;
        success_rate: number;
        total_savings: number;
    };
}

export default function Dashboard({ recentDocuments = [], stats }: DashboardProps) {
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const checkAuth = async () => {
            if (!authService.isAuthenticated()) {
                router.visit('/login');
                return;
            }

            try {
                await authService.getCurrentUser();
                setIsAuthenticated(true);
            } catch (error) {
                router.visit('/login');
            } finally {
                setIsLoading(false);
            }
        };

        checkAuth();
    }, []);

    if (isLoading) {
        return (
            <AppLayout title="Загрузка...">
                <div className="page-wrapper">
                    <div className="page-body">
                        <div className="container-xl">
                            <div className="text-center">
                                <div className="spinner-border" role="status">
                                    <span className="visually-hidden">Загрузка...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (!isAuthenticated) {
        return null;
    }
    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <IconCheck size={16} className="text-success" />;
            case 'processing':
                return <IconClock size={16} className="text-warning" />;
            case 'failed':
                return <IconAlertTriangle size={16} className="text-danger" />;
            default:
                return <IconFile size={16} className="text-muted" />;
        }
    };

    const getStatusText = (status: string) => {
        switch (status) {
            case 'completed':
                return 'Обработан';
            case 'processing':
                return 'Обрабатывается';
            case 'failed':
                return 'Ошибка';
            default:
                return 'В очереди';
        }
    };

    const getStatusBadgeClass = (status: string) => {
        switch (status) {
            case 'completed':
                return 'badge bg-success-lt text-success';
            case 'processing':
                return 'badge bg-warning-lt text-warning';
            case 'failed':
                return 'badge bg-danger-lt text-danger';
            default:
                return 'badge bg-secondary-lt text-secondary';
        }
    };

    return (
        <AppLayout title="Главная панель">
            <Head title="Главная панель" />

            <div className="page-wrapper">
                <div className="page-header d-print-none">
                    <div className="container-xl">
                        <div className="row g-2 align-items-center">
                            <div className="col">
                                <div className="page-pretitle">
                                    Обзор
                                </div>
                                <h2 className="page-title">
                                    Главная панель
                                </h2>
                            </div>
                            <div className="col-auto ms-auto d-print-none">
                                <div className="btn-list">
                                    <Link
                                        href="/documents/upload"
                                        className="btn btn-primary d-none d-sm-inline-block"
                                    >
                                        <IconPlus className="me-1" size={16} />
                                        Загрузить документ
                                    </Link>
                                    <Link
                                        href="/documents/upload"
                                        className="btn btn-primary d-sm-none btn-icon"
                                        aria-label="Загрузить документ"
                                    >
                                        <IconPlus size={16} />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="page-body">
                    <div className="container-xl">
                        {/* Statistics Cards */}
                        <div className="row row-deck row-cards">
                            <div className="col-sm-6 col-lg-3">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Всего документов</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{stats?.total_documents || 0}</div>
                                            <div className="me-auto">
                                                <IconFile size={24} className="text-muted" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="col-sm-6 col-lg-3">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Обработано сегодня</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{stats?.processed_today || 0}</div>
                                            <div className="me-auto">
                                                <IconTrendingUp size={24} className="text-success" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="col-sm-6 col-lg-3">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Успешность</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{stats?.success_rate || 0}%</div>
                                            <div className="me-auto">
                                                <IconCheck size={24} className="text-info" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="col-sm-6 col-lg-3">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Экономия времени</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{stats?.total_savings || 0}ч</div>
                                            <div className="me-auto">
                                                <IconCurrencyDollar size={24} className="text-warning" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Recent Documents */}
                        <div className="row row-cards mt-3">
                            <div className="col-12">
                                <div className="card">
                                    <div className="card-header">
                                        <h3 className="card-title">Последние документы</h3>
                                        <div className="card-actions">
                                            <Link href="/documents" className="btn btn-outline-primary btn-sm">
                                                Все документы
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="card-body p-0">
                                        {recentDocuments.length > 0 ? (
                                            <div className="table-responsive">
                                                <table className="table table-vcenter card-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Документ</th>
                                                            <th>Статус</th>
                                                            <th>Страниц</th>
                                                            <th>Дата загрузки</th>
                                                            <th className="w-1"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {recentDocuments.map((document) => (
                                                            <tr key={document.id}>
                                                                <td>
                                                                    <div className="d-flex align-items-center">
                                                                        <IconFile className="me-2 text-muted" size={16} />
                                                                        <div>
                                                                            <div className="font-weight-medium">
                                                                                {document.title || `Документ #${document.id}`}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span className={getStatusBadgeClass(document.status)}>
                                                                        <div className="d-flex align-items-center">
                                                                            {getStatusIcon(document.status)}
                                                                            <span className="ms-1">{getStatusText(document.status)}</span>
                                                                        </div>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span className="text-muted">
                                                                        {document.pages_count || '—'}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span className="text-muted">
                                                                        {new Date(document.created_at).toLocaleDateString('ru-RU')}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div className="dropdown">
                                                                        <button
                                                                            className="btn btn-outline-secondary btn-sm dropdown-toggle"
                                                                            data-bs-toggle="dropdown"
                                                                        >
                                                                            Действия
                                                                        </button>
                                                                        <div className="dropdown-menu dropdown-menu-end">
                                                                            <Link
                                                                                className="dropdown-item"
                                                                                href={`/documents/${document.id}`}
                                                                            >
                                                                                Просмотреть
                                                                            </Link>
                                                                            {document.status === 'completed' && (
                                                                                <Link
                                                                                    className="dropdown-item"
                                                                                    href={`/documents/${document.id}/download`}
                                                                                >
                                                                                    Скачать
                                                                                </Link>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="empty">
                                                <div className="empty-img">
                                                    <IconFile size={96} className="text-muted" />
                                                </div>
                                                <p className="empty-title">У вас пока нет документов</p>
                                                <p className="empty-subtitle text-muted">
                                                    Загрузите ваш первый договор для анализа и перевода на простой язык.
                                                </p>
                                                <div className="empty-action">
                                                    <Link
                                                        href="/documents/upload"
                                                        className="btn btn-primary"
                                                    >
                                                        <IconPlus className="me-1" size={16} />
                                                        Загрузить документ
                                                    </Link>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="row row-cards mt-3">
                            <div className="col-md-6 col-lg-4">
                                <div className="card card-link">
                                    <Link href="/documents/upload" className="d-block">
                                        <div className="card-body">
                                            <div className="d-flex align-items-center">
                                                <span className="avatar avatar-md me-3 bg-primary-lt">
                                                    <IconPlus size={24} />
                                                </span>
                                                <div>
                                                    <div className="font-weight-medium">Загрузить документ</div>
                                                    <div className="text-muted">Добавить новый договор для анализа</div>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>

                            <div className="col-md-6 col-lg-4">
                                <div className="card card-link">
                                    <Link href="/documents" className="d-block">
                                        <div className="card-body">
                                            <div className="d-flex align-items-center">
                                                <span className="avatar avatar-md me-3 bg-info-lt">
                                                    <IconFile size={24} />
                                                </span>
                                                <div>
                                                    <div className="font-weight-medium">Мои документы</div>
                                                    <div className="text-muted">Просмотр всех загруженных файлов</div>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>

                            <div className="col-md-6 col-lg-4">
                                <div className="card card-link">
                                    <Link href="/profile" className="d-block">
                                        <div className="card-body">
                                            <div className="d-flex align-items-center">
                                                <span className="avatar avatar-md me-3 bg-success-lt">
                                                    <IconUsers size={24} />
                                                </span>
                                                <div>
                                                    <div className="font-weight-medium">Профиль</div>
                                                    <div className="text-muted">Настройки аккаунта и тарифы</div>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}