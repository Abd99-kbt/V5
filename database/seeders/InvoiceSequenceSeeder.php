<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InvoiceSequence;

class InvoiceSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequences = [
            // Current year 2025
            [
                'year' => 2025,
                'month' => null,
                'prefix' => 'INV',
                'last_sequence' => 100,
            ],
            [
                'year' => 2025,
                'month' => null,
                'prefix' => 'BILL',
                'last_sequence' => 50,
            ],
            [
                'year' => 2025,
                'month' => 1,
                'prefix' => 'INV',
                'last_sequence' => 25,
            ],
            [
                'year' => 2025,
                'month' => 6,
                'prefix' => 'BILL',
                'last_sequence' => 75,
            ],

            // Last year 2024 (for testing)
            [
                'year' => 2024,
                'month' => null,
                'prefix' => 'INV',
                'last_sequence' => 200,
            ],
            [
                'year' => 2024,
                'month' => null,
                'prefix' => 'BILL',
                'last_sequence' => 150,
            ],
            [
                'year' => 2024,
                'month' => 12,
                'prefix' => 'INV',
                'last_sequence' => 300,
            ],

            // Next year 2026 (for testing)
            [
                'year' => 2026,
                'month' => null,
                'prefix' => 'INV',
                'last_sequence' => 0,
            ],
            [
                'year' => 2026,
                'month' => null,
                'prefix' => 'BILL',
                'last_sequence' => 0,
            ],
            [
                'year' => 2026,
                'month' => 3,
                'prefix' => 'INV',
                'last_sequence' => 10,
            ],
        ];

        foreach ($sequences as $sequence) {
            InvoiceSequence::updateOrCreate(
                [
                    'year' => $sequence['year'],
                    'month' => $sequence['month'],
                    'prefix' => $sequence['prefix'],
                ],
                [
                    'last_sequence' => $sequence['last_sequence'],
                ]
            );
        }
    }
}