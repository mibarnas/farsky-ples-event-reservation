<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

/**
 * Server-side counterpart of resources/js/lib/tableLabels.ts: writes each table's
 * name onto a seat map, centred on the table's seats and rotated to run along
 * tables that are taller than wide.
 */
class TableLabels
{
    private const MAX_FONT_SIZE = 16;

    private const MIN_FONT_SIZE = 7;

    /**
     * @param  list<array{name: string, seats: list<int>}>  $tables
     */
    public static function draw(DOMDocument $dom, array $tables): void
    {
        // Seat numbers are resolved like Location::seatNumbers(): data-seat, falling back to DOM order.
        $xpath = new DOMXPath($dom);
        $circles = [];
        foreach ($xpath->query('//circle[contains(concat(" ", normalize-space(@class), " "), " seat ")]') as $index => $circle) {
            $seat = $circle->getAttribute('data-seat');
            $circles[ctype_digit($seat) ? (int) $seat : $index] = $circle;
        }

        foreach ($tables as $table) {
            $name = trim((string) ($table['name'] ?? ''));
            $nodes = array_values(array_filter(array_map(
                fn ($seat) => $circles[(int) $seat] ?? null,
                (array) ($table['seats'] ?? []),
            )));
            if ($name === '' || $nodes === []) {
                continue;
            }

            [$minX, $minY, $maxX, $maxY] = [INF, INF, -INF, -INF];
            foreach ($nodes as $circle) {
                [$cx, $cy, $r] = array_map(fn ($attr) => (float) $circle->getAttribute($attr), ['cx', 'cy', 'r']);
                $minX = min($minX, $cx - $r);
                $minY = min($minY, $cy - $r);
                $maxX = max($maxX, $cx + $r);
                $maxY = max($maxY, $cy + $r);
            }

            $x = ($minX + $maxX) / 2;
            $y = ($minY + $maxY) / 2;
            $available = 0.9 * max($maxX - $minX, $maxY - $minY);

            // No text measurement on the server, so estimate the width like the client's fallback.
            $width = mb_strlen($name) * self::MAX_FONT_SIZE * 0.6;
            $fontSize = $width > $available
                ? max(self::MIN_FONT_SIZE, self::MAX_FONT_SIZE * $available / $width)
                : self::MAX_FONT_SIZE;

            $label = $dom->createElement('text');
            $label->appendChild($dom->createTextNode($name));
            foreach ([
                'class' => 'table-label',
                'x' => $x,
                'y' => $y,
                'text-anchor' => 'middle',
                'dominant-baseline' => 'central',
                'font-weight' => 'bold',
                'fill' => '#333',
                'font-size' => round($fontSize, 1),
            ] as $attr => $value) {
                $label->setAttribute($attr, (string) $value);
            }
            if ($maxY - $minY > $maxX - $minX) {
                $label->setAttribute('transform', "rotate(-90, $x, $y)");
            }

            // Seat coordinates are local to the circles' parent, so the label goes there too.
            $nodes[0]->parentNode->appendChild($label);
        }
    }
}
