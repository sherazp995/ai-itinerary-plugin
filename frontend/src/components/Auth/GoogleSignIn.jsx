import React, { useEffect, useRef } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { useAppStore } from '../../stores/appStore';

export function GoogleSignIn() {
  const { googleAuth } = useAuth();
  const { config } = useAppStore();
  const btnRef = useRef(null);

  useEffect(() => {
    if (!config.googleClientId || !window.google) return;

    window.google.accounts.id.initialize({
      client_id: config.googleClientId,
      callback: (response) => {
        googleAuth(response.credential);
      },
    });

    window.google.accounts.id.renderButton(btnRef.current, {
      theme: 'outline',
      size: 'large',
      width: '100%',
    });
  }, [config.googleClientId]);

  if (!config.googleClientId) return null;

  return <div ref={btnRef} className="aip-google-btn" />;
}
