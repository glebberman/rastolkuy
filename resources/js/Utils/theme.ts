import { Theme } from '../Types';

const THEME_STORAGE_KEY = 'legal-translator-theme';

export class ThemeManager {
    private static instance: ThemeManager;
    private currentTheme: Theme = 'auto';
    private listeners: Array<(theme: Theme) => void> = [];

    public static getInstance(): ThemeManager {
        if (!ThemeManager.instance) {
            ThemeManager.instance = new ThemeManager();
        }
        return ThemeManager.instance;
    }

    constructor() {
        this.init();
    }

    private init(): void {
        // Load saved theme from localStorage
        const savedTheme = localStorage.getItem(THEME_STORAGE_KEY) as Theme;
        if (savedTheme && ['light', 'dark', 'auto'].includes(savedTheme)) {
            this.currentTheme = savedTheme;
        }

        // Apply theme immediately
        this.applyTheme();

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.currentTheme === 'auto') {
                this.applyTheme();
            }
        });
    }

    public getTheme(): Theme {
        return this.currentTheme;
    }

    public setTheme(theme: Theme): void {
        this.currentTheme = theme;
        localStorage.setItem(THEME_STORAGE_KEY, theme);
        this.applyTheme();
        this.notifyListeners();
    }

    public toggleTheme(): void {
        const themes: Theme[] = ['light', 'dark', 'auto'];
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        this.setTheme(themes[nextIndex]);
    }

    public getEffectiveTheme(): 'light' | 'dark' {
        if (this.currentTheme === 'auto') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return this.currentTheme as 'light' | 'dark';
    }

    private applyTheme(): void {
        const effectiveTheme = this.getEffectiveTheme();
        
        // Add transition class for smooth theme switching
        document.documentElement.classList.add('theme-transition');
        
        // Update data attribute
        document.documentElement.setAttribute('data-bs-theme', effectiveTheme);
        
        // Update class for custom styling
        document.documentElement.classList.remove('theme-light', 'theme-dark', 'theme-auto');
        document.documentElement.classList.add(`theme-${this.currentTheme}`);
        
        // Update color-scheme for native elements
        document.documentElement.style.colorScheme = effectiveTheme;

        // Remove transition class after animation
        setTimeout(() => {
            document.documentElement.classList.remove('theme-transition');
        }, 300);
    }

    public addListener(callback: (theme: Theme) => void): void {
        this.listeners.push(callback);
    }

    public removeListener(callback: (theme: Theme) => void): void {
        this.listeners = this.listeners.filter(listener => listener !== callback);
    }

    private notifyListeners(): void {
        this.listeners.forEach(callback => callback(this.currentTheme));
    }
}

export const themeManager = ThemeManager.getInstance();