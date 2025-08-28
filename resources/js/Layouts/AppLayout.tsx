import React, { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import Header from '../Components/Layout/Header';
import Footer from '../Components/Layout/Footer';
import Breadcrumbs from '../Components/Layout/Breadcrumbs';

interface Props {
    children: ReactNode;
    title?: string;
    showBreadcrumbs?: boolean;
    breadcrumbs?: Array<{ label: string; url?: string }>;
}

export default function AppLayout({ children, title = 'Legal Translator', showBreadcrumbs = true, breadcrumbs = [] }: Props) {
    return (
        <>
            <Head title={title} />
            
            <div className="min-vh-100 d-flex flex-column">
                <Header />
                
                <main className="flex-grow-1">
                    {showBreadcrumbs && (
                        <div className="container-fluid py-3">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>
                    )}
                    
                    <div className="container-fluid">
                        {children}
                    </div>
                </main>
                
                <Footer />
            </div>
        </>
    );
}