import React from 'react';
import { useItinerary } from '../../hooks/useItinerary';
import { useAppStore } from '../../stores/appStore';

export function GenerateButtons() {
  const { generate, isGenerating } = useItinerary();
  const { user, config } = useAppStore();
  const freeRemaining = user?.free_remaining ?? 0;

  return (
    <div className="aip-generate">
      <p className="aip-generate__text">Your trip details are ready!</p>

      {freeRemaining > 0 && (
        <button
          className="aip-generate__btn aip-generate__btn--free"
          onClick={() => generate('free')}
          disabled={isGenerating}
        >
          {isGenerating ? 'Generating...' : 'Generate Free Itinerary'}
        </button>
      )}

      <button
        className="aip-generate__btn aip-generate__btn--premium"
        onClick={() => generate('premium')}
        disabled={isGenerating}
        style={{ background: config.primaryColor }}
      >
        {isGenerating ? 'Generating...' : 'Get Premium Itinerary'}
      </button>

      {freeRemaining <= 0 && !user?.has_premium && (
        <p className="aip-generate__limit">Free limit reached. Try premium!</p>
      )}
    </div>
  );
}
