<?php
if (!defined('ABSPATH')) exit;

class AIP_ICS {

    public static function generate($itinerary_row, $start_date) {
        $data = is_string($itinerary_row->data) ? json_decode($itinerary_row->data, true) : $itinerary_row->data;
        if (!is_array($data) || empty($data['itinerary'])) return '';

        $start = DateTime::createFromFormat('Y-m-d', $start_date) ?: new DateTime('today');
        $site_name = get_bloginfo('name') ?: 'AI Itinerary';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . self::esc($site_name) . '//AI Itinerary//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::esc($itinerary_row->title),
        ];

        $day_offset = 0;
        foreach ($data['itinerary'] as $day) {
            $day_date = (clone $start)->modify("+{$day_offset} days");

            foreach ($day['activities'] ?? [] as $i => $activity) {
                $dtstart = self::activity_datetime($day_date, $activity, $i);
                $duration_minutes = self::parse_duration_minutes($activity['duration'] ?? '');
                $dtend = (clone $dtstart)->modify("+{$duration_minutes} minutes");

                $uid = md5($itinerary_row->id . '|' . $day_offset . '|' . $i . '|' . ($activity['title'] ?? '')) . '@aip';

                $summary = ($activity['title'] ?? 'Activity');
                $description = trim(($activity['description'] ?? '') . "\n\n" . ($activity['cost_estimate'] ?? ''));
                $location = $activity['location'] ?? ($data['destination'] ?? '');

                // Floating local time (no TZID, no Z) — activity at 9am shows as 9am in viewer's calendar
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:' . $uid;
                $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
                $lines[] = 'DTSTART:' . $dtstart->format('Ymd\THis');
                $lines[] = 'DTEND:' . $dtend->format('Ymd\THis');
                $lines[] = 'SUMMARY:' . self::esc($summary);
                $lines[] = 'DESCRIPTION:' . self::esc($description);
                if (!empty($location)) $lines[] = 'LOCATION:' . self::esc($location);

                $coords = $activity['coordinates'] ?? null;
                if (is_array($coords) && isset($coords['lat'], $coords['lng'])) {
                    $lines[] = 'GEO:' . floatval($coords['lat']) . ';' . floatval($coords['lng']);
                }

                $lines[] = 'END:VEVENT';
            }
            $day_offset++;
        }

        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines);
    }

    private static function activity_datetime(DateTime $day_date, array $activity, int $index): DateTime {
        $time = $activity['time'] ?? '';
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return (clone $day_date)->setTime((int) $m[1], (int) $m[2]);
        }
        $period_default = [
            'morning'   => [9, 0],
            'afternoon' => [13, 0],
            'evening'   => [18, 0],
            'night'     => [20, 0],
        ];
        $period = strtolower($activity['period'] ?? '');
        if (isset($period_default[$period])) {
            [$h, $m] = $period_default[$period];
            return (clone $day_date)->setTime($h + min($index, 2), $m);
        }
        return (clone $day_date)->setTime(9 + $index, 0);
    }

    private static function parse_duration_minutes(string $duration): int {
        $minutes = 0;
        if (preg_match('/(\d+)\s*h/i', $duration, $m)) $minutes += ((int) $m[1]) * 60;
        if (preg_match('/(\d+)\s*m/i', $duration, $m)) $minutes += (int) $m[1];
        return $minutes > 0 ? $minutes : 60;
    }

    private static function esc(string $text): string {
        $text = preg_replace('/[\r\n]+/', '\\n', $text);
        return addcslashes($text, ",;\\");
    }
}
