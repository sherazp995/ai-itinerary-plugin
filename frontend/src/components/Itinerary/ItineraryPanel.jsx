import React from 'react';
import { useAppStore } from '../../stores/appStore';
import { DayCard } from './DayCard';
import { AffiliateLinks } from '../Affiliate/AffiliateLinks';

export function ItineraryPanel() {
  const { itinerary, affiliateLinks, resetChat, config } = useAppStore();

  if (!itinerary) return null;

  return (
    <div className="aip-itinerary">
      <div className="aip-itinerary__header" style={{ background: config.primaryColor }}>
        <h2>{itinerary.destination}</h2>
        <p>{itinerary.days}-Day Itinerary</p>
      </div>

      {itinerary.summary && (
        <p className="aip-itinerary__summary">{itinerary.summary}</p>
      )}

      <div className="aip-itinerary__days">
        {(itinerary.itinerary || []).map((day, i) => (
          <DayCard key={i} day={day} />
        ))}
      </div>

      {itinerary.tips && itinerary.tips.length > 0 && (
        <div className="aip-itinerary__tips">
          <h3>Travel Tips</h3>
          <ul>
            {itinerary.tips.map((tip, i) => (
              <li key={i}>{tip}</li>
            ))}
          </ul>
        </div>
      )}

      {affiliateLinks.length > 0 && <AffiliateLinks links={affiliateLinks} />}

      <div className="aip-itinerary__actions">
        <button className="aip-btn aip-btn--primary" onClick={resetChat} style={{ background: config.primaryColor }}>
          Plan Another Trip
        </button>
      </div>
    </div>
  );
}
