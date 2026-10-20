/**
 * Table — editor-side render.
 *
 * Simplified port of `@wordpress/block-library/src/table/edit.js` (v9.43.0).
 *
 * Upstream's 650-line edit ships a full toolbar (row/col add/delete, cell
 * tag switch, scope picker, alignment, header/footer toggles, …) and a
 * `state.js` of table-mutation helpers. This fork ships the empty-table
 * placeholder, cell RichText editing, a caption, the fixed-layout toggle,
 * and upstream's "Edit table" dropdown (insert/delete row and column,
 * backed by `./state.ts`). Column alignment, header/footer section
 * toggles, and cell tag/scope switching are still deferred.
 *
 * Layout note: the `<figure>` is a single stable element across the
 * placeholder → populated transition. The block-editor's internal ref
 * tracker is anchored to whatever element receives `useBlockProps`;
 * swapping figures on the transition leaves dangling refs in upstream
 * gutenberg's post-insert focus handler.
 *
 * CSS note: the upstream gutenberg CSS targets `.wp-block-table`, but
 * `getBlockDefaultClassName('artisanpack/table')` would normally emit
 * `wp-block-artisanpack-table`. The `_shared/fork-class-name-alias.ts`
 * filter remaps the I1 forks back to their upstream class names so the
 * bundled CSS applies — without it, cells render at 0×0.
 */

import type { ReactElement } from 'react';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    BlockControls,
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    Placeholder,
    TextControl,
    ToggleControl,
    ToolbarDropdownMenu,
} from '@wordpress/components';
import {
    table as tableIcon,
    tableColumnAfter,
    tableColumnBefore,
    tableColumnDelete,
    tableRowAfter,
    tableRowBefore,
    tableRowDelete,
} from '@wordpress/icons';
import {
    createTable,
    deleteColumn,
    deleteRow,
    insertColumn,
    insertRow,
    isEmptyTableSection,
    type TableCell,
    type TableCellLocation,
    type TableRow,
    type TableSectionName,
    type TableState,
} from './state';

interface TableAttributes extends TableState {
    hasFixedLayout: boolean;
    caption?: string;
}

interface TableEditProps {
    readonly attributes: TableAttributes;
    readonly setAttributes: (next: Partial<TableAttributes>) => void;
    readonly isSelected?: boolean;
}

function updateCell(
    section: TableRow[],
    rowIndex: number,
    columnIndex: number,
    next: Partial<TableCell>
): TableRow[] {
    return section.map((row, ri) => {
        if (ri !== rowIndex) {
            return row;
        }
        return {
            cells: row.cells.map((cell, ci) =>
                ci === columnIndex ? { ...cell, ...next } : cell
            ),
        };
    });
}

