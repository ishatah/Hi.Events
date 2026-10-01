import {describe, expect, it} from 'vitest';
import {buildCsv, escapeCsvCell} from './csv';

describe('escapeCsvCell', () => {
    it('quotes a plain string', () => {
        expect(escapeCsvCell('Layla')).toBe('"Layla"');
    });

    it('leaves numbers unquoted so spreadsheets treat them as numbers', () => {
        expect(escapeCsvCell(42)).toBe('42');
        expect(escapeCsvCell(0)).toBe('0');
        expect(escapeCsvCell(12.5)).toBe('12.5');
    });

    it('renders null and undefined as empty', () => {
        expect(escapeCsvCell(null)).toBe('');
        expect(escapeCsvCell(undefined)).toBe('');
    });

    it('doubles internal quotes', () => {
        expect(escapeCsvCell('O"Brien')).toBe('"O""Brien"');
    });

    it('survives a value containing a quote and a comma together', () => {
        expect(escapeCsvCell('O"Brien, "VIP"')).toBe('"O""Brien, ""VIP"""');
    });

    it('keeps an embedded newline inside one field', () => {
        expect(escapeCsvCell('line one\nline two')).toBe('"line one\nline two"');
    });

    it.each(['=', '+', '-', '@'])('neutralises a formula starting with %s', (trigger) => {
        expect(escapeCsvCell(`${trigger}HYPERLINK("http://evil.test")`)).toBe(
            `"'${trigger}HYPERLINK(""http://evil.test"")"`,
        );
    });

    it('neutralises tab and carriage return triggers', () => {
        expect(escapeCsvCell('\t=1+1')).toBe('"\'\t=1+1"');
        expect(escapeCsvCell('\r=1+1')).toBe('"\'\r=1+1"');
    });

    it('does not touch a negative number', () => {
        expect(escapeCsvCell(-5)).toBe('-5');
    });

    it('leaves an ordinary string that merely contains an equals sign alone', () => {
        expect(escapeCsvCell('a=b')).toBe('"a=b"');
    });
});

describe('buildCsv', () => {
    it('escapes headers as well as cells', () => {
        expect(buildCsv(['Name, first', 'Total'], [])).toBe('"Name, first","Total"');
    });

    it('separates rows with CRLF', () => {
        expect(buildCsv(['A'], [['one'], ['two']])).toBe('"A"\r\n"one"\r\n"two"');
    });

    it('keeps columns aligned when a value contains a comma', () => {
        const csv = buildCsv(['Name', 'Product'], [['Rahman, Layla', 'VIP']]);

        expect(csv).toBe('"Name","Product"\r\n"Rahman, Layla","VIP"');
    });

    it('keeps columns aligned when a value contains a quote', () => {
        const csv = buildCsv(['Name', 'Product'], [['O"Brien', 'VIP']]);

        expect(csv.split('\r\n')[1]).toBe('"O""Brien","VIP"');
    });

    it('handles an empty row set', () => {
        expect(buildCsv(['A', 'B'], [])).toBe('"A","B"');
    });

    it('mixes numbers and strings in one row', () => {
        expect(buildCsv(['Name', 'Count'], [['Layla', 3]])).toBe('"Name","Count"\r\n"Layla",3');
    });
});
