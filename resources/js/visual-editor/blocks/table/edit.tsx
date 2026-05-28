/**
 * Table — editor-side render.
 *
 * Simplified port of `@wordpress/block-library/src/table/edit.js` (v9.43.0).
 *
 * Upstream's 650-line edit ships a full toolbar (row/col add/delete, cell
 * tag switch, scope picker, alignment, header/footer toggles, …) and a
 * `state.js` of table-mutation helpers. This fork ships the core editing
 * surface — the empty-table placeholder, cell RichText editing, a caption,
 * and a fixed-layout toggle — and defers the heavier toolbar work to a
 * post-I7 customization phase, alongside the indent/outdent work for the
 * list/list-item forks.
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
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
} from '@wordpress/components';

interface TableCell {
    content: string;
    tag: string;
    scope?: string;
    align?: string;
    colspan?: string;
    rowspan?: string;
}

interface TableRow {
    cells: TableCell[];
}

interface TableAttributes {
    hasFixedLayout: boolean;
    caption?: string;
    head: TableRow[];
    body: TableRow[];
    foot: TableRow[];
}

interface TableEditProps {
    readonly attributes: TableAttributes;
    readonly setAttributes: (next: Partial<TableAttributes>) => void;
}

function createTable(rowCount: number, columnCount: number): TableRow[] {
    return Array.from({ length: rowCount }).map(() => ({
        cells: Array.from({ length: columnCount }).map(() => ({
            content: '',
            tag: 'td',
        })),
    }));
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
}: TableEditProps): ReactElement {
    const { hasFixedLayout, caption, head, body, foot } = attributes;
    const isEmpty = head.length === 0 && body.length === 0 && foot.length === 0;
    const [initialRowCount, setInitialRowCount] = useState(2);
    const [initialColumnCount, setInitialColumnCount] = useState(2);
    const blockProps = useBlockProps();

    const renderSection = (
        section: 'head' | 'body' | 'foot',
        rows: TableRow[]
    ) => {
        if (!rows.length) {
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
                                        aria-label={__('Cell')}
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
                    <InspectorControls>
                        <PanelBody title={__('Settings')}>
                            <ToggleControl
                                label={__('Fixed width table cells')}
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
                    label={__('Table')}
                    instructions={__('Insert a table for sharing data.')}
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
                            label={__('Column count')}
                            value={String(initialColumnCount)}
                            onChange={(value) =>
                                setInitialColumnCount(parseInt(value, 10) || 1)
                            }
                            min={1}
                        />
                        <TextControl
                            type="number"
                            label={__('Row count')}
                            value={String(initialRowCount)}
                            onChange={(value) =>
                                setInitialRowCount(parseInt(value, 10) || 1)
                            }
                            min={1}
                        />
                        <Button type="submit" variant="primary">
                            {__('Create table')}
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
                        aria-label={__('Table caption text')}
                        placeholder={__('Add caption')}
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
