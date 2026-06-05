/**
 * Design-family core block renderers: separator, spacer, cover, media-text,
 * table, details, search. Validates and clamps attributes the same way the
 * Blade partials do — dimRatio 0-100, minHeightUnit allowlist, table cell
 * alignment allowlist, group tag allowlist — to keep parity with the
 * server-side output.
 */

import { useId } from 'react';
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

export function SearchBlock({ attributes }: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label, 'Search');
    const showLabel = attributes.showLabel === undefined ? true : attrBoolean(attributes.showLabel);
    const placeholder = attrString(attributes.placeholder);
    const buttonText = attrString(attributes.buttonText, 'Search');
    const useIcon = attrBoolean(attributes.buttonUseIcon);
    const queryName = attrString(attrRecord(attributes.query).name, 's');
    const className = attrString(attributes.className);

    const ariaLabel =
        buttonText.trim() !== ''
            ? buttonText
            : label.trim() !== ''
            ? label
            : 'Search';

    const classes = classList([
        'wp-block-search',
        !showLabel ? 'wp-block-search__button-inside' : null,
        className,
    ]);

    const buttonClasses = classList([
        'wp-block-search__button',
        useIcon ? 'has-icon' : null,
    ]);

    const inputId = `wp-block-search-input-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;

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
                {useIcon ? (
                    <button type="submit" className={buttonClasses} aria-label={ariaLabel}>
                        <svg
                            className="wp-block-search__button-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            width="24"
                            height="24"
                            aria-hidden="true"
                            focusable="false"
                        >
                            <path d="M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-3c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"></path>
                        </svg>
                    </button>
                ) : (
                    <button type="submit" className={buttonClasses}>
                        {buttonText}
                    </button>
                )}
            </div>
        </form>
    );
}

