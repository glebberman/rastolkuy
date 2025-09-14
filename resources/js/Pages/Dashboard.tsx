import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { 
    IconFile, 
    IconClock, 
    IconCheck, 
    IconAlertTriangle,
    IconTrendingUp,
    IconUsers,
    IconCoins,
    IconPlus,
    IconSettings,
    IconLogout,
    IconDownload,
    IconTrash
} from '@tabler/icons-react';
import { authService } from '@/Utils/auth';
import FileUploadZone from '../Components/Document/FileUploadZone';
import DocumentProcessor from '../Components/Document/DocumentProcessor';

interface Document {
    id: string;
    filename: string;
    status: 'uploaded' | 'estimated' | 'pending' | 'processing' | 'completed' | 'failed';
    status_description: string;
    cost_usd: number | null;
    created_at: string;
    file_type: string;
    task_description: string;
    estimation?: {
        credits_needed?: number;
        estimated_cost_usd?: number;
    };
}

interface DocumentsResponse {
    data: Document[];
    meta: {
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
}

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
    const { props } = usePage<{ config: { polling: { dashboard: { credits_refresh_interval: number } } } }>();
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [currentStats, setCurrentStats] = useState(stats);
    const [showProcessor, setShowProcessor] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [documents, setDocuments] = useState<Document[]>([]);
    const [documentsPagination, setDocumentsPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0
    });
    const [documentsLoading, setDocumentsLoading] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<Document | null>(null);

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

    // Auto-refresh credits balance periodically
    useEffect(() => {
        if (!isAuthenticated) return;

        const refreshCredits = async () => {
            try {
                const response = await axios.get('/api/v1/user/stats');
                if (response.data?.data) {
                    const { credits_balance, total_documents, processed_today } = response.data.data;
                    setCurrentStats(prev => ({
                        ...prev,
                        credits_balance: credits_balance ?? prev.credits_balance,
                        total_documents: total_documents ?? prev.total_documents,
                        processed_today: processed_today ?? prev.processed_today,
                    }));
                }
            } catch (error) {
                console.error('Failed to refresh stats:', error);
            }
        };

        // Initial stats load
        refreshCredits();

        // Refresh credits based on configuration
        const refreshInterval = (props.config?.polling?.dashboard?.credits_refresh_interval || 30) * 1000;
        const interval = setInterval(refreshCredits, refreshInterval);
        
        return () => clearInterval(interval);
    }, [isAuthenticated]);

    // Load documents when authenticated
    useEffect(() => {
        if (isAuthenticated) {
            loadDocuments();
        }
    }, [isAuthenticated]);

    const handleStartNewUpload = () => {
        setSelectedFile(null);
        setShowProcessor(false);
    };

    const handleDocumentComplete = (documentId: string) => {
        // Refresh documents list and stats
        loadDocuments(documentsPagination.current_page);
        console.log('Document completed:', documentId);
    };

    const loadDocuments = async (page: number = 1) => {
        if (!isAuthenticated) return;
        
        setDocumentsLoading(true);
        try {
            const response = await axios.get(`/api/v1/documents?page=${page}&per_page=20`);
            if (response.data?.data && response.data?.meta?.pagination) {
                setDocuments(response.data.data);
                setDocumentsPagination(response.data.meta.pagination);
            }
        } catch (error) {
            console.error('Failed to load documents:', error);
        } finally {
            setDocumentsLoading(false);
        }
    };

    const handleProcessDocument = async (documentId: string) => {
        try {
            await axios.post(`/api/v1/documents/${documentId}/process`);
            loadDocuments(documentsPagination.current_page);
        } catch (error) {
            console.error('Failed to process document:', error);
        }
    };

    const handleDownloadDocument = async (documentId: string) => {
        try {
            const response = await axios.get(`/api/v1/documents/${documentId}/result`, {
                responseType: 'blob'
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'processed_document.html');
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (error) {
            console.error('Failed to download document:', error);
        }
    };

    const confirmDeleteDocument = (document: Document) => {
        setDocumentToDelete(document);
        setShowDeleteModal(true);
    };

    const handleDeleteDocument = async () => {
        if (!documentToDelete) return;
        
        try {
            await axios.delete(`/api/v1/documents/${documentToDelete.id}`);
            setShowDeleteModal(false);
            setDocumentToDelete(null);
            loadDocuments(documentsPagination.current_page);
        } catch (error) {
            console.error('Failed to delete document:', error);
        }
    };

    const handleLogout = async () => {
        try {
            await axios.post('/api/logout');
            authService.logout();
            router.visit('/login');
        } catch (error) {
            console.error('Logout failed:', error);
            authService.logout();
            router.visit('/login');
        }
    };

    if (isLoading) {
        return (
            <div className="min-vh-100 d-flex align-items-center justify-content-center">
                <div className="text-center">
                    <div className="spinner-border text-primary" role="status">
                        <span className="visually-hidden">Загрузка...</span>
                    </div>
                    <div className="mt-3">Загрузка...</div>
                </div>
            </div>
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
        <div className="min-vh-100 d-flex flex-column">
            <Head title="Растолкуй" />
            
            {/* Compact Header */}
            <header className="bg-white border-bottom py-3">
                <div className="container-fluid">
                    <div className="d-flex justify-content-end align-items-center gap-3">
                        {/* Credits */}
                        <div className="d-flex align-items-center text-muted">
                            <IconCoins size={20} className="me-1" />
                            <span className="fw-medium">{currentStats?.credits_balance || 0}&nbsp;кр.</span>
                        </div>
                        
                        {/* Add Button */}
                        <button 
                            className="btn btn-outline-primary btn-sm d-flex align-items-center"
                            onClick={() => {/* TODO: Add credits functionality */}}
                            title="Пополнить кредиты"
                        >
                            <IconPlus size={16} />
                        </button>
                        
                        {/* Settings Button */}
                        <button 
                            className="btn btn-outline-secondary btn-sm d-flex align-items-center"
                            onClick={() => router.visit('/profile')}
                            title="Настройки"
                        >
                            <IconSettings size={16} />
                        </button>
                        
                        {/* Logout Button */}
                        <button 
                            className="btn btn-outline-danger btn-sm d-flex align-items-center"
                            onClick={handleLogout}
                            title="Выйти"
                        >
                            <IconLogout size={16} />
                        </button>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex-grow-1 py-4">
                <div className="container-fluid">
                    {/* Upload/Processing Zone */}
                    <div className="row">
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

                    {/* Documents Table */}
                    <div className="row mt-4">
                        <div className="col-12">
                            <div className="card">
                                <div className="card-header">
                                    <h3 className="card-title">Мои документы</h3>
                                </div>
                                <div className="card-body p-0">
                                    {documentsLoading ? (
                                        <div className="text-center py-4">
                                            <div className="spinner-border spinner-border-sm" role="status">
                                                <span className="visually-hidden">Загрузка...</span>
                                            </div>
                                        </div>
                                    ) : documents.length === 0 ? (
                                        <div className="text-center py-5 text-muted">
                                            <IconFile size={48} className="mb-2" />
                                            <div>У вас пока нет загруженных документов</div>
                                        </div>
                                    ) : (
                                        <div className="table-responsive">
                                            <table className="table table-hover table-vcenter">
                                                <thead>
                                                    <tr>
                                                        <th>Документ</th>
                                                        <th>Статус</th>
                                                        <th>Стоимость</th>
                                                        <th>Дата</th>
                                                        <th style={{ width: '150px' }}>Действия</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {documents.map((document) => (
                                                        <tr key={document.id}>
                                                            <td>
                                                                <div className="d-flex align-items-center">
                                                                    {getStatusIcon(document.status)}
                                                                    <div className="ms-2">
                                                                        <div className="fw-medium">{document.filename}</div>
                                                                        <div className="text-muted small">{document.task_description}</div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span className={getStatusBadgeClass(document.status)}>
                                                                    {document.status_description}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                {document.estimation?.credits_needed 
                                                                    ? `${document.estimation.credits_needed.toFixed(0)} кр.` 
                                                                    : document.cost_usd 
                                                                        ? `${Math.ceil(document.cost_usd * 100)} кр.`
                                                                        : '—'
                                                                }
                                                            </td>
                                                            <td className="text-muted">
                                                                {new Date(document.created_at).toLocaleDateString('ru-RU')}
                                                            </td>
                                                            <td>
                                                                <div className="btn-group btn-group-sm">
                                                                    {document.status === 'completed' && (
                                                                        <button
                                                                            className="btn btn-outline-success"
                                                                            onClick={() => handleDownloadDocument(document.id)}
                                                                            title="Скачать"
                                                                        >
                                                                            <IconDownload size={14} />
                                                                        </button>
                                                                    )}
                                                                    {(document.status === 'uploaded' || document.status === 'estimated') && (
                                                                        <button
                                                                            className="btn btn-outline-primary"
                                                                            onClick={() => handleProcessDocument(document.id)}
                                                                            title="Обработать"
                                                                        >
                                                                            Обработать
                                                                        </button>
                                                                    )}
                                                                    <button
                                                                        className="btn btn-outline-danger"
                                                                        onClick={() => confirmDeleteDocument(document)}
                                                                        title="Удалить"
                                                                    >
                                                                        <IconTrash size={14} />
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                                
                                {/* Pagination */}
                                {documentsPagination.last_page > 1 && (
                                    <div className="card-footer d-flex align-items-center justify-content-between">
                                        <div className="text-muted">
                                            Всего документов: {documentsPagination.total}
                                        </div>
                                        <nav>
                                            <ul className="pagination pagination-sm m-0">
                                                <li className={`page-item ${documentsPagination.current_page === 1 ? 'disabled' : ''}`}>
                                                    <button 
                                                        className="page-link"
                                                        onClick={() => loadDocuments(documentsPagination.current_page - 1)}
                                                        disabled={documentsPagination.current_page === 1}
                                                    >
                                                        Назад
                                                    </button>
                                                </li>
                                                
                                                {Array.from({ length: documentsPagination.last_page }, (_, i) => i + 1)
                                                    .filter(page => 
                                                        page === 1 || 
                                                        page === documentsPagination.last_page ||
                                                        Math.abs(page - documentsPagination.current_page) <= 2
                                                    )
                                                    .map((page, index, array) => {
                                                        const prevPage = array[index - 1];
                                                        return (
                                                            <React.Fragment key={page}>
                                                                {prevPage && page - prevPage > 1 && (
                                                                    <li className="page-item disabled">
                                                                        <span className="page-link">...</span>
                                                                    </li>
                                                                )}
                                                                <li className={`page-item ${documentsPagination.current_page === page ? 'active' : ''}`}>
                                                                    <button 
                                                                        className="page-link"
                                                                        onClick={() => loadDocuments(page)}
                                                                    >
                                                                        {page}
                                                                    </button>
                                                                </li>
                                                            </React.Fragment>
                                                        );
                                                    })}
                                                
                                                <li className={`page-item ${documentsPagination.current_page === documentsPagination.last_page ? 'disabled' : ''}`}>
                                                    <button 
                                                        className="page-link"
                                                        onClick={() => loadDocuments(documentsPagination.current_page + 1)}
                                                        disabled={documentsPagination.current_page === documentsPagination.last_page}
                                                    >
                                                        Далее
                                                    </button>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            {/* Delete Confirmation Modal */}
            {showDeleteModal && documentToDelete && (
                <div className="modal d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Подтверждение удаления</h5>
                                <button 
                                    type="button" 
                                    className="btn-close" 
                                    onClick={() => setShowDeleteModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <p>Вы действительно хотите удалить документ <strong>{documentToDelete.filename}</strong>?</p>
                                <p className="text-muted small">Это действие необратимо.</p>
                            </div>
                            <div className="modal-footer">
                                <button 
                                    type="button" 
                                    className="btn btn-secondary" 
                                    onClick={() => setShowDeleteModal(false)}
                                >
                                    Отмена
                                </button>
                                <button 
                                    type="button" 
                                    className="btn btn-danger" 
                                    onClick={handleDeleteDocument}
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}