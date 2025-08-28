import { usePage } from '@inertiajs/react';
import { route as ziggyRoute } from 'ziggy-js';

export function useRoute() {
    const { ziggy } = usePage().props as any;
    
    return (name?: string, params?: any, absolute?: boolean) => {
        return ziggyRoute(name, params, absolute, ziggy);
    };
}

export function route(name?: string, params?: any, absolute?: boolean): string {
    if (typeof window !== 'undefined' && window.route) {
        try {
            return window.route(name, params, absolute) as string;
        } catch (error) {
            console.warn('Route error:', error);
        }
    }
    
    // Fallback для server-side rendering
    return name || '/';
}