import React from 'react';
import { useAppStore } from '../../stores/appStore';
import { LimitCounter } from './LimitCounter';

export function Header({ onClose }) {
  const { config, view, resetChat } = useAppStore();

  return (
    <div className="aip-header">
      <div className="aip-header__left">
        <span className="aip-header__name">{config.botName}</span>
        <LimitCounter />
      </div>
      <div className="aip-header__right">
        {view === 'itinerary' && (
          <button className="aip-header__btn" onClick={resetChat} title="New chat">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="1 4 1 10 7 10" />
              <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
            </svg>
          </button>
        )}
        {onClose && (
          <button className="aip-header__btn" onClick={onClose} title="Close">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        )}
      </div>
    </div>
  );
}
