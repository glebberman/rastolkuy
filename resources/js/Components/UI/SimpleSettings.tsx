import React, { useState } from 'react';
import { IconSettings } from '@tabler/icons-react';

export default function SimpleSettings() {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <button
                className="btn btn-outline-secondary btn-sm"
                onClick={() => {
                    console.log('Simple settings button clicked');
                    setIsOpen(!isOpen);
                }}
                title="Настройки"
            >
                <IconSettings size={18} />
            </button>

            {isOpen && (
                <div style={{ 
                    position: 'fixed', 
                    top: 0, 
                    left: 0, 
                    width: '100%', 
                    height: '100%', 
                    backgroundColor: 'rgba(0,0,0,0.5)', 
                    zIndex: 1050,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center'
                }}>
                    <div style={{ 
                        backgroundColor: 'white', 
                        padding: '20px', 
                        borderRadius: '5px',
                        maxWidth: '400px',
                        width: '90%'
                    }}>
                        <h5>Тестовые настройки</h5>
                        <p>Модальное окно работает!</p>
                        <button 
                            className="btn btn-secondary"
                            onClick={() => setIsOpen(false)}
                        >
                            Закрыть
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}