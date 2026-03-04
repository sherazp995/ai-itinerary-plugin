import React from 'react';
import { useAppStore } from '../../stores/appStore';

export function WidgetTrigger({ onClick, isOpen }) {
  const { config } = useAppStore();

  if (isOpen) return null;

  return (
    <button
      className="aip-trigger"
      onClick={onClick}
      style={{ '--aip-primary': config.primaryColor }}
      aria-label="Open travel assistant"
    >
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
    </button>
  );
}
