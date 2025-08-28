import React from 'react';
import { Link } from '@inertiajs/react';
import { IconGavel, IconMail, IconPhone } from '@tabler/icons-react';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="bg-light border-top mt-auto">
            <div className="container-fluid py-4">
                <div className="row">
                    {/* Company Info */}
                    <div className="col-lg-4 mb-3 mb-lg-0">
                        <div className="d-flex align-items-center mb-3">
                            <IconGavel className="me-2" size={24} />
                            <span className="fw-bold fs-5">Legal Translator</span>
                        </div>
                        <p className="text-muted mb-0">
                            Автоматический перевод юридических документов на простой человеческий язык.
                            Анализ рисков и подводных камней.
                        </p>
                    </div>

                    {/* Quick Links */}
                    <div className="col-lg-2 col-md-6 mb-3 mb-md-0">
                        <h6 className="fw-bold mb-3">Продукт</h6>
                        <ul className="list-unstyled">
                            <li className="mb-2">
                                <Link href="/how-it-works" className="text-muted text-decoration-none">
                                    Как это работает
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/pricing" className="text-muted text-decoration-none">
                                    Тарифы
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/api" className="text-muted text-decoration-none">
                                    API
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Support */}
                    <div className="col-lg-2 col-md-6 mb-3 mb-md-0">
                        <h6 className="fw-bold mb-3">Поддержка</h6>
                        <ul className="list-unstyled">
                            <li className="mb-2">
                                <Link href="/help" className="text-muted text-decoration-none">
                                    Помощь
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/faq" className="text-muted text-decoration-none">
                                    FAQ
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/contact" className="text-muted text-decoration-none">
                                    Контакты
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Legal */}
                    <div className="col-lg-2 col-md-6 mb-3 mb-md-0">
                        <h6 className="fw-bold mb-3">Правовая информация</h6>
                        <ul className="list-unstyled">
                            <li className="mb-2">
                                <Link href="/terms" className="text-muted text-decoration-none">
                                    Условия использования
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/privacy" className="text-muted text-decoration-none">
                                    Политика конфиденциальности
                                </Link>
                            </li>
                            <li className="mb-2">
                                <Link href="/security" className="text-muted text-decoration-none">
                                    Безопасность
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Contact */}
                    <div className="col-lg-2 col-md-6">
                        <h6 className="fw-bold mb-3">Контакты</h6>
                        <ul className="list-unstyled">
                            <li className="mb-2 d-flex align-items-center">
                                <IconMail size={16} className="me-2 text-muted" />
                                <a href="mailto:support@legaltranslator.ru" className="text-muted text-decoration-none">
                                    support@legaltranslator.ru
                                </a>
                            </li>
                            <li className="mb-2 d-flex align-items-center">
                                <IconPhone size={16} className="me-2 text-muted" />
                                <a href="tel:+78001234567" className="text-muted text-decoration-none">
                                    +7 (800) 123-45-67
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <hr className="my-4" />

                {/* Bottom Row */}
                <div className="row align-items-center">
                    <div className="col-md-6">
                        <p className="text-muted mb-0">
                            © {currentYear} Legal Translator. Все права защищены.
                        </p>
                    </div>
                    <div className="col-md-6 text-md-end mt-2 mt-md-0">
                        <span className="text-muted small">
                            Версия 1.0.0 | Статус системы: 
                            <span className="text-success ms-1">Все системы работают</span>
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    );
}