/**
 * Design-family core block renderers: separator, spacer, cover, media-text,
 * table, details, search. Validates and clamps attributes the same way the
 * Blade partials do — dimRatio 0-100, minHeightUnit allowlist, table cell
 * alignment allowlist, group tag allowlist — to keep parity with the
 * server-side output.
 */

import { useMemo } from 'react';
import {
    attrArray,
    attrBoolean,
    attrFloat,
    attrInt,
    attrRecord,
    attrString,
    classList,
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

const ALLOWED_MIN_HEIGHT_UNITS = ['px', 'em', 'rem', 'vh', 'vw', '%'] as const;
const ALLOWED_CELL_ALIGN = ['left', 'center', 'right', 'justify'] as const;

export function SeparatorBlock({ attributes }: BlockRendererProps): JSX.Element {
    const style = attrString(attributes.style, 'default');
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-separator',
        'has-alpha-channel-opacity',
        style === 'wide' ? 'is-style-wide' : null,
        style === 'dots' ? 'is-style-dots' : null,
        className,
    ]);

    return <hr className={classes} />;
}

export function SpacerBlock({ attributes }: BlockRendererProps): JSX.Element {
    const rawHeight = attributes.height ?? '100px';
    const height =
        typeof rawHeight === 'number'
            ? `${rawHeight}px`
            : typeof rawHeight === 'string' && /^\d+(\.\d+)?$/.test(rawHeight.trim())
            ? `${rawHeight.trim()}px`
            : attrString(rawHeight, '100px');

    return <div aria-hidden="true" className="wp-block-spacer" style={{ height }} />;
}

export function CoverBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const url = safeUrl(attributes.url);
    const isDark = attributes.isDark === undefined ? true : attrBoolean(attributes.isDark);

    const rawDim = attrInt(attributes.dimRatio, 50);
    const dimRatio = Math.max(0, Math.min(100, rawDim));

    const align = attrString(attributes.align);
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-cover',
        isDark ? 'is-dark' : 'is-light',
        align !== '' ? `align${align}` : null,
        className,
    ]);

    let style: React.CSSProperties | undefined;

    if (attributes.minHeight !== undefined && attributes.minHeight !== null) {
        const minHeight = Math.max(0, attrFloat(attributes.minHeight));
        const requestedUnit = attrString(attributes.minHeightUnit);
        const unit = (ALLOWED_MIN_HEIGHT_UNITS as ReadonlyArray<string>).includes(requestedUnit)
            ? requestedUnit
            : 'px';

        style = { minHeight: `${minHeight}${unit}` };
    }

    return (
        <div className={classes} style={style}>
            <span
                aria-hidden="true"
                className="wp-block-cover__background has-background-dim"
                style={{ opacity: dimRatio / 100 }}
            />
            {url === '' ? null : (
                <img className="wp-block-cover__image-background" alt="" src={url} />
            )}
            <div className="wp-block-cover__inner-container">{children}</div>
        </div>
    );
}

export function MediaTextBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const mediaUrl = safeUrl(attributes.mediaUrl);
    const mediaAlt = attrString(attributes.mediaAlt);
    const mediaType = attrString(attributes.mediaType, 'image');
    const mediaPosition = attrString(attributes.mediaPosition, 'left');
    const mediaWidth = attrInt(attributes.mediaWidth, 50);
    const className = attrString(attributes.className);
    const isStackedOnMobile = attrBoolean(attributes.isStackedOnMobile);

    const classes = classList([
        'wp-block-media-text',
        mediaPosition === 'right' ? 'has-media-on-the-right' : null,
        isStackedOnMobile ? 'is-stacked-on-mobile' : null,
        className,
    ]);

    let style: React.CSSProperties | undefined;

    if (mediaWidth !== 50) {
        const columns =
            mediaPosition === 'left'
                ? `${mediaWidth}% auto`
                : `auto ${mediaWidth}%`;

        style = { gridTemplateColumns: columns };
    }

    return (
        <div className={classes} style={style}>
            <figure className="wp-block-media-text__media">
                {mediaUrl === '' ? null : mediaType === 'video' ? (
                    <video controls src={mediaUrl} />
                ) : (
                    <img src={mediaUrl} alt={mediaAlt} />
                )}
            </figure>
            <div className="wp-block-media-text__content">{children}</div>
        </div>
    );
}

