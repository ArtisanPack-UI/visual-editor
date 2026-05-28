/**
 * Table — saved markup.
 *
 * Ported from `@wordpress/block-library/src/table/save.js` (v9.43.0).
 * Save shape is byte-equivalent to upstream `core/table`.
 *
 * Note: upstream pulls `__experimentalGetBorderClassesAndStyles`,
 * `__experimentalGetColorClassesAndStyles`, and
 * `__experimentalGetElementClassName` from `@wordpress/block-editor`. These
 * are public exports (despite the `__experimental` prefix). We re-import
 * them as-is so the fork stays in sync with the upstream API surface.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    __experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
    __experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

interface TableCell {
    readonly content: string;
    readonly tag: string;
    readonly scope?: string;
    readonly align?: string;
    readonly colspan?: string;
    readonly rowspan?: string;
}

interface TableRow {
    readonly cells: readonly TableCell[];
}

interface TableSaveAttributes {
    readonly hasFixedLayout: boolean;
    readonly caption?: string;
    readonly head: readonly TableRow[];
    readonly body: readonly TableRow[];
    readonly foot: readonly TableRow[];
}

interface TableSaveProps {
    readonly attributes: TableSaveAttributes;
}

interface SectionProps {
    readonly type: 'head' | 'body' | 'foot';
    readonly rows: readonly TableRow[];
}

function Section({ type, rows }: SectionProps): ReactElement | null {
    if (!rows.length) {
        return null;
    }
    const Tag = `t${type}` as 'thead' | 'tbody' | 'tfoot';
    return (
        <Tag>
            {rows.map(({ cells }, rowIndex) => (
                <tr key={rowIndex}>
                    {cells.map(
                        (
                            { content, tag, scope, align, colspan, rowspan },
                            cellIndex
                        ) => {
                            const cellClasses = clsx({
                                [`has-text-align-${align}`]: align,
                            });
                            return (
                                <RichText.Content
                                    className={cellClasses ? cellClasses : undefined}
                                    data-align={align}
                                    tagName={tag}
                                    value={content}
                                    key={cellIndex}
                                    scope={tag === 'th' ? scope : undefined}
                                    colSpan={colspan}
                                    rowSpan={rowspan}
                                />
                            );
                        }
                    )}
                </tr>
            ))}
        </Tag>
    );
}

export default function TableSave({
    attributes,
}: TableSaveProps): ReactElement | null {
    const { hasFixedLayout, head, body, foot, caption } = attributes;
    const isEmpty = !head.length && !body.length && !foot.length;

    if (isEmpty) {
        return null;
    }

    const colorProps = getColorClassesAndStyles(attributes);
    const borderProps = getBorderClassesAndStyles(attributes);

    const classes = clsx(colorProps.className, borderProps.className, {
        'has-fixed-layout': hasFixedLayout,
    });

    const hasCaption = !RichText.isEmpty(caption);

    return (
        <figure {...useBlockProps.save()}>
            <table
                className={classes === '' ? undefined : classes}
                style={{ ...colorProps.style, ...borderProps.style }}
            >
                <Section type="head" rows={head} />
                <Section type="body" rows={body} />
                <Section type="foot" rows={foot} />
            </table>
            {hasCaption && (
                <RichText.Content
                    tagName="figcaption"
                    value={caption}
                    className={__experimentalGetElementClassName('caption')}
                />
            )}
        </figure>
    );
}
