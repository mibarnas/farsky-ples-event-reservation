<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

/**
 * Server-side counterpart of resources/js/lib/extraSeats.ts: draws an event's
 * extra seats (added in the seat plan editor) onto the location's SVG map.
 */
class ExtraSeats
{
    /**
     * @param  list<array{seat: int, x: float, y: float}>  $seats
     */
    public static function draw(DOMDocument $dom, array $seats): void
    {
        $svg = $dom->getElementsByTagName('svg')->item(0);
        if (! $svg || $seats === []) {
            return;
        }

        // Match the size of the map's own seats
        $first = (new DOMXPath($dom))
            ->query('//circle[contains(concat(" ", normalize-space(@class), " "), " seat ")]')
            ->item(0);
        $r = $first ? ((float) $first->getAttribute('r') ?: 10.0) : 10.0;

        $group = $dom->createElement('g');
        $group->setAttribute('class', 'extra-seats');

        foreach ($seats as $seat) {
            $circle = $dom->createElement('circle');
            foreach ([
                'class' => 'seat',
                'data-seat' => $seat['seat'],
                'cx' => $seat['x'],
                'cy' => $seat['y'],
                'r' => $r,
                'stroke' => '#333',
            ] as $attr => $value) {
                $circle->setAttribute($attr, (string) $value);
            }
            $group->appendChild($circle);

            $label = $dom->createElement('text');
            $label->appendChild($dom->createTextNode((string) $seat['seat']));
            foreach ([
                'x' => $seat['x'],
                'y' => $seat['y'] + $r * 0.4,
                'text-anchor' => 'middle',
                'font-size' => round($r * ($seat['seat'] >= 100 ? 0.7 : 0.8), 1),
                'font-weight' => 'bold',
                'fill' => '#333',
            ] as $attr => $value) {
                $label->setAttribute($attr, (string) $value);
            }
            $group->appendChild($label);
        }

        $svg->appendChild($group);
    }
}
