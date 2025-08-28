import React, { useState } from 'react';
import { IconEye, IconEyeOff } from '@tabler/icons-react';

interface PasswordInputProps {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    error?: string;
    required?: boolean;
    autoFocus?: boolean;
}

export default function PasswordInput({
    id,
    label,
    value,
    onChange,
    placeholder = '',
    error,
    required = false,
    autoFocus = false
}: PasswordInputProps) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="mb-3">
            <label htmlFor={id} className="form-label">{label}</label>
            <div className="position-relative">
                <input
                    type={showPassword ? 'text' : 'password'}
                    className={`form-control ${error ? 'is-invalid' : ''}`}
                    id={id}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    required={required}
                    autoFocus={autoFocus}
                />
                <button
                    type="button"
                    className="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-3 text-muted"
                    onClick={() => setShowPassword(!showPassword)}
                    tabIndex={-1}
                    style={{ border: 'none', background: 'none' }}
                >
                    {showPassword ? <IconEyeOff size={20} /> : <IconEye size={20} />}
                </button>
            </div>
            {error && (
                <div className="invalid-feedback d-block">
                    {error}
                </div>
            )}
        </div>
    );
}