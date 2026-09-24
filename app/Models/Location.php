<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{

    use HasFactory;

    protected $fillable = [
        'address',
        'svg_map',
        'places_total',
    ];

    protected $casts = [
        'places_total' => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Seat numbers defined in the location's SVG map, resolved the same way as
     * SeatSelector.vue: each `circle.seat` uses its data-seat value, falling back
     * to its position in the document. Returns null if the map file is missing.
     *
     * @return list<int>|null
     */
    public function seatNumbers(): ?array
    {
        static $cache = [];

        $path = public_path(ltrim((string) $this->svg_map, '/'));

        if (! array_key_exists($path, $cache)) {
            $cache[$path] = null;

            if (is_file($path)) {
                preg_match_all('/<circle\b[^>]*\bclass="(?:[^"]*\s)?seat(?:\s[^"]*)?"[^>]*>/i', file_get_contents($path), $matches);

                $seats = [];
                foreach ($matches[0] as $index => $circle) {
                    $seats[] = preg_match('/\bdata-seat="(\d+)"/', $circle, $seat) ? (int) $seat[1] : $index;
                }

                $cache[$path] = $seats;
            }
        }

        return $cache[$path];
    }
}
