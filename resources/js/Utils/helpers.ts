import { ValidationErrors } from '../Types';

/**
 * Format date to human readable string
 */
export function formatDate(date: string | Date, options?: Intl.DateTimeFormatOptions): string {
    const dateObject = typeof date === 'string' ? new Date(date) : date;
    
    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };
    
    return new Intl.DateTimeFormat('ru-RU', { ...defaultOptions, ...options }).format(dateObject);
}

/**
 * Format file size to human readable string
 */
export function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Truncate text to specified length
 */
export function truncateText(text: string, maxLength: number): string {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
}

/**
 * Capitalize first letter of string
 */
export function capitalize(text: string): string {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

/**
 * Get first validation error for a field
 */
export function getValidationError(errors: ValidationErrors | undefined, field: string): string | undefined {
    return errors?.[field]?.[0];
}

/**
 * Check if form has errors
 */
export function hasValidationErrors(errors: ValidationErrors | undefined): boolean {
    return Boolean(errors && Object.keys(errors).length > 0);
}

/**
 * Debounce function execution
 */
export function debounce<T extends (...args: any[]) => void>(func: T, delay: number): (...args: Parameters<T>) => void {
    let timeoutId: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
}

/**
 * Generate random ID
 */
export function generateId(prefix = 'id'): string {
    return `${prefix}-${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Check if user has permission
 */
export function hasPermission(userPermissions: string[] | undefined, permission: string): boolean {
    return Boolean(userPermissions?.includes(permission));
}

/**
 * Check if user has role
 */
export function hasRole(userRoles: string[] | undefined, role: string): boolean {
    return Boolean(userRoles?.includes(role));
}

/**
 * Sleep for specified milliseconds
 */
export function sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Copy text to clipboard
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (error) {
        console.error('Failed to copy to clipboard:', error);
        return false;
    }
}

/**
 * Download file from URL
 */
export function downloadFile(url: string, filename?: string): void {
    const link = document.createElement('a');
    link.href = url;
    if (filename) {
        link.download = filename;
    }
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Scroll to element smoothly
 */
export function scrollToElement(element: HTMLElement | null, offset = 0): void {
    if (!element) return;
    
    const rect = element.getBoundingClientRect();
    const top = rect.top + window.pageYOffset - offset;
    
    window.scrollTo({
        top,
        behavior: 'smooth',
    });
}

/**
 * Check if element is in viewport
 */
export function isElementInViewport(element: HTMLElement): boolean {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}