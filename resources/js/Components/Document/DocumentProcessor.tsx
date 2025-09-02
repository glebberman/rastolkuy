import React, { useState, useEffect, useCallback } from 'react';
import { 
    IconFile, 
    IconCheck, 
    IconTrash, 
    IconDownload, 
    IconEye,
    IconCloudUpload,
    IconCoins,
    IconExclamationCircle
} from '@tabler/icons-react';
import axios from 'axios';

interface DocumentProcessorProps {
    selectedFile?: File;
    onDocumentComplete?: (documentId: string) => void;
    onStartNewUpload?: () => void;
    onCreditsUpdated?: (newBalance: number) => void;
}

type ProcessingState = 
    | 'idle'
    | 'uploading'
    | 'analyzing'
    | 'ready'
    | 'processing'
    | 'completed'
    | 'failed'
    | 'insufficient_credits';

interface ProcessingData {
    documentId?: string;
    filename?: string;
    estimatedCost?: number;
    creditsNeeded?: number;
    hasSufficientBalance?: boolean;
    currentBalance?: number;
    resultUrl?: string;
    downloadUrl?: string;
    progress?: number;
}

export default function DocumentProcessor({
    selectedFile,
    onDocumentComplete,
    onStartNewUpload,
    onCreditsUpdated
}: DocumentProcessorProps) {
    const [state, setState] = useState<ProcessingState>('idle');
    const [data, setData] = useState<ProcessingData>({});
    const [error, setError] = useState<string | null>(null);
    const [pollingInterval, setPollingInterval] = useState<NodeJS.Timeout | null>(null);

    // Clear polling on unmount
    useEffect(() => {
        return () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        };
    }, [pollingInterval]);

    // Auto-start upload when file is selected
    useEffect(() => {
        if (selectedFile && state === 'idle') {
            handleFileUpload(selectedFile);
        }
    }, [selectedFile, state]);

    const startPolling = useCallback((documentId: string) => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        const interval = setInterval(async () => {
            try {
                const response = await axios.get(`/api/v1/documents/${documentId}/status`);
                const documentData = response.data.data;

                if (documentData.status === 'completed') {
                    setState('completed');
                    setData(prev => ({
                        ...prev,
                        resultUrl: `/documents/${documentId}`,
                        downloadUrl: `/documents/${documentId}/download`
                    }));
                    clearInterval(interval);
                    onDocumentComplete?.(documentId);
                } else if (documentData.status === 'failed') {
                    setState('failed');
                    setError('Обработка документа завершилась с ошибкой');
                    clearInterval(interval);
                } else if (documentData.status === 'processing') {
                    setState('processing');
                    setData(prev => ({
                        ...prev,
                        progress: documentData.progress_percentage || 50
                    }));
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }, 5000); // 5 seconds as per RAS-17 requirements

        setPollingInterval(interval);
    }, [pollingInterval, onDocumentComplete]);

    const stopPolling = useCallback(() => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            setPollingInterval(null);
        }
    }, [pollingInterval]);

    const handleFileUpload = useCallback(async (file: File) => {
        setError(null);
        setState('uploading');
        setData({ filename: file.name });

        try {
            // Step 1: Upload file
            const uploadData = new FormData();
            uploadData.append('file', file);
            uploadData.append('task_type', 'translation');
            uploadData.append('anchor_at_start', 'false');

            const uploadResponse = await axios.post('/api/v1/documents/upload', uploadData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            const documentId = uploadResponse.data.data.id;
            setData(prev => ({ ...prev, documentId }));

            // Step 2: Start analyzing (estimating cost)
            setState('analyzing');

            const estimateResponse = await axios.post(`/api/v1/documents/${documentId}/estimate`, {
                task_type: 'translation',
                anchor_at_start: false
            });

            const estimation = estimateResponse.data.data.estimation;

            setData(prev => ({
                ...prev,
                estimatedCost: estimation.estimated_cost_usd,
                creditsNeeded: estimation.credits_needed,
                hasSufficientBalance: estimation.has_sufficient_balance,
                currentBalance: estimation.current_balance
            }));

            if (estimation.has_sufficient_balance) {
                setState('ready');
            } else {
                setState('insufficient_credits');
            }

        } catch (err: any) {
            console.error('Upload/estimation error:', err);
            setError(err.response?.data?.message || 'Ошибка при загрузке файла');
            setState('failed');
        }
    }, []);

    const handleStartProcessing = useCallback(async () => {
        if (!data.documentId) return;

        setError(null);
        setState('processing');

        try {
            await axios.post(`/api/v1/documents/${data.documentId}/process`);
            
            // Update credits balance
            if (data.creditsNeeded && data.currentBalance !== undefined) {
                const newBalance = data.currentBalance - data.creditsNeeded;
                onCreditsUpdated?.(newBalance);
            }

            // Start polling for status
            startPolling(data.documentId);

        } catch (err: any) {
            console.error('Processing error:', err);
            setError(err.response?.data?.message || 'Ошибка при запуске обработки');
            setState('failed');
        }
    }, [data.documentId, data.creditsNeeded, data.currentBalance, onCreditsUpdated, startPolling]);

    const handleDeleteDocument = useCallback(async () => {
        if (!data.documentId) return;

        try {
            await axios.delete(`/api/v1/documents/${data.documentId}`);
            setState('idle');
            setData({});
            setError(null);
            stopPolling();
        } catch (err: any) {
            console.error('Delete error:', err);
            setError(err.response?.data?.message || 'Ошибка при удалении документа');
        }
    }, [data.documentId, stopPolling]);

    const handleStartNewUpload = useCallback(() => {
        setState('idle');
        setData({});
        setError(null);
        stopPolling();
        onStartNewUpload?.();
    }, [stopPolling, onStartNewUpload]);

    const renderContent = () => {
        switch (state) {
            case 'uploading':
                return (
                    <div className="text-center py-4">
                        <div className="spinner-border text-primary mb-3" role="status">
                            <span className="visually-hidden">Загрузка...</span>
                        </div>
                        <div className="h5">Загрузка файла...</div>
                        <p className="text-muted">{data.filename}</p>
                    </div>
                );

            case 'analyzing':
                return (
                    <div className="text-center py-4">
                        <div className="spinner-border text-info mb-3" role="status">
                            <span className="visually-hidden">Анализ...</span>
                        </div>
                        <div className="h5">Анализ структуры документа...</div>
                        <p className="text-muted">Определение секций и якорей</p>
                    </div>
                );

            case 'ready':
                return (
                    <div className="text-center py-4">
                        <IconFile size={48} className="text-success mb-3" />
                        <div className="h5 mb-3">{data.filename}</div>
                        <div className="d-flex justify-content-center gap-2">
                            <button 
                                className="btn btn-primary"
                                onClick={handleStartProcessing}
                            >
                                <IconCoins size={16} className="me-1" />
                                Обработать за {data.creditsNeeded} кр.
                            </button>
                            <button 
                                className="btn btn-outline-secondary"
                                onClick={handleDeleteDocument}
                            >
                                <IconTrash size={16} className="me-1" />
                                Удалить
                            </button>
                        </div>
                    </div>
                );

            case 'insufficient_credits':
                return (
                    <div className="text-center py-4">
                        <IconExclamationCircle size={48} className="text-warning mb-3" />
                        <div className="h5 mb-3">{data.filename}</div>
                        <p className="text-muted mb-3">
                            Требуется: {data.creditsNeeded} кр. | Доступно: {data.currentBalance} кр.
                        </p>
                        <div className="d-flex justify-content-center gap-2 mb-3">
                            <button className="btn btn-primary disabled">
                                <IconCoins size={16} className="me-1" />
                                Обработать за {data.creditsNeeded} кр.
                            </button>
                            <button 
                                className="btn btn-outline-secondary"
                                onClick={handleDeleteDocument}
                            >
                                <IconTrash size={16} className="me-1" />
                                Удалить
                            </button>
                        </div>
                        <a href="/profile" className="btn btn-outline-primary btn-sm">
                            Пополнить баланс
                        </a>
                    </div>
                );

            case 'processing':
                return (
                    <div className="text-center py-4">
                        <div className="progress mb-3" style={{ height: '8px' }}>
                            <div 
                                className="progress-bar progress-bar-striped progress-bar-animated" 
                                style={{ width: `${data.progress || 50}%` }}
                            />
                        </div>
                        <div className="h5">Обработка документа...</div>
                        <p className="text-muted">Перевод на простой язык и выявление рисков</p>
                    </div>
                );

            case 'completed':
                return (
                    <div className="text-center py-4">
                        <IconCheck size={48} className="text-success mb-3" />
                        <div className="h5 mb-3">Обработка завершена!</div>
                        <div className="d-flex justify-content-center gap-2 mb-3">
                            <a href={data.resultUrl} className="btn btn-primary">
                                <IconEye size={16} className="me-1" />
                                Просмотреть
                            </a>
                            <a href={data.downloadUrl} className="btn btn-outline-primary">
                                <IconDownload size={16} className="me-1" />
                                Скачать
                            </a>
                        </div>
                        <button 
                            className="btn btn-outline-secondary btn-sm"
                            onClick={handleStartNewUpload}
                        >
                            <IconCloudUpload size={16} className="me-1" />
                            Загрузить другой документ
                        </button>
                    </div>
                );

            case 'failed':
                return (
                    <div className="text-center py-4">
                        <IconExclamationCircle size={48} className="text-danger mb-3" />
                        <div className="h5 mb-3">Ошибка обработки</div>
                        {error && <p className="text-danger mb-3">{error}</p>}
                        <div className="d-flex justify-content-center gap-2">
                            <button 
                                className="btn btn-primary"
                                onClick={handleStartNewUpload}
                            >
                                <IconCloudUpload size={16} className="me-1" />
                                Попробовать снова
                            </button>
                            {data.documentId && (
                                <button 
                                    className="btn btn-outline-secondary"
                                    onClick={handleDeleteDocument}
                                >
                                    <IconTrash size={16} className="me-1" />
                                    Удалить
                                </button>
                            )}
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <div className="card">
            <div className="card-body">
                {renderContent()}
            </div>
        </div>
    );
}