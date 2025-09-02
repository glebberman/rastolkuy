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
    IconCoins
} from '@tabler/icons-react';
import AppLayout from '../Layouts/AppLayout';
import { authService } from '@/Utils/auth';
import FileUploadZone from '../Components/Document/FileUploadZone';
import DocumentProcessor from '../Components/Document/DocumentProcessor';

interface DashboardProps {
    recentDocuments: Array<{
        id: number;
        title: string;
        status: 'pending' | 'processing' | 'completed' | 'failed';
        created_at: string;
        pages_count?: number;
    }>;
    stats: {
        credits_balance: number;
        total_documents: number;
        processed_today: number;
    };
}

export default function Dashboard({ recentDocuments = [], stats }: DashboardProps) {
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [currentStats, setCurrentStats] = useState(stats);
    const [showProcessor, setShowProcessor] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    useEffect(() => {
        const checkAuth = async () => {
            console.log('Dashboard: Checking authentication...');
            console.log('Dashboard: Token exists:', !!authService.getToken());
            console.log('Dashboard: User exists:', !!authService.getUser());
            console.log('Dashboard: Is authenticated:', authService.isAuthenticated());
            
            if (!authService.isAuthenticated()) {
                console.log('Dashboard: Not authenticated, redirecting to login');
                router.visit('/login');
                return;
            }

            try {
                console.log('Dashboard: Getting current user...');
                await authService.getCurrentUser();
                console.log('Dashboard: Authentication successful');
                setIsAuthenticated(true);
            } catch (error) {
                console.log('Dashboard: Get current user failed:', error);
                router.visit('/login');
            } finally {
                setIsLoading(false);
            }
        };

        checkAuth();
    }, []);

    const handleFileSelect = (file: File) => {
        setSelectedFile(file);
        setShowProcessor(true);
    };

    const handleCreditsUpdated = (newBalance: number) => {
        setCurrentStats(prev => ({
            ...prev,
            credits_balance: newBalance
        }));
    };

    const handleStartNewUpload = () => {
        setSelectedFile(null);
        setShowProcessor(false);
    };

    const handleDocumentComplete = (documentId: string) => {
        // Could refresh stats here if needed
        console.log('Document completed:', documentId);
    };

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
                            <div className="col-sm-6 col-lg-4">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Кредиты</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{currentStats?.credits_balance || 0} кр.</div>
                                            <div className="me-auto">
                                                <IconCoins size={24} className="text-primary" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="col-sm-6 col-lg-4">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Всего документов</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{currentStats?.total_documents || 0}</div>
                                            <div className="me-auto">
                                                <IconFile size={24} className="text-muted" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="col-sm-6 col-lg-4">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="d-flex align-items-center">
                                            <div className="subheader">Обработано сегодня</div>
                                        </div>
                                        <div className="d-flex align-items-baseline">
                                            <div className="h1 mb-0 me-2">{currentStats?.processed_today || 0}</div>
                                            <div className="me-auto">
                                                <IconTrendingUp size={24} className="text-success" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Central Upload/Processing Zone */}
                        <div className="row row-cards mt-3">
                            <div className="col-12">
                                {showProcessor ? (
                                    <DocumentProcessor
                                        selectedFile={selectedFile || undefined}
                                        onDocumentComplete={handleDocumentComplete}
                                        onStartNewUpload={handleStartNewUpload}
                                        onCreditsUpdated={handleCreditsUpdated}
                                    />
                                ) : (
                                    <FileUploadZone
                                        onFileSelect={handleFileSelect}
                                        acceptedTypes={['.pdf', '.docx', '.txt']}
                                        maxSizeMB={50}
                                    />
                                )}
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