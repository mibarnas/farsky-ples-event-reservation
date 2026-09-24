export type EventTable = {
    name: string;
    seats: number[];
};

/**
 * Parses seat input like "1-12, 15, 20–22" into a sorted list of unique seat numbers.
 * Returns the seats plus the tokens that could not be understood.
 */
export function parseSeatRanges(input: string): {
    seats: number[];
    invalid: string[];
} {
    const seats = new Set<number>();
    const invalid: string[] = [];

    input
        .split(/[,;\s]+/)
        .map((token) => token.trim())
        .filter(Boolean)
        .forEach((token) => {
            const range = token.match(/^(\d+)\s*[-–]\s*(\d+)$/);
            if (range) {
                let [from, to] = [
                    parseInt(range[1], 10),
                    parseInt(range[2], 10),
                ];
                if (from > to) [from, to] = [to, from];
                if (to - from > 5000) {
                    invalid.push(token);
                    return;
                }
                for (let seat = from; seat <= to; seat++) seats.add(seat);
            } else if (/^\d+$/.test(token)) {
                seats.add(parseInt(token, 10));
            } else {
                invalid.push(token);
            }
        });

    return { seats: [...seats].sort((a, b) => a - b), invalid };
}

/**
 * Formats seat numbers compactly: [1,2,3,4,7] -> "1-4, 7".
 */
export function formatSeatRanges(seats: number[]): string {
    const sorted = [...new Set(seats)].sort((a, b) => a - b);
    const parts: string[] = [];

    for (let i = 0; i < sorted.length; i++) {
        const start = sorted[i];
        while (i + 1 < sorted.length && sorted[i + 1] === sorted[i] + 1) i++;
        parts.push(start === sorted[i] ? `${start}` : `${start}-${sorted[i]}`);
    }

    return parts.join(', ');
}

/**
 * Problems in a table setup, keyed by table index. Seats shared between tables,
 * empty/duplicate names and seats missing from the map are all blocking.
 */
export function validateTables(
    tables: EventTable[],
    validSeats?: number[] | null,
): Record<number, string[]> {
    const problems: Record<number, string[]> = {};
    const add = (index: number, message: string) =>
        (problems[index] ??= []).push(message);
    const valid = validSeats ? new Set(validSeats) : null;
    const owners = new Map<number, number>();
    const names = new Map<string, number>();

    tables.forEach((table, index) => {
        const name = table.name.trim();
        if (!name) add(index, 'Zadajte názov stola.');
        else if (names.has(name.toLowerCase()))
            add(index, `Názov „${name}“ už používa iný stôl.`);
        else names.set(name.toLowerCase(), index);

        if (!table.seats.length)
            add(index, 'Stôl nemá priradené žiadne miesta.');

        const shared: number[] = [];
        const missing: number[] = [];
        table.seats.forEach((seat) => {
            const owner = owners.get(seat);
            if (owner !== undefined && owner !== index) shared.push(seat);
            else owners.set(seat, index);
            if (valid && !valid.has(seat)) missing.push(seat);
        });

        if (shared.length)
            add(
                index,
                `Miesta ${formatSeatRanges(shared)} už patria inému stolu.`,
            );
        if (missing.length)
            add(
                index,
                `Miesta ${formatSeatRanges(missing)} na mape sedenia neexistujú.`,
            );
    });

    return problems;
}
