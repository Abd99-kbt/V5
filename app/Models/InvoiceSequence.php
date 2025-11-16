<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $table = 'invoice_sequences';

    protected $fillable = [
        'year',
        'month',
        'last_sequence',
        'prefix',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'last_sequence' => 'integer',
    ];

    /**
     * Scope for filtering by year.
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope for filtering by month.
     */
    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope for filtering by prefix.
     */
    public function scopeForPrefix($query, $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    /**
     * Get the next sequence number.
     */
    public function getNextSequence(): int
    {
        return $this->last_sequence + 1;
    }

    /**
     * Increment the sequence.
     */
    public function incrementSequence(): void
    {
        $this->increment('last_sequence');
    }

    /**
     * Reset the sequence to 0.
     */
    public function resetSequence(): void
    {
        $this->update(['last_sequence' => 0]);
    }

    /**
     * Get validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 10),
            'month' => 'nullable|integer|min:1|max:12',
            'last_sequence' => 'required|integer|min:0',
            'prefix' => 'required|string|max:10',
        ];
    }

    /**
     * Get or create sequence for given parameters.
     */
    public static function getOrCreateSequence(int $year, ?int $month = null, string $prefix = 'INV'): self
    {
        return self::firstOrCreate([
            'year' => $year,
            'month' => $month,
            'prefix' => $prefix,
        ], [
            'last_sequence' => 0,
        ]);
    }
}