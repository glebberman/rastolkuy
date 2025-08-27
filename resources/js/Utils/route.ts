import { usePage } from '@inertiajs/react';
import { route as ziggyRoute } from 'ziggy-js';

export function useRoute() {
    const { ziggy } = usePage().props as any;
    
    return (name?: string, params?: any, absolute?: boolean) => {
        return ziggyRoute(name, params, absolute, ziggy);
    };
}

export function route(name?: string, params?: any, absolute?: boolean) {
    if (typeof window !== 'undefined' && window.route) {
        return window.route(name, params, absolute);
    }
    
    // Fallback для server-side rendering
    return name || '/';
}