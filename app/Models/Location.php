<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
     * The visible area of the location's SVG map (its viewBox, or width/height),
     * in SVG user units. Null if the map file is missing or has no usable size.
     *
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    public function mapBounds(): ?array
    {
        $path = public_path(ltrim((string) $this->svg_map, '/'));
        if (! is_file($path) || ! preg_match('/<svg\b[^>]*>/i', file_get_contents($path), $tag)) {
            return null;
        }

        if (preg_match('/\bviewBox="([^"]+)"/i', $tag[0], $viewBox)) {
            $parts = array_map('floatval', preg_split('/[\s,]+/', trim($viewBox[1])));
            if (count($parts) === 4 && $parts[2] > 0 && $parts[3] > 0) {
                return ['x' => $parts[0], 'y' => $parts[1], 'width' => $parts[2], 'height' => $parts[3]];
            }
        }

        if (preg_match('/\bwidth="([\d.]+)/', $tag[0], $width) && preg_match('/\bheight="([\d.]+)/', $tag[0], $height)
            && (float) $width[1] > 0 && (float) $height[1] > 0) {
            return ['x' => 0.0, 'y' => 0.0, 'width' => (float) $width[1], 'height' => (float) $height[1]];
        }

        return null;
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
