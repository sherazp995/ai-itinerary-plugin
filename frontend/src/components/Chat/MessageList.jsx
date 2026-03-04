import React, { useEffect, useRef } from 'react';
import { BotMessage } from './BotMessage';
import { UserMessage } from './UserMessage';
import { TypingIndicator } from './TypingIndicator';

export function MessageList({ messages, isSending }) {
  const endRef = useRef(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isSending]);

  return (
    <div className="aip-messages">
      {messages.map((msg, i) =>
        msg.role === 'assistant' ? (
          <BotMessage key={i} content={msg.content} />
        ) : (
          <UserMessage key={i} content={msg.content} />
        )
      )}
      {isSending && <TypingIndicator />}
      <div ref={endRef} />
    </div>
  );
}
