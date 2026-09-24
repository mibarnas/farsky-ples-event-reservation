import type { EventTable } from '@/lib/seatRanges';

const SVG_NS = 'http://www.w3.org/2000/svg';
const LABEL_CLASS = 'table-label';
const MAX_FONT_SIZE = 16;
const MIN_FONT_SIZE = 7;

/**
 * Writes each table's name onto a seat map, centred on the table's seats and
 * rotated to run along tables that are taller than wide. Labels from a previous
 * call are replaced, so this can be re-run whenever the tables change.
 */
export function drawTableLabels(
    root: ParentNode | null | undefined,
    seatNodes: Map<number, SVGCircleElement>,
    tables: EventTable[] | null | undefined,
) {
    root?.querySelectorAll(`text.${LABEL_CLASS}`).forEach((node) => node.remove());

    (tables ?? []).forEach((table) => {
        const name = table.name?.trim();
        const circles = (table.seats ?? [])
            .map((seat) => seatNodes.get(seat))
            .filter((node): node is SVGCircleElement => node instanceof SVGCircleElement);
        if (!name || !circles.length) return;

        let [minX, minY, maxX, maxY] = [Infinity, Infinity, -Infinity, -Infinity];
        circles.forEach((circle) => {
            const [cx, cy, r] = [circle.cx, circle.cy, circle.r].map((length) => length.baseVal.value);
            minX = Math.min(minX, cx - r);
            minY = Math.min(minY, cy - r);
            maxX = Math.max(maxX, cx + r);
            maxY = Math.max(maxY, cy + r);
        });

        const x = (minX + maxX) / 2;
        const y = (minY + maxY) / 2;
        const vertical = maxY - minY > maxX - minX;
        const available = 0.9 * Math.max(maxX - minX, maxY - minY);

        const label = document.createElementNS(SVG_NS, 'text');
        label.classList.add(LABEL_CLASS);
        label.textContent = name;
        label.setAttribute('x', String(x));
        label.setAttribute('y', String(y));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('dominant-baseline', 'central');
        label.setAttribute('font-weight', 'bold');
        label.setAttribute('fill', '#333');
        label.setAttribute('font-size', String(MAX_FONT_SIZE));
        label.setAttribute('pointer-events', 'none');
        if (vertical) label.setAttribute('transform', `rotate(-90, ${x}, ${y})`);

        // Seat coordinates are local to the circles' parent, so the label goes there too.
        circles[0].parentNode?.appendChild(label);

        // Shrink long names to fit; estimate the width when the map isn't rendered yet.
        const width = label.getComputedTextLength() || name.length * MAX_FONT_SIZE * 0.6;
        if (width > available) {
            const size = Math.max(MIN_FONT_SIZE, (MAX_FONT_SIZE * available) / width);
            label.setAttribute('font-size', size.toFixed(1));
        }
    });
}
