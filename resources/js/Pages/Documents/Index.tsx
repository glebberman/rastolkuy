import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { IconPlus, IconFile } from '@tabler/icons-react';
import AppLayout from '@/Layouts/AppLayout';

export default function DocumentsIndex() {
    return (
        <AppLayout title="Документы">
            <Head title="Документы" />

            <div className="page-wrapper">
                <div className="page-header d-print-none">
                    <div className="container-xl">
                        <div className="row g-2 align-items-center">
                            <div className="col">
                                <h2 className="page-title">
                                    Документы
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="page-body">
                    <div className="container-xl">
                        <div className="row row-cards">
                            <div className="col-12">
                                <div className="card">
                                    <div className="card-body">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}