import React, { useEffect, useRef } from 'react';
import { useChat } from '../../hooks/useChat';
import { useAppStore } from '../../stores/appStore';
import { MessageList } from './MessageList';
import { ChatInput } from './ChatInput';
import { GenerateButtons } from './GenerateButtons';

export function ChatView() {
  const { sendMessage, resetChat, messages, isSending } = useChat();
  const { ready, config } = useAppStore();
  const initialized = useRef(false);

  useEffect(() => {
    if (!initialized.current && messages.length === 0) {
      initialized.current = true;
      resetChat();
    }
  }, []);

  return (
    <div className="aip-chat">
      <MessageList messages={messages} isSending={isSending} />
      {ready ? (
        <GenerateButtons />
      ) : (
        <ChatInput onSend={sendMessage} disabled={isSending} />
      )}
    </div>
  );
}
