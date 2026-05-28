/**
 * Table — transforms.
 *
 * Ported from `@wordpress/block-library/src/table/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/table` ↔
 * `artisanpack/table`.
 */

import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';
import { normalizeRowColSpan } from './utils';

const { name } = metadata;

interface PasteTransformNode {
    readonly children: HTMLCollectionOf<Element>;
    readonly nodeName: string;
}

interface RawTransformContext {
    readonly phrasingContentSchema: unknown;
}

const tableContentPasteSchema = ({
    phrasingContentSchema,
}: RawTransformContext) => ({
    tr: {
        allowEmpty: true,
        children: {
            th: {
                allowEmpty: true,
                children: phrasingContentSchema,
                attributes: ['scope', 'colspan', 'rowspan', 'style'],
            },
            td: {
                allowEmpty: true,
                children: phrasingContentSchema,
                attributes: ['colspan', 'rowspan', 'style'],
            },
        },
    },
});

const tablePasteSchema = (args: RawTransformContext) => ({
    table: {
        children: {
            thead: { allowEmpty: true, children: tableContentPasteSchema(args) },
            tfoot: { allowEmpty: true, children: tableContentPasteSchema(args) },
            tbody: { allowEmpty: true, children: tableContentPasteSchema(args) },
        },
    },
});

const transforms = {
    from: [
        {
            type: 'raw',
            selector: 'table',
            schema: tablePasteSchema,
            transform: (node: PasteTransformNode) => {
                const attributes = Array.from(node.children).reduce(
                    (sectionAcc, section) => {
                        if (!section.children.length) {
                            return sectionAcc;
                        }
                        const sectionName = section.nodeName.toLowerCase().slice(1);
                        const sectionAttributes = Array.from(section.children).reduce(
                            (rowAcc, row) => {
                                if (!row.children.length) {
                                    return rowAcc;
                                }
                                const rowAttributes = Array.from(row.children).reduce(
                                    (colAcc, col) => {
                                        const rowspan = normalizeRowColSpan(
                                            col.getAttribute('rowspan')
                                        );
                                        const colspan = normalizeRowColSpan(
                                            col.getAttribute('colspan')
                                        );
                                        const styledCol = col as HTMLElement;
                                        const { textAlign } = styledCol.style || {};
                                        let align;
                                        if (
                                            textAlign === 'left' ||
                                            textAlign === 'center' ||
                                            textAlign === 'right'
                                        ) {
                                            align = textAlign;
                                        }
                                        (colAcc as unknown[]).push({
                                            tag: col.nodeName.toLowerCase(),
                                            content: col.innerHTML,
                                            rowspan,
                                            colspan,
                                            align,
                                        });
                                        return colAcc;
                                    },
                                    [] as unknown[]
                                );
                                (rowAcc as unknown[]).push({ cells: rowAttributes });
                                return rowAcc;
                            },
                            [] as unknown[]
                        );
                        (sectionAcc as Record<string, unknown>)[sectionName] =
                            sectionAttributes;
                        return sectionAcc;
                    },
                    {} as Record<string, unknown>
                );
                return createBlock(name, attributes);
            },
        },
        {
            type: 'block',
            blocks: ['core/table'],
            transform: (attributes: Record<string, unknown>) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/table'],
            transform: (attributes: Record<string, unknown>) =>
                createBlock('core/table', attributes),
        },
    ],
};

export default transforms;
