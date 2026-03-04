import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

// Mount on fullpage container (shortcode)
const fullpageEl = document.getElementById('aip-fullpage');
if (fullpageEl) {
  createRoot(fullpageEl).render(<App mode="fullpage" />);

  // Measure the site navbar so the chat panel starts right below it. Block themes
  // render the navbar as <header class="wp-block-template-part"> at the top of
  // .wp-site-blocks; other themes use <header>, #masthead, .site-header, etc.
  const findHeader = () => {
    const candidates = [
      'header.wp-block-template-part',
      '[data-elementor-type="header"]',
      '.elementor-location-header',
      '#masthead',
      '.site-header',
      'header[role="banner"]',
      'body > header',
    ];
    for (const sel of candidates) {
      const el = document.querySelector(sel);
      if (el) return el;
    }
    return null;
  };
  const measureHeader = () => {
    const header = findHeader();
    let h = header ? Math.round(header.getBoundingClientRect().bottom) : 0;
    // Include WP admin bar if present
    const adminBar = document.getElementById('wpadminbar');
    if (adminBar) {
      const r = adminBar.getBoundingClientRect();
      if (r.bottom > h) h = Math.round(r.bottom);
    }
    document.documentElement.style.setProperty('--aip-header-height', Math.max(0, h) + 'px');
  };
  measureHeader();
  window.addEventListener('resize', measureHeader);
  // Re-measure after fonts/images reflow
  setTimeout(measureHeader, 300);
  setTimeout(measureHeader, 1500);
}

// Mount floating widget only if fullpage is NOT on the page
const widgetEl = document.getElementById('aip-widget');
if (widgetEl && !fullpageEl) {
  createRoot(widgetEl).render(<App mode="widget" />);
}
