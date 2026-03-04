import React from 'react';
import { useAppStore } from '../../stores/appStore';
import { useItinerary } from '../../hooks/useItinerary';
import { DayCard } from './DayCard';
import { AffiliateLinks } from '../Affiliate/AffiliateLinks';

export function ItineraryPanel() {
  const { itinerary, itineraryId, affiliateLinks, resetChat, config } = useAppStore();
  const { downloadPdf } = useItinerary();

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
        {itineraryId && (
          <button
            className="aip-btn aip-btn--primary"
            onClick={() => downloadPdf(itineraryId)}
            style={{ background: config.primaryColor, marginBottom: '8px' }}
          >
            Download PDF
          </button>
        )}
        <button
          className="aip-btn"
          onClick={resetChat}
          style={{ background: '#f0f0f0', color: config.primaryColor }}
        >
          Plan Another Trip
        </button>
      </div>
    </div>
  );
}
