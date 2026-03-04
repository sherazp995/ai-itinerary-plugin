import React from 'react';
import { useAppStore } from '../../stores/appStore';

export function DayCard({ day }) {
  const { config } = useAppStore();

  return (
    <div className="aip-day">
      <div className="aip-day__header" style={{ background: config.primaryColor }}>
        Day {day.day}: {day.title}
      </div>

      <div className="aip-day__activities">
        {(day.activities || []).map((act, i) => (
          <div key={i} className="aip-activity">
            <span className="aip-activity__time" style={{ color: config.primaryColor }}>
              {act.time}
            </span>
            <div className="aip-activity__info">
              <strong>{act.title}</strong>
              {act.description && <p>{act.description}</p>}
              {act.cost_estimate && <span className="aip-activity__cost">{act.cost_estimate}</span>}
            </div>
          </div>
        ))}
      </div>

      {day.meals && (
        <div className="aip-day__meals">
          {['breakfast', 'lunch', 'dinner'].map((type) => {
            const meal = day.meals[type];
            if (!meal) return null;
            const name = typeof meal === 'string' ? meal : meal.name;
            return (
              <div key={type} className="aip-meal">
                <span className="aip-meal__type">{type}</span>
                <span className="aip-meal__name">{name}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
