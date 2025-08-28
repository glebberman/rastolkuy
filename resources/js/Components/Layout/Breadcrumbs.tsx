import React from 'react';
import { Link } from '@inertiajs/react';
import { IconHome, IconChevronRight } from '@tabler/icons-react';

interface BreadcrumbItem {
    label: string;
    url?: string;
}

interface Props {
    items: BreadcrumbItem[];
}

export default function Breadcrumbs({ items }: Props) {
    if (items.length === 0) return null;

    return (
        <nav aria-label="breadcrumb">
            <ol className="breadcrumb mb-0">
                {/* Home link */}
                <li className="breadcrumb-item">
                    <Link
                        href="/dashboard"
                        className="text-decoration-none d-flex align-items-center"
                    >
                        <IconHome size={16} className="me-1" />
                        Главная
                    </Link>
                </li>

                {/* Dynamic items */}
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li
                            key={index}
                            className={`breadcrumb-item ${isLast ? 'active' : ''}`}
                            {...(isLast && { 'aria-current': 'page' })}
                        >
                            {!isLast && item.url ? (
                                <Link
                                    href={item.url}
                                    className="text-decoration-none"
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <span>{item.label}</span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}