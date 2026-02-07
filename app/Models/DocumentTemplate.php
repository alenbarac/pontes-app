<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'type',
        'name',
        'content',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get available placeholders for this template type.
     *
     * @return array
     */
    public function getAvailablePlaceholders(): array
    {
        $basePlaceholders = [
            'member.first_name' => 'Ime člana',
            'member.last_name' => 'Prezime člana',
            'member.full_name' => 'Puno ime člana',
            'member.parent_contact' => 'Kontakt roditelja/skrbnika',
            'member.parent_email' => 'Email roditelja/skrbnika',
            'member.date_of_birth' => 'Datum rođenja',
            'member.email' => 'Email člana',
            'member.phone_number' => 'Telefon člana',
            'date' => 'Današnji datum',
            'date.formatted' => 'Današnji datum (formatiran)',
        ];

        if ($this->type === 'privola') {
            $basePlaceholders = array_merge($basePlaceholders, [
                'activity.name' => 'Naziv aktivnosti',
                'activity.date' => 'Datum aktivnosti',
                'activity.start_date' => 'Početni datum',
                'activity.end_date' => 'Završni datum',
                'activity.location' => 'Lokacija',
                'activity.description' => 'Opis aktivnosti',
            ]);
        }

        return $basePlaceholders;
    }

    /**
     * Scope to filter by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter active templates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
