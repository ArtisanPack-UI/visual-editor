/**
 * Table — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/table/deprecated.js` (v9.43.0).
 * v4 → v1 cover historical save shapes. The v2 chain also handles the
 * legacy `subtle-light-gray`/etc. `backgroundColor` slugs by migrating
 * them into `style.color.background` hex values.
 */

import clsx from 'clsx';
import {
    RichText,
    getColorClassName,
    useBlockProps,
    __experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
    __experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

const oldColors: Record<string, string> = {
    'subtle-light-gray': '#f3f4f5',
    'subtle-pale-green': '#e9fbe5',
    'subtle-pale-blue': '#e7f5fe',
    'subtle-pale-pink': '#fcf0ef',
};

interface LegacyTableCell {
    content?: string;
    tag?: string;
    scope?: string;
    align?: string;
    colspan?: string;
    rowspan?: string;
}

interface LegacyTableRow {
    cells: LegacyTableCell[];
}

interface LegacyTableAttributes {
    hasFixedLayout?: boolean;
    backgroundColor?: string;
    caption?: string;
    head?: LegacyTableRow[];
    body?: LegacyTableRow[];
    foot?: LegacyTableRow[];
    style?: Record<string, unknown>;
    [key: string]: unknown;
}

const v4Query = {
    content: { type: 'rich-text', source: 'rich-text' },
    tag: { type: 'string', default: 'td', source: 'tag' },
    scope: { type: 'string', source: 'attribute', attribute: 'scope' },
    align: { type: 'string', source: 'attribute', attribute: 'data-align' },
    colspan: { type: 'string', source: 'attribute', attribute: 'colspan' },
    rowspan: { type: 'string', source: 'attribute', attribute: 'rowspan' },
};

const sharedSectionAttributes = (query: object) => ({
    head: {
        type: 'array',
        default: [],
        source: 'query',
        selector: 'thead tr',
        query: {
            cells: {
                type: 'array',
                default: [],
                source: 'query',
                selector: 'td,th',
                query,
            },
        },
    },
    body: {
        type: 'array',
        default: [],
        source: 'query',
        selector: 'tbody tr',
        query: {
            cells: {
                type: 'array',
                default: [],
                source: 'query',
                selector: 'td,th',
                query,
            },
        },
    },
    foot: {
        type: 'array',
        default: [],
        source: 'query',
        selector: 'tfoot tr',
        query: {
            cells: {
                type: 'array',
                default: [],
                source: 'query',
                selector: 'td,th',
                query,
            },
        },
    },
});

const v4 = {
    attributes: {
        hasFixedLayout: { type: 'boolean', default: false },
        caption: {
            type: 'rich-text',
            source: 'rich-text',
            selector: 'figcaption',
        },
        ...sharedSectionAttributes(v4Query),
    },
    supports: {
        anchor: true,
        align: true,
        color: { __experimentalSkipSerialization: true, gradients: true },
        spacing: { margin: true, padding: true },
        typography: { fontSize: true, lineHeight: true },
        __experimentalBorder: {
            __experimentalSkipSerialization: true,
            color: true,
            style: true,
            width: true,
        },
        __experimentalSelector: '.wp-block-table > table',
    },
    save({ attributes }: { attributes: LegacyTableAttributes }) {
        const { hasFixedLayout, head = [], body = [], foot = [], caption } = attributes;
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

        const renderSection = (type: 'head' | 'body' | 'foot', rows: LegacyTableRow[]) => {
            if (!rows.length) {
                return null;
            }
            const Tag = `t${type}` as 'thead' | 'tbody' | 'tfoot';
            return (
                <Tag>
                    {rows.map(({ cells }, rowIndex) => (
                        <tr key={rowIndex}>
                            {cells.map((cell, cellIndex) => {
                                const cellClasses = clsx({
                                    [`has-text-align-${cell.align}`]: cell.align,
                                });
                                return (
                                    <RichText.Content
                                        className={cellClasses ? cellClasses : undefined}
                                        data-align={cell.align}
                                        tagName={cell.tag}
                                        value={cell.content}
                                        key={cellIndex}
                                        scope={cell.tag === 'th' ? cell.scope : undefined}
                                        colSpan={cell.colspan}
                                        rowSpan={cell.rowspan}
                                    />
                                );
                            })}
                        </tr>
                    ))}
                </Tag>
            );
        };

        return (
            <figure {...useBlockProps.save()}>
                <table
                    className={classes === '' ? undefined : classes}
                    style={{ ...colorProps.style, ...borderProps.style }}
                >
                    {renderSection('head', head)}
                    {renderSection('body', body)}
                    {renderSection('foot', foot)}
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
    },
};

const v3Query = {
    content: { type: 'string', source: 'html' },
    tag: { type: 'string', default: 'td', source: 'tag' },
    scope: { type: 'string', source: 'attribute', attribute: 'scope' },
    align: { type: 'string', source: 'attribute', attribute: 'data-align' },
};

const v3 = {
    attributes: {
        hasFixedLayout: { type: 'boolean', default: false },
        caption: {
            type: 'string',
            source: 'html',
            selector: 'figcaption',
            default: '',
        },
        ...sharedSectionAttributes(v3Query),
    },
    supports: {
        anchor: true,
        align: true,
        color: { __experimentalSkipSerialization: true, gradients: true },
        spacing: { margin: true, padding: true },
        typography: { fontSize: true, lineHeight: true },
        __experimentalBorder: {
            __experimentalSkipSerialization: true,
            color: true,
            style: true,
            width: true,
        },
        __experimentalSelector: '.wp-block-table > table',
    },
    save({ attributes }: { attributes: LegacyTableAttributes }) {
        const { hasFixedLayout, head = [], body = [], foot = [], caption } = attributes;
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

        const renderSection = (type: 'head' | 'body' | 'foot', rows: LegacyTableRow[]) => {
            if (!rows.length) {
                return null;
            }
            const Tag = `t${type}` as 'thead' | 'tbody' | 'tfoot';
            return (
                <Tag>
                    {rows.map(({ cells }, rowIndex) => (
                        <tr key={rowIndex}>
                            {cells.map((cell, cellIndex) => {
                                const cellClasses = clsx({
                                    [`has-text-align-${cell.align}`]: cell.align,
                                });
                                return (
                                    <RichText.Content
                                        className={cellClasses ? cellClasses : undefined}
                                        data-align={cell.align}
                                        tagName={cell.tag}
                                        value={cell.content}
                                        key={cellIndex}
                                        scope={cell.tag === 'th' ? cell.scope : undefined}
                                    />
                                );
                            })}
                        </tr>
                    ))}
                </Tag>
            );
        };

        return (
            <figure {...useBlockProps.save()}>
                <table
                    className={classes === '' ? undefined : classes}
                    style={{ ...colorProps.style, ...borderProps.style }}
                >
                    {renderSection('head', head)}
                    {renderSection('body', body)}
                    {renderSection('foot', foot)}
                </table>
                {hasCaption && (
                    <RichText.Content tagName="figcaption" value={caption} />
                )}
            </figure>
        );
    },
};

const v2Query = v3Query;

const v2 = {
    attributes: {
        hasFixedLayout: { type: 'boolean', default: false },
        backgroundColor: { type: 'string' },
        caption: {
            type: 'string',
            source: 'html',
            selector: 'figcaption',
            default: '',
        },
        ...sharedSectionAttributes(v2Query),
    },
    supports: {
        anchor: true,
        align: true,
        __experimentalSelector: '.wp-block-table > table',
    },
    save({ attributes }: { attributes: LegacyTableAttributes }) {
        const {
            hasFixedLayout,
            head = [],
            body = [],
            foot = [],
            backgroundColor,
            caption,
        } = attributes;
        const isEmpty = !head.length && !body.length && !foot.length;
        if (isEmpty) {
            return null;
        }
        const backgroundClass = getColorClassName('background-color', backgroundColor);

        const classes = clsx(backgroundClass, {
            'has-fixed-layout': hasFixedLayout,
            'has-background': !!backgroundClass,
        });
        const hasCaption = !RichText.isEmpty(caption);

        const renderSection = (type: 'head' | 'body' | 'foot', rows: LegacyTableRow[]) => {
            if (!rows.length) {
                return null;
            }
            const Tag = `t${type}` as 'thead' | 'tbody' | 'tfoot';
            return (
                <Tag>
                    {rows.map(({ cells }, rowIndex) => (
                        <tr key={rowIndex}>
                            {cells.map((cell, cellIndex) => {
                                const cellClasses = clsx({
                                    [`has-text-align-${cell.align}`]: cell.align,
                                });
                                return (
                                    <RichText.Content
                                        className={cellClasses ? cellClasses : undefined}
                                        data-align={cell.align}
                                        tagName={cell.tag}
                                        value={cell.content}
                                        key={cellIndex}
                                        scope={cell.tag === 'th' ? cell.scope : undefined}
                                    />
                                );
                            })}
                        </tr>
                    ))}
                </Tag>
            );
        };

        return (
            <figure {...useBlockProps.save()}>
                <table className={classes === '' ? undefined : classes}>
                    {renderSection('head', head)}
                    {renderSection('body', body)}
                    {renderSection('foot', foot)}
                </table>
                {hasCaption && <RichText.Content tagName="figcaption" value={caption} />}
            </figure>
        );
    },
    isEligible: (attributes: LegacyTableAttributes) => {
        return (
            !!attributes.backgroundColor &&
            attributes.backgroundColor in oldColors &&
            !attributes.style
        );
    },
    migrate: (attributes: LegacyTableAttributes) => {
        return {
            ...attributes,
            backgroundColor: undefined,
            style: {
                color: {
                    background: oldColors[attributes.backgroundColor as string],
                },
            },
        };
    },
};

const v1Query = {
    content: { type: 'string', source: 'html' },
    tag: { type: 'string', default: 'td', source: 'tag' },
    scope: { type: 'string', source: 'attribute', attribute: 'scope' },
};

const v1 = {
    attributes: {
        hasFixedLayout: { type: 'boolean', default: false },
        backgroundColor: { type: 'string' },
        ...sharedSectionAttributes(v1Query),
    },
    supports: { align: true },
    save({ attributes }: { attributes: LegacyTableAttributes }) {
        const { hasFixedLayout, head = [], body = [], foot = [], backgroundColor } = attributes;
        const isEmpty = !head.length && !body.length && !foot.length;
        if (isEmpty) {
            return null;
        }
        const backgroundClass = getColorClassName('background-color', backgroundColor);
        const classes = clsx(backgroundClass, {
            'has-fixed-layout': hasFixedLayout,
            'has-background': !!backgroundClass,
        });

        const renderSection = (type: 'head' | 'body' | 'foot', rows: LegacyTableRow[]) => {
            if (!rows.length) {
                return null;
            }
            const Tag = `t${type}` as 'thead' | 'tbody' | 'tfoot';
            return (
                <Tag>
                    {rows.map(({ cells }, rowIndex) => (
                        <tr key={rowIndex}>
                            {cells.map((cell, cellIndex) => (
                                <RichText.Content
                                    tagName={cell.tag}
                                    value={cell.content}
                                    key={cellIndex}
                                    scope={cell.tag === 'th' ? cell.scope : undefined}
                                />
                            ))}
                        </tr>
                    ))}
                </Tag>
            );
        };

        return (
            <table className={classes}>
                {renderSection('head', head)}
                {renderSection('body', body)}
                {renderSection('foot', foot)}
            </table>
        );
    },
};

const deprecated = [v4, v3, v2, v1];

export default deprecated;
