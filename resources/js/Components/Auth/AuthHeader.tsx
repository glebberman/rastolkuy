import React from 'react';

interface AuthHeaderProps {
    icon: React.ReactNode;
    title: string;
    subtitle: string;
    iconBgColor?: string;
}

export default function AuthHeader({ 
    icon, 
    title, 
    subtitle, 
    iconBgColor = 'bg-primary' 
}: AuthHeaderProps) {
    return (
        <div className="auth-header">
            <div className="text-center mb-3">
                <div className={`d-inline-flex align-items-center justify-content-center rounded-circle ${iconBgColor} text-white mb-3`}
                     style={{ width: '64px', height: '64px' }}>
                    {icon}
                </div>
            </div>
            <h1 className="auth-title">{title}</h1>
            <p className="auth-subtitle">{subtitle}</p>
        </div>
    );
}