import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

// Mount on widget container
const widgetEl = document.getElementById('aip-widget');
if (widgetEl) {
  createRoot(widgetEl).render(<App mode="widget" />);
}

// Mount on fullpage container
const fullpageEl = document.getElementById('aip-fullpage');
if (fullpageEl) {
  createRoot(fullpageEl).render(<App mode="fullpage" />);
}
