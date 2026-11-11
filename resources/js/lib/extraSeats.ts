const SVG_NS = 'http://www.w3.org/2000/svg';
const GROUP_CLASS = 'extra-seats';

/** A seat added to an event on top of its location's map; x/y are SVG user units. */
export type ExtraSeat = {
    seat: number;
    x: number;
    y: number;
};

/** Radius of the map's own seats, so added seats look the same. */
export function seatRadius(root: ParentNode | null | undefined): number {
    const first = Array.from(
        root?.querySelectorAll<SVGCircleElement>('circle.seat') ?? [],
    ).find((node) => !node.closest(`g.${GROUP_CLASS}`));
    return first?.r.baseVal.value || 10;
}

/**
 * Draws an event's extra seats onto a loaded seat map as `circle.seat` elements
 * with numbers, like the map's own seats. Seats from a previous call are
 * replaced, so this can be re-run whenever the seats change. Server-side
 * counterpart: app/Support/ExtraSeats.php.
 */
export function drawExtraSeats(
    root: ParentNode | null | undefined,
    seats: ExtraSeat[] | null | undefined,
) {
    const svg = root?.querySelector('svg');
    if (!svg) return;

    svg.querySelectorAll(`g.${GROUP_CLASS}`).forEach((node) => node.remove());
    if (!seats?.length) return;

    const r = seatRadius(svg);
    const group = document.createElementNS(SVG_NS, 'g');
    group.classList.add(GROUP_CLASS);

    seats.forEach(({ seat, x, y }) => {
        const circle = document.createElementNS(SVG_NS, 'circle');
        circle.classList.add('seat');
        circle.dataset.seat = String(seat);
        circle.setAttribute('cx', String(x));
        circle.setAttribute('cy', String(y));
        circle.setAttribute('r', String(r));
        circle.setAttribute('stroke', '#333');
        group.appendChild(circle);

        const label = document.createElementNS(SVG_NS, 'text');
        label.textContent = String(seat);
        label.setAttribute('x', String(x));
        label.setAttribute('y', String(y + r * 0.4));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute(
            'font-size',
            (r * (seat >= 100 ? 0.7 : 0.8)).toFixed(1),
        );
        label.setAttribute('font-weight', 'bold');
        label.setAttribute('fill', '#333');
        label.setAttribute('pointer-events', 'none');
        group.appendChild(label);
    });

    svg.appendChild(group);
}

/** Converts a mouse event's position to the SVG's user units. */
export function svgPoint(
    svg: SVGSVGElement,
    event: MouseEvent,
): { x: number; y: number } | null {
    const matrix = svg.getScreenCTM();
    if (!matrix) return null;
    const point = new DOMPoint(event.clientX, event.clientY).matrixTransform(
        matrix.inverse(),
    );
    return { x: point.x, y: point.y };
}
