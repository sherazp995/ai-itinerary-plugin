import React from 'react';
import { useAppStore } from '../../stores/appStore';
import { Header } from '../Common/Header';
import { ChatView } from '../Chat/ChatView';
import { ItineraryPanel } from '../Itinerary/ItineraryPanel';
import { AuthModal } from '../Auth/AuthModal';

export function WidgetPanel({ mode, onClose }) {
  const { view, showAuth, config } = useAppStore();

  const isWidget = mode === 'widget';

  return (
    <div
      className={`aip-panel aip-panel--${mode}`}
      style={{ '--aip-primary': config.primaryColor, '--aip-secondary': config.secondaryColor }}
    >
      <Header onClose={isWidget ? onClose : null} />

      <div className="aip-panel__body">
        {view === 'chat' && <ChatView />}
        {view === 'itinerary' && <ItineraryPanel />}
      </div>

      {showAuth && <AuthModal />}
    </div>
  );
}
