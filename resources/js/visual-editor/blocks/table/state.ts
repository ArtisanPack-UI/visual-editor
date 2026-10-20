/**
 * Table — row/column mutation helpers.
 *
 * Ported from `@wordpress/block-library/src/table/state.js` (v9.43.0),
 * limited to the row/column subset the fork's "Edit table" toolbar uses.
 * The cell-selection, alignment, and section-toggle helpers stay unported
 * until those controls land.
 *
 * Every helper returns a partial attributes object containing only the
 * sections it changed, ready to hand to `setAttributes`.
 *
 * @since 1.15.4
 */

export interface TableCell {
    content: string;
    tag: string;
    scope?: string;
    align?: string;
    colspan?: string;
    rowspan?: string;
}

export interface TableRow {
    cells: TableCell[];
}

export type TableSectionName = 'head' | 'body' | 'foot';

export interface TableState {
    head: TableRow[];
    body: TableRow[];
    foot: TableRow[];
}

export interface TableCellLocation {
    sectionName: TableSectionName;
    rowIndex: number;
    columnIndex: number;
}

export const TABLE_SECTION_NAMES: TableSectionName[] = ['head', 'body', 'foot'];

const INHERITED_COLUMN_ATTRIBUTES = ['align'] as const;

/**
 * Returns a body-only table of empty `td` cells.
 */
export function createTable(rowCount: number, columnCount: number): TableRow[] {
    return Array.from({ length: rowCount }).map(() => ({
        cells: Array.from({ length: columnCount }).map(() => ({
            content: '',
            tag: 'td',
        })),
    }));
}

/**
 * Determines whether a table row has no cells.
 */
export function isEmptyRow(row: TableRow): boolean {
    return !(row.cells && row.cells.length);
}

/**
 * Determines whether a table section is missing or has only empty rows.
 */
export function isEmptyTableSection(section: TableRow[] | undefined): boolean {
    return !section || !section.length || section.every(isEmptyRow);
}

/**
 * Returns the first row of the first non-empty section (head → body → foot).
 */
export function getFirstRow(state: TableState): TableRow | undefined {
    for (const sectionName of TABLE_SECTION_NAMES) {
        if (!isEmptyTableSection(state[sectionName])) {
            return state[sectionName][0];
        }
    }
    return undefined;
}

/**
 * Inserts an empty row into `sectionName` at `rowIndex`.
 *
 * The cell count matches the table's first row unless `columnCount` is
 * given, and each new cell inherits its column's alignment. `head` rows
 * get `th` cells; `body`/`foot` rows get `td`.
 */
export function insertRow(
    state: TableState,
    {
        sectionName,
        rowIndex,
        columnCount,
    }: { sectionName: TableSectionName; rowIndex: number; columnCount?: number }
): Partial<TableState> {
    const firstRow = getFirstRow(state);
    const cellCount = columnCount === undefined ? firstRow?.cells?.length : columnCount;

    if (!cellCount) {
        return {};
    }

    const section = state[sectionName] ?? [];

    return {
        [sectionName]: [
            ...section.slice(0, rowIndex),
            {
                cells: Array.from({ length: cellCount }).map((_, index) => {
                    const firstCellInColumn = firstRow?.cells?.[index];
                    const inheritedAttributes: Partial<TableCell> = {};
                    INHERITED_COLUMN_ATTRIBUTES.forEach((key) => {
                        if (firstCellInColumn?.[key] !== undefined) {
                            inheritedAttributes[key] = firstCellInColumn[key];
                        }
                    });

                    return {
                        ...inheritedAttributes,
                        content: '',
                        tag: sectionName === 'head' ? 'th' : 'td',
                    };
                }),
            },
            ...section.slice(rowIndex),
        ],
    };
}

/**
 * Removes the row at `rowIndex` from `sectionName`.
 */
export function deleteRow(
    state: TableState,
    { sectionName, rowIndex }: { sectionName: TableSectionName; rowIndex: number }
): Partial<TableState> {
    return {
        [sectionName]: (state[sectionName] ?? []).filter(
            (_, index) => index !== rowIndex
        ),
    };
}

/**
 * Inserts an empty column at `columnIndex` in every non-empty section so
 * head, body, and foot stay the same width.
 */
export function insertColumn(
    state: TableState,
    { columnIndex }: { columnIndex: number }
): Partial<TableState> {
    const next: Partial<TableState> = {};

    TABLE_SECTION_NAMES.forEach((sectionName) => {
        const section = state[sectionName];
        if (isEmptyTableSection(section)) {
            return;
        }

        next[sectionName] = section.map((row) => {
            // Leave empty rows alone, and don't insert past the end of a
            // short row.
            if (isEmptyRow(row) || row.cells.length < columnIndex) {
                return row;
            }

            return {
                cells: [
                    ...row.cells.slice(0, columnIndex),
                    {
                        content: '',
                        tag: sectionName === 'head' ? 'th' : 'td',
                    },
                    ...row.cells.slice(columnIndex),
                ],
            };
        });
    });

    return next;
}

/**
 * Removes the column at `columnIndex` from every non-empty section. Rows
 * left with no cells are dropped, so deleting a table's last column
 * empties it and the placeholder returns.
 */
export function deleteColumn(
    state: TableState,
    { columnIndex }: { columnIndex: number }
): Partial<TableState> {
    const next: Partial<TableState> = {};

    TABLE_SECTION_NAMES.forEach((sectionName) => {
        const section = state[sectionName];
        if (isEmptyTableSection(section)) {
            return;
        }

        next[sectionName] = section
            .map((row) => ({
                cells: row.cells.filter((_, index) => index !== columnIndex),
            }))
            .filter((row) => row.cells.length);
    });

    return next;
}
