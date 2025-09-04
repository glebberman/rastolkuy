import React, { useCallback, useState } from 'react';
import { IconCloudUpload, IconFile, IconX } from '@tabler/icons-react';

interface FileUploadZoneProps {
    onFileSelect: (file: File) => void;
    acceptedTypes?: string[];
    maxSizeMB?: number;
    isUploading?: boolean;
    disabled?: boolean;
}

export default function FileUploadZone({
    onFileSelect,
    acceptedTypes = ['.pdf', '.docx', '.txt'],
    maxSizeMB = 50,
    isUploading = false,
    disabled = false
}: FileUploadZoneProps) {
    const [isDragOver, setIsDragOver] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (!disabled) {
            setIsDragOver(true);
        }
    }, [disabled]);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const validateFile = useCallback((file: File): string | null => {
        // Check file size
        const maxSizeBytes = maxSizeMB * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            return `Файл слишком большой. Максимальный размер: ${maxSizeMB} МБ`;
        }

        // Check file type
        const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
        if (!acceptedTypes.some(type => type.toLowerCase() === fileExtension)) {
            return `Неподдерживаемый тип файла. Поддерживаются: ${acceptedTypes.join(', ')}`;
        }

        return null;
    }, [acceptedTypes, maxSizeMB]);

    const handleFileSelection = useCallback((file: File) => {
        setError(null);
        const validationError = validateFile(file);
        if (validationError) {
            setError(validationError);
            return;
        }

        onFileSelect(file);
    }, [validateFile, onFileSelect]);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);

        if (disabled) return;

        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    }, [disabled, handleFileSelection]);

    const handleFileInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            handleFileSelection(files[0]);
        }
    }, [handleFileSelection]);

    const handleClick = useCallback(() => {
        if (disabled) return;
        document.getElementById('file-upload-input')?.click();
    }, [disabled]);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="card-title">Загрузка документа</h3>
                <div className="card-actions">
                    <a href="/documents" className="btn btn-outline-primary btn-sm">
                        Все документы
                    </a>
                </div>
            </div>
            <div className="card-body">
                <div
                    className={`upload-zone ${isDragOver ? 'drag-over' : ''} ${disabled ? 'disabled' : ''} ${error ? 'error' : ''}`}
                    onDragEnter={handleDragEnter}
                    onDragLeave={handleDragLeave}
                    onDragOver={handleDragOver}
                    onDrop={handleDrop}
                    onClick={handleClick}
                    style={{
                        border: '2px dashed #dee2e6',
                        borderRadius: '8px',
                        padding: '3rem 2rem',
                        textAlign: 'center',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        transition: 'all 0.2s ease',
                        backgroundColor: isDragOver ? '#f8f9fa' : 'transparent',
                        borderColor: error ? '#dc3545' : isDragOver ? '#0d6efd' : '#dee2e6'
                    }}
                >
                    <input
                        id="file-upload-input"
                        type="file"
                        accept={acceptedTypes.join(',')}
                        onChange={handleFileInputChange}
                        style={{ display: 'none' }}
                        disabled={disabled}
                    />

                    {isUploading ? (
                        <>
                            <div className="spinner-border text-primary mb-3" role="status">
                                <span className="visually-hidden">Загрузка...</span>
                            </div>
                            <div className="h4 mb-2">Загружается...</div>
                            <p className="text-muted mb-0">
                                Пожалуйста, подождите
                            </p>
                        </>
                    ) : (
                        <>
                            <IconCloudUpload size={48} className={`mb-3 ${error ? 'text-danger' : 'text-muted'}`} />
                            <div className="h4 mb-2">
                                {isDragOver ? 'Отпустите файл для загрузки' : 'Перетащите файл сюда или нажмите для выбора'}
                            </div>
                            <p className="text-muted mb-3">
                                Поддерживаются файлы: {acceptedTypes.join(', ')}
                            </p>
                            <p className="text-muted mb-0">
                                Максимальный размер: {maxSizeMB} МБ
                            </p>
                        </>
                    )}

                    {error && (
                        <div className="mt-3">
                            <div className="alert alert-danger d-flex align-items-center" role="alert">
                                <IconFile size={16} className="me-2" />
                                <div className="flex-grow-1">{error}</div>
                                <button
                                    type="button"
                                    className="btn-close"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        clearError();
                                    }}
                                    aria-label="Close"
                                ></button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="mt-3 text-center text-muted small">
                    <IconFile size={16} className="me-1" />
                    После загрузки автоматически начнется анализ структуры документа
                </div>
            </div>
        </div>
    );
}