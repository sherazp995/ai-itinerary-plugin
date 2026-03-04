import React, { useEffect } from 'react';
import { WidgetTrigger } from './components/Widget/WidgetTrigger';
import { WidgetPanel } from './components/Widget/WidgetPanel';
import { useAppStore } from './stores/appStore';
import { api } from './api/client';

export default function App({ mode }) {
  const { isOpen, setIsOpen, setUser } = useAppStore();

  useEffect(() => {
    api.get('/user/status').then(data => {
      setUser(data);
    });
  }, []);

  if (mode === 'fullpage') {
    return <WidgetPanel mode="fullpage" />;
  }

  return (
    <>
      <WidgetTrigger onClick={() => setIsOpen(!isOpen)} isOpen={isOpen} />
      {isOpen && <WidgetPanel mode="widget" onClose={() => setIsOpen(false)} />}
    </>
  );
}
