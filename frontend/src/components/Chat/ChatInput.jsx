import React, { useState } from 'react';

export function ChatInput({ onSend, disabled }) {
  const [text, setText] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!text.trim() || disabled) return;
    onSend(text.trim());
    setText('');
  };

  return (
    <form className="aip-input" onSubmit={handleSubmit}>
      <input
        type="text"
        className="aip-input__field"
        value={text}
        onChange={(e) => setText(e.target.value)}
        placeholder="Type your message..."
        disabled={disabled}
        autoFocus
      />
      <button type="submit" className="aip-input__btn" disabled={disabled || !text.trim()}>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
        </svg>
      </button>
    </form>
  );
}
