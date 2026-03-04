<?php
use PHPUnit\Framework\TestCase;

class PdfTest extends TestCase {

    public function test_generate_returns_pdf_bytes(): void {
        $data = [
            'destination' => 'Paris, France',
            'days' => 3,
            'summary' => 'A lovely 3-day trip.',
            'itinerary' => [
                [
                    'day' => 1,
                    'title' => 'Eiffel Tower Day',
                    'activities' => [
                        ['time' => '10:00', 'title' => 'Eiffel Tower', 'description' => 'Visit the tower', 'cost_estimate' => '$25'],
                    ],
                    'meals' => [
                        'breakfast' => ['name' => 'Cafe de Flore', 'cuisine' => 'French', 'price_range' => '$$'],
                        'lunch' => ['name' => 'Le Relais', 'cuisine' => 'Bistro', 'price_range' => '$$'],
                        'dinner' => ['name' => 'Le Cinq', 'cuisine' => 'Fine Dining', 'price_range' => '$$$'],
                    ],
                    'accommodation' => ['name' => 'Hotel Le Marais', 'price_range' => '$$', 'area' => 'Le Marais'],
                ],
            ],
            'tips' => ['Learn basic French phrases', 'Buy a museum pass'],
            'budget_summary' => [
                'total_estimate' => '$1200-1800',
                'breakdown' => ['accommodation' => '$600', 'food' => '$300', 'activities' => '$200', 'transport' => '$100'],
            ],
        ];

        $pdf = AIP_PDF::generate($data, 'premium');

        // PDF starts with %PDF
        $this->assertStringStartsWith('%PDF', $pdf);
        // Should be a reasonable size (> 1KB)
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_generate_free_pdf_excludes_hotels(): void {
        $data = [
            'destination' => 'Rome',
            'days' => 2,
            'itinerary' => [
                [
                    'day' => 1,
                    'title' => 'Colosseum',
                    'activities' => [
                        ['time' => '09:00', 'title' => 'Colosseum Tour'],
                    ],
                    'accommodation' => ['name' => 'Hidden Hotel', 'area' => 'Centro'],
                ],
            ],
        ];

        $pdf = AIP_PDF::generate($data, 'free');

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_generate_with_empty_data(): void {
        $pdf = AIP_PDF::generate([], 'free');

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
