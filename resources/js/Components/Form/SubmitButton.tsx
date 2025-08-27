import React from 'react';
import { IconLoader2 } from '@tabler/icons-react';

interface SubmitButtonProps {
    isLoading: boolean;
    loadingText: string;
    children: React.ReactNode;
    className?: string;
}

export default function SubmitButton({ 
    isLoading, 
    loadingText, 
    children, 
    className = "btn btn-primary w-100 mb-3" 
}: SubmitButtonProps) {
    return (
        <button
            type="submit"
            className={className}
            disabled={isLoading}
        >
            {isLoading ? (
                <>
                    <IconLoader2 className="me-2 spinner-border spinner-border-sm" />
                    {loadingText}
                </>
            ) : (
                children
            )}
        </button>
    );
}