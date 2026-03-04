import React from 'react';
import { api } from '../../api/client';
import { useAppStore } from '../../stores/appStore';

const icons = {
  hotel: '\u{1F3E8}',
  flight: '\u{2708}\u{FE0F}',
  activity: '\u{1F3AD}',
  flight_compare: '\u{1F50D}',
};

export function AffiliateLinks({ links }) {
  const { collectedData } = useAppStore();

  const handleClick = (link) => {
    api.post('/affiliate/click', {
      provider: link.provider,
      category: link.category,
      destination: collectedData.destination || '',
      url: link.url,
    }).catch(() => {});

    window.open(link.url, '_blank');
  };

  if (!links || links.length === 0) return null;

  return (
    <div className="aip-affiliates">
      <h3>Book Your Trip</h3>
      <div className="aip-affiliates__grid">
        {links.map((link, i) => (
          <button key={i} className="aip-affiliate-btn" onClick={() => handleClick(link)}>
            <span className="aip-affiliate-btn__icon">{icons[link.icon] || '\u{1F517}'}</span>
            <span>{link.label}</span>
          </button>
        ))}
      </div>
    </div>
  );
}
