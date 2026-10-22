/**
 * Tests for the `artisanpack/table` row/column mutation helpers.
 */

import { describe, it, expect } from 'vitest';
import {
    createTable,
    deleteColumn,
    deleteRow,
    getFirstRow,
    insertColumn,
    insertRow,
    isEmptyTableSection,
    type TableRow,
    type TableState,
} from '../state';

const row = (...contents: string[]): TableRow => ({
    cells: contents.map((content) => ({ content, tag: 'td' })),
});

const headRow = (...contents: string[]): TableRow => ({
    cells: contents.map((content) => ({ content, tag: 'th', scope: 'col' })),
});

const fullTable = (): TableState => ({
    head: [headRow('H1', 'H2', 'H3')],
    body: [row('A1', 'A2', 'A3'), row('B1', 'B2', 'B3')],
    foot: [row('F1', 'F2', 'F3')],
});

const contents = (rows: TableRow[] | undefined): string[][] =>
    (rows ?? []).map((r) => r.cells.map((c) => c.content));

describe('createTable', () => {
    it('creates rowCount rows of columnCount empty td cells', () => {
        const rows = createTable(2, 3);
        expect(rows).toHaveLength(2);
        rows.forEach((r) => {
            expect(r.cells).toHaveLength(3);
            r.cells.forEach((c) => expect(c).toEqual({ content: '', tag: 'td' }));
        });
    });
});

describe('isEmptyTableSection / getFirstRow', () => {
    it('treats missing, empty, and all-empty-row sections as empty', () => {
        expect(isEmptyTableSection(undefined)).toBe(true);
        expect(isEmptyTableSection([])).toBe(true);
        expect(isEmptyTableSection([{ cells: [] }])).toBe(true);
        expect(isEmptyTableSection([row('A')])).toBe(false);
    });

    it('prefers head, then body, then foot', () => {
        expect(getFirstRow(fullTable())?.cells[0].content).toBe('H1');
        expect(
            getFirstRow({ head: [], body: [row('B')], foot: [row('F')] })?.cells[0]
                .content
        ).toBe('B');
        expect(
            getFirstRow({ head: [], body: [], foot: [row('F')] })?.cells[0].content
        ).toBe('F');
        expect(getFirstRow({ head: [], body: [], foot: [] })).toBeUndefined();
    });
});

describe('insertRow', () => {
    it('inserts an empty body row at the given index and only returns that section', () => {
        const next = insertRow(fullTable(), { sectionName: 'body', rowIndex: 1 });
        expect(Object.keys(next)).toEqual(['body']);
        expect(contents(next.body)).toEqual([
            ['A1', 'A2', 'A3'],
            ['', '', ''],
            ['B1', 'B2', 'B3'],
        ]);
        next.body?.[1].cells.forEach((c) => expect(c.tag).toBe('td'));
    });

    it('inserts th cells into the head and td cells into the foot', () => {
        const state = fullTable();
        const head = insertRow(state, { sectionName: 'head', rowIndex: 0 }).head;
        head?.[0].cells.forEach((c) => expect(c.tag).toBe('th'));

        const foot = insertRow(state, { sectionName: 'foot', rowIndex: 1 }).foot;
        expect(foot).toHaveLength(2);
        foot?.[1].cells.forEach((c) => expect(c.tag).toBe('td'));
    });

    it('inherits column alignment from the first row but not scope or content', () => {
        const state: TableState = {
            head: [],
            body: [
                {
                    cells: [
                        { content: 'X', tag: 'th', scope: 'row', align: 'right' },
                        { content: 'Y', tag: 'td' },
                    ],
                },
            ],
            foot: [],
        };
        const next = insertRow(state, { sectionName: 'body', rowIndex: 1 });
        expect(next.body?.[1].cells).toEqual([
            { content: '', tag: 'td', align: 'right' },
            { content: '', tag: 'td' },
        ]);
    });

    it('honours an explicit columnCount', () => {
        const next = insertRow(
            { head: [], body: [], foot: [] },
            { sectionName: 'head', rowIndex: 0, columnCount: 2 }
        );
        expect(contents(next.head)).toEqual([['', '']]);
    });

    it('returns no changes when it cannot determine a cell count', () => {
        expect(
            insertRow({ head: [], body: [], foot: [] }, { sectionName: 'body', rowIndex: 0 })
        ).toEqual({});
    });
});

describe('deleteRow', () => {
    it('removes only the row at the given index of the given section', () => {
        const next = deleteRow(fullTable(), { sectionName: 'body', rowIndex: 0 });
        expect(Object.keys(next)).toEqual(['body']);
        expect(contents(next.body)).toEqual([['B1', 'B2', 'B3']]);
    });

    it('can empty a section entirely', () => {
        const next = deleteRow(fullTable(), { sectionName: 'head', rowIndex: 0 });
        expect(next.head).toEqual([]);
    });
});

describe('insertColumn', () => {
    it('inserts a column at the index in every section', () => {
        const next = insertColumn(fullTable(), { columnIndex: 1 });
        expect(contents(next.head)).toEqual([['H1', '', 'H2', 'H3']]);
        expect(contents(next.body)).toEqual([
            ['A1', '', 'A2', 'A3'],
            ['B1', '', 'B2', 'B3'],
        ]);
        expect(contents(next.foot)).toEqual([['F1', '', 'F2', 'F3']]);
        expect(next.head?.[0].cells[1].tag).toBe('th');
        expect(next.body?.[0].cells[1].tag).toBe('td');
        expect(next.foot?.[0].cells[1].tag).toBe('td');
    });

    it('can append a column after the last one', () => {
        const next = insertColumn(fullTable(), { columnIndex: 3 });
        expect(contents(next.body)[0]).toEqual(['A1', 'A2', 'A3', '']);
    });

    it('leaves empty sections out of the result', () => {
        const next = insertColumn(
            { head: [], body: [row('A')], foot: [] },
            { columnIndex: 0 }
        );
        expect(Object.keys(next)).toEqual(['body']);
    });

    it('does not insert past the end of a short row', () => {
        const next = insertColumn(
            { head: [], body: [row('A', 'B', 'C'), row('X')], foot: [] },
            { columnIndex: 3 }
        );
        expect(contents(next.body)).toEqual([['A', 'B', 'C', ''], ['X']]);
    });
});

describe('deleteColumn', () => {
    it('removes the column from every section', () => {
        const next = deleteColumn(fullTable(), { columnIndex: 0 });
        expect(contents(next.head)).toEqual([['H2', 'H3']]);
        expect(contents(next.body)).toEqual([
            ['A2', 'A3'],
            ['B2', 'B3'],
        ]);
        expect(contents(next.foot)).toEqual([['F2', 'F3']]);
    });

    it('empties the table when the last column is deleted', () => {
        const next = deleteColumn(
            { head: [headRow('H')], body: [row('A'), row('B')], foot: [] },
            { columnIndex: 0 }
        );
        expect(next.head).toEqual([]);
        expect(next.body).toEqual([]);
        expect(isEmptyTableSection(next.head)).toBe(true);
        expect(isEmptyTableSection(next.body)).toBe(true);
    });

    it('does not mutate the input state', () => {
        const state = fullTable();
        const snapshot = JSON.parse(JSON.stringify(state));
        deleteColumn(state, { columnIndex: 1 });
        insertColumn(state, { columnIndex: 1 });
        insertRow(state, { sectionName: 'body', rowIndex: 0 });
        deleteRow(state, { sectionName: 'body', rowIndex: 0 });
        expect(state).toEqual(snapshot);
    });
});
