import React from 'react';

export function UserMessage({ content }) {
  return (
    <div className="aip-msg aip-msg--user">
      <div className="aip-msg__bubble aip-msg__bubble--user">{content}</div>
    </div>
  );
}