export function TableBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const hasFixedLayout = attrBoolean(attributes.hasFixedLayout);
    const caption = attrString(attributes.caption);

    const classes = classList([
        'wp-block-table',
        hasFixedLayout ? 'has-fixed-layout' : null,
        className,
    ]);

    const sections = [
        { key: 'head' as const, Tag: 'thead' as const, defaultTag: 'th' as const },
        { key: 'body' as const, Tag: 'tbody' as const, defaultTag: 'td' as const },
        { key: 'foot' as const, Tag: 'tfoot' as const, defaultTag: 'td' as const },
    ];

    return (
        <figure className={classes}>
            <table>
                {sections.map(({ key, Tag, defaultTag }) => {
                    const rows = attrArray(attributes[key]);

                    if (rows.length === 0) {
                        return null;
                    }

                    return (
                        <Tag key={key}>
                            {rows.map((row, rowIndex) => {
                                const rowRecord = attrRecord(row);
                                const cells = attrArray(rowRecord.cells);

                                return (
                                    <tr key={rowIndex}>
                                        {cells.map((cell, cellIndex) => {
                                            const cellRecord = attrRecord(cell);
                                            const content = attrString(cellRecord.content);
                                            const rawAlign = attrString(cellRecord.align).toLowerCase();
                                            const align = (ALLOWED_CELL_ALIGN as ReadonlyArray<string>).includes(rawAlign)
                                                ? rawAlign
                                                : '';
                                            const cellTagRaw = attrString(cellRecord.tag);
                                            const CellTag: 'td' | 'th' =
                                                cellTagRaw === 'td' || cellTagRaw === 'th' ? cellTagRaw : defaultTag;
                                            const cellStyle = align === '' ? undefined : { textAlign: align as React.CSSProperties['textAlign'] };

                                            return (
                                                <CellTag
                                                    key={cellIndex}
                                                    style={cellStyle}
                                                    dangerouslySetInnerHTML={{ __html: content }}
                                                />
                                            );
                                        })}
                                    </tr>
                                );
                            })}
                        </Tag>
                    );
                })}
            </table>
            {caption.trim() !== '' ? (
                <figcaption dangerouslySetInnerHTML={{ __html: caption }} />
            ) : null}
        </figure>
    );
}

export function DetailsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const summary = attrString(attributes.summary);
    const showContent = attrBoolean(attributes.showContent);
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-details', className]);

    return (
        <details className={classes} open={showContent}>
            <summary dangerouslySetInnerHTML={{ __html: summary }} />
            {children}
        </details>
    );
}

export function SearchBlock({ name, attributes }: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label, 'Search');
    const showLabel = attributes.showLabel === undefined ? true : attrBoolean(attributes.showLabel);
    const placeholder = attrString(attributes.placeholder);
    const buttonText = attrString(attributes.buttonText, 'Search');
    const useIcon = attrBoolean(attributes.buttonUseIcon);
    const queryName = attrString(attrRecord(attributes.query).name, 's');
    const buttonLabel = useIcon ? '' : buttonText;
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-search',
        !showLabel ? 'wp-block-search__button-inside' : null,
        className,
    ]);

    const inputId = useMemo(
        () => `wp-block-search-input-${stableHash(`${name}|${JSON.stringify(attributes)}`)}`,
        [name, attributes]
    );

    return (
        <form role="search" method="get" action="/" className={classes}>
            {showLabel ? (
                <label className="wp-block-search__label" htmlFor={inputId}>
                    {label}
                </label>
            ) : null}
            <div className="wp-block-search__inside-wrapper">
                <input
                    id={inputId}
                    type="search"
                    className="wp-block-search__input"
                    name={queryName}
                    placeholder={placeholder === '' ? undefined : placeholder}
                />
                <button type="submit" className="wp-block-search__button">
                    {buttonLabel}
                </button>
            </div>
        </form>
    );
}

function stableHash(value: string): string {
    let h1 = 0xdeadbeef ^ value.length;
    let h2 = 0x41c6ce57 ^ value.length;

    for (let i = 0; i < value.length; i++) {
        const code = value.charCodeAt(i);

        h1 = Math.imul(h1 ^ code, 2654435761);
        h2 = Math.imul(h2 ^ code, 1597334677);
    }

    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);

    const combined = (BigInt(h2 >>> 0) << 32n) | BigInt(h1 >>> 0);

    return combined.toString(16).padStart(16, '0').slice(0, 8);
}
