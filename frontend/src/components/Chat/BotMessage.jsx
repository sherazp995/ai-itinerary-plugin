import React from 'react';
import { useAppStore } from '../../stores/appStore';

export function BotMessage({ content }) {
  const { config } = useAppStore();

  return (
    <div className="aip-msg aip-msg--bot">
      <div className="aip-msg__avatar" style={{ background: config.primaryColor }}>
        {config.botName.charAt(0)}
      </div>
      <div className="aip-msg__bubble aip-msg__bubble--bot">{content}</div>
    </div>
  );
}
