import React, { ReactNode } from 'react';
import { Head } from '@inertiajs/react';

interface Props {
    children: ReactNode;
    title?: string;
}

export default function AuthLayout({ children, title = 'Аутентификация' }: Props) {
    return (
        <>
            <Head title={title} />
            
            <div className="auth-page">
                {children}
            </div>
        </>
    );
}