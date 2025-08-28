import React from 'react';
import { Link } from '@inertiajs/react';

interface AuthFooterProps {
    text: string;
    linkText: string;
    linkHref: string;
}

export default function AuthFooter({ text, linkText, linkHref }: AuthFooterProps) {
    return (
        <div className="auth-footer">
            <p>
                {text}{' '}
                <Link href={linkHref} className="text-decoration-none">
                    {linkText}
                </Link>
            </p>
        </div>
    );
}