export default function TableEdit({
    attributes,
    setAttributes,
    isSelected,
}: TableEditProps): ReactElement {
    const { hasFixedLayout, caption, head, body, foot } = attributes;
    const isEmpty =
        isEmptyTableSection(head) &&
        isEmptyTableSection(body) &&
        isEmptyTableSection(foot);
    const [initialRowCount, setInitialRowCount] = useState(2);
    const [initialColumnCount, setInitialColumnCount] = useState(2);
    const [selectedCell, setSelectedCell] = useState<TableCellLocation>();
    const blockProps = useBlockProps();

    useEffect(() => {
        if (!isSelected) {
            setSelectedCell(undefined);
        }
    }, [isSelected]);

    const onInsertRow = (delta: number) => {
        if (!selectedCell) {
            return;
        }
        const { sectionName, rowIndex } = selectedCell;
        const newRowIndex = rowIndex + delta;
        setAttributes(
            insertRow(attributes, { sectionName, rowIndex: newRowIndex })
        );
        setSelectedCell({ sectionName, rowIndex: newRowIndex, columnIndex: 0 });
    };

    const onDeleteRow = () => {
        if (!selectedCell) {
            return;
        }
        const { sectionName, rowIndex } = selectedCell;
        setSelectedCell(undefined);
        setAttributes(deleteRow(attributes, { sectionName, rowIndex }));
    };

    const onInsertColumn = (delta: number) => {
        if (!selectedCell) {
            return;
        }
        const { sectionName, columnIndex } = selectedCell;
        const newColumnIndex = columnIndex + delta;
        setAttributes(insertColumn(attributes, { columnIndex: newColumnIndex }));
        setSelectedCell({ sectionName, rowIndex: 0, columnIndex: newColumnIndex });
    };

    const onDeleteColumn = () => {
        if (!selectedCell) {
            return;
        }
        const { columnIndex } = selectedCell;
        setSelectedCell(undefined);
        setAttributes(deleteColumn(attributes, { columnIndex }));
    };

    const tableControls = [
        {
            icon: tableRowBefore,
            title: __('Insert row before', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: () => onInsertRow(0),
        },
        {
            icon: tableRowAfter,
            title: __('Insert row after', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: () => onInsertRow(1),
        },
        {
            icon: tableRowDelete,
            title: __('Delete row', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: onDeleteRow,
        },
        {
            icon: tableColumnBefore,
            title: __('Insert column before', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: () => onInsertColumn(0),
        },
        {
            icon: tableColumnAfter,
            title: __('Insert column after', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: () => onInsertColumn(1),
        },
        {
            icon: tableColumnDelete,
            title: __('Delete column', TEXT_DOMAIN),
            isDisabled: !selectedCell,
            onClick: onDeleteColumn,
        },
    ];

    const renderSection = (section: TableSectionName, rows: TableRow[]) => {
        if (isEmptyTableSection(rows)) {
            return null;
        }
        const Tag = `t${section}` as 'thead' | 'tbody' | 'tfoot';
        return (
            <Tag>
                {rows.map(({ cells }, rowIndex) => (
                    <tr key={rowIndex}>
                        {cells.map((cell, columnIndex) => {
                            const CellTag = (cell.tag || 'td') as 'td' | 'th';
                            return (
                                <CellTag
                                    key={columnIndex}
                                    data-align={cell.align}
                                    scope={
                                        CellTag === 'th' ? cell.scope : undefined
                                    }
                                    colSpan={cell.colspan}
                                    rowSpan={cell.rowspan}
                                >
                                    <RichText
                                        value={cell.content}
                                        onChange={(content: string) => {
                                            setAttributes({
                                                [section]: updateCell(
                                                    rows,
                                                    rowIndex,
                                                    columnIndex,
                                                    { content }
                                                ),
                                            });
                                        }}
                                        onFocus={() =>
                                            setSelectedCell({
                                                sectionName: section,
                                                rowIndex,
                                                columnIndex,
                                            })
                                        }
                                        aria-label={__('Cell', TEXT_DOMAIN)}
                                    />
                                </CellTag>
                            );
                        })}
                    </tr>
                ))}
            </Tag>
        );
    };

    return (
        <figure {...blockProps}>
            {!isEmpty && (
                <>
                    <BlockControls group="block" />
                    <BlockControls group="other">
                        <ToolbarDropdownMenu
                            icon={tableIcon}
                            label={__('Edit table', TEXT_DOMAIN)}
                            controls={tableControls}
                        />
                    </BlockControls>
                    <InspectorControls>
                        <PanelBody title={__('Settings', TEXT_DOMAIN)}>
                            <ToggleControl
                                label={__('Fixed width table cells', TEXT_DOMAIN)}
                                checked={!!hasFixedLayout}
                                onChange={() =>
                                    setAttributes({
                                        hasFixedLayout: !hasFixedLayout,
                                    })
                                }
                            />
                        </PanelBody>
                    </InspectorControls>
                </>
            )}
            {isEmpty ? (
                <Placeholder
                    label={__('Table', TEXT_DOMAIN)}
                    instructions={__('Insert a table for sharing data.', TEXT_DOMAIN)}
                >
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            setAttributes({
                                body: createTable(
                                    initialRowCount,
                                    initialColumnCount
                                ),
                            });
                        }}
                    >
                        <TextControl
                            type="number"
                            label={__('Column count', TEXT_DOMAIN)}
                            value={String(initialColumnCount)}
                            onChange={(value) =>
                                setInitialColumnCount(
                                    Math.max(1, parseInt(value, 10) || 1)
                                )
                            }
                            min={1}
                        />
                        <TextControl
                            type="number"
                            label={__('Row count', TEXT_DOMAIN)}
                            value={String(initialRowCount)}
                            onChange={(value) =>
                                setInitialRowCount(
                                    Math.max(1, parseInt(value, 10) || 1)
                                )
                            }
                            min={1}
                        />
                        <Button type="submit" variant="primary">
                            {__('Create table', TEXT_DOMAIN)}
                        </Button>
                    </form>
                </Placeholder>
            ) : (
                <>
                    <table
                        className={
                            hasFixedLayout ? 'has-fixed-layout' : undefined
                        }
                    >
                        {renderSection('head', head)}
                        {renderSection('body', body)}
                        {renderSection('foot', foot)}
                    </table>
                    <RichText
                        tagName="figcaption"
                        aria-label={__('Table caption text', TEXT_DOMAIN)}
                        placeholder={__('Add caption', TEXT_DOMAIN)}
                        value={caption ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ caption: value })
                        }
                    />
                </>
            )}
        </figure>
    );
}
