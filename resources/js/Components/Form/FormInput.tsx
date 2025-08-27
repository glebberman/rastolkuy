import React from 'react';

interface FormInputProps {
    id: string;
    label: string;
    type?: 'text' | 'email';
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    error?: string;
    required?: boolean;
    autoFocus?: boolean;
}

export default function FormInput({
    id,
    label,
    type = 'text',
    value,
    onChange,
    placeholder = '',
    error,
    required = false,
    autoFocus = false
}: FormInputProps) {
    return (
        <div className="mb-3">
            <label htmlFor={id} className="form-label">{label}</label>
            <input
                type={type}
                className={`form-control ${error ? 'is-invalid' : ''}`}
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                required={required}
                autoFocus={autoFocus}
            />
            {error && (
                <div className="invalid-feedback">
                    {error}
                </div>
            )}
        </div>
    );
}