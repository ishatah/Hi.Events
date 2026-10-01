const FORMULA_TRIGGERS = ['=', '+', '-', '@', '\t', '\r'];

export const escapeCsvCell = (cell: string | number | null | undefined): string => {
    if (cell === null || cell === undefined) {
        return '';
    }

    if (typeof cell === 'number') {
        return String(cell);
    }

    const value = FORMULA_TRIGGERS.some(trigger => cell.startsWith(trigger))
        ? `'${cell}`
        : cell;

    return `"${value.replace(/"/g, '""')}"`;
};

export const buildCsv = (headers: string[], rows: (string | number | null | undefined)[][]): string =>
    [headers, ...rows]
        .map(row => row.map(escapeCsvCell).join(','))
        .join('\r\n');
