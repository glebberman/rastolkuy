import React, { useState, useEffect } from 'react';
import { IconSun, IconMoon, IconDeviceDesktop } from '@tabler/icons-react';
import { themeManager } from '../../Utils/theme';
import { Theme } from '../../Types';

export default function ThemeSwitcher() {
    const [currentTheme, setCurrentTheme] = useState<Theme>(themeManager.getTheme());

    useEffect(() => {
        const handleThemeChange = (theme: Theme) => {
            setCurrentTheme(theme);
        };

        themeManager.addListener(handleThemeChange);

        return () => {
            themeManager.removeListener(handleThemeChange);
        };
    }, []);

    const handleThemeChange = (theme: Theme) => {
        themeManager.setTheme(theme);
    };

    const getIcon = (theme: Theme) => {
        switch (theme) {
            case 'light':
                return <IconSun size={18} />;
            case 'dark':
                return <IconMoon size={18} />;
            case 'auto':
                return <IconDeviceDesktop size={18} />;
            default:
                return <IconSun size={18} />;
        }
    };

    const getThemeLabel = (theme: Theme) => {
        switch (theme) {
            case 'light':
                return 'Светлая тема';
            case 'dark':
                return 'Темная тема';
            case 'auto':
                return 'Автоматически';
            default:
                return 'Светлая тема';
        }
    };

    return (
        <div className="dropdown">
            <button
                className="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Выбор темы"
            >
                {getIcon(currentTheme)}
                <span className="d-none d-md-inline">{getThemeLabel(currentTheme)}</span>
            </button>

            <ul className="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button
                        className={`dropdown-item d-flex align-items-center gap-2 ${
                            currentTheme === 'light' ? 'active' : ''
                        }`}
                        onClick={() => handleThemeChange('light')}
                    >
                        <IconSun size={16} />
                        Светлая тема
                    </button>
                </li>
                <li>
                    <button
                        className={`dropdown-item d-flex align-items-center gap-2 ${
                            currentTheme === 'dark' ? 'active' : ''
                        }`}
                        onClick={() => handleThemeChange('dark')}
                    >
                        <IconMoon size={16} />
                        Темная тема
                    </button>
                </li>
                <li>
                    <button
                        className={`dropdown-item d-flex align-items-center gap-2 ${
                            currentTheme === 'auto' ? 'active' : ''
                        }`}
                        onClick={() => handleThemeChange('auto')}
                    >
                        <IconDeviceDesktop size={16} />
                        Автоматически
                    </button>
                </li>
            </ul>
        </div>
    );
}