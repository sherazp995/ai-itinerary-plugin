import React from 'react';
import { useAppStore } from '../../stores/appStore';

export function LimitCounter() {
  const { user } = useAppStore();
  if (!user || user.has_premium) return null;

  const remaining = user.free_remaining ?? 0;
  const limit = user.free_limit ?? 3;

  return (
    <span className="aip-limit">
      {remaining}/{limit} free
    </span>
  );
}
