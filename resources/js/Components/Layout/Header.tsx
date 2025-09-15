import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { IconCoins, IconPlus, IconLogout } from '@tabler/icons-react';
import { authService } from '@/Utils/auth';
import SettingsModal from '@/Components/UI/SettingsModal';
import { themeManager } from '@/Utils/theme';
import axios from 'axios';

export default function Header() {
    const [creditsBalance, setCreditsBalance] = useState<number>(0);
    const [currentTheme, setCurrentTheme] = useState<string>('auto');

    useEffect(() => {
        // Load credits balance
        const loadCredits = async () => {
            try {
                const response = await axios.get('/api/v1/user/stats');
                if (response.data?.data?.credits_balance) {
                    setCreditsBalance(response.data.data.credits_balance);
                }
            } catch (error) {
                console.error('Failed to load credits:', error);
            }
        };

        if (authService.isAuthenticated()) {
            loadCredits();
        }

        // Subscribe to theme changes
        const updateTheme = () => {
            setCurrentTheme(themeManager.getEffectiveTheme());
        };

        updateTheme();
        themeManager.addListener(updateTheme);

        return () => {
            themeManager.removeListener(updateTheme);
        };
    }, []);

    const handleLogout = async () => {
        try {
            await axios.post('/api/logout');
            authService.logout();
            router.visit('/login');
        } catch (error) {
            console.error('Logout failed:', error);
            authService.logout();
            router.visit('/login');
        }
    };

    return (
        <header 
            className="py-3"
            data-bs-theme={currentTheme}
            style={{
                backgroundColor: 'transparent'
            }}
        >
            <div className="container-fluid">
                <div className="d-flex justify-content-end align-items-center gap-3">
                    {/* Credits */}
                    <div className="d-flex align-items-center text-muted">
                        <IconCoins size={20} className="me-1" />
                        <span className="fw-medium">{creditsBalance || 0}&nbsp;кр.</span>
                    </div>
                    
                    {/* Add Button */}
                    <button 
                        className="btn btn-ghost btn-sm d-flex align-items-center"
                        onClick={() => {/* TODO: Add credits functionality */}}
                        title="Пополнить кредиты"
                    >
                        <IconPlus size={16} />
                    </button>

                    {/* Settings */}
                    <SettingsModal />

                    {/* Logout Button */}
                    <button 
                        className="btn btn-ghost btn-sm d-flex align-items-center"
                        onClick={handleLogout}
                        title="Выйти"
                    >
                        <IconLogout size={16} />
                    </button>
                </div>
            </div>
        </header>
    );
}