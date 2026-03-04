import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

// Mount on fullpage container (shortcode)
const fullpageEl = document.getElementById('aip-fullpage');
if (fullpageEl) {
  createRoot(fullpageEl).render(<App mode="fullpage" />);
}

// Mount floating widget only if fullpage is NOT on the page
const widgetEl = document.getElementById('aip-widget');
if (widgetEl && !fullpageEl) {
  createRoot(widgetEl).render(<App mode="widget" />);
}
