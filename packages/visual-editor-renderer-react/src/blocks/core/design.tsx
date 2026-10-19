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
    layoutClass,
} from '../../support/attributes';
import { applyBlockGap } from '../../support/blockGap';
import { dimensionsClassName } from '../../support/dimensions';
import { cssNumber } from '../../support/cssNumber';
import { layoutWrapperClasses } from '../../support/layoutContainer';
import { coverImageUrl } from '../../support/coverImage';
import { searchButtonClasses, searchButtonPosition, searchFormAction } from '../../support/searchForm';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

const ALLOWED_MIN_HEIGHT_UNITS = ['px', 'em', 'rem', 'vh', 'vw', '%'] as const;
const ALLOWED_CELL_ALIGN = ['left', 'center', 'right', 'justify'] as const;
const ALLOWED_CELL_SCOPE = ['row', 'col', 'rowgroup', 'colgroup'] as const;

/** HTML's own ceilings for the span attributes, mirrored from `table.blade.php`. */
const CELL_SPAN_LIMITS = { colspan: 1000, rowspan: 65534 } as const;

/**
 * Sanitise a table cell's `colspan` / `rowspan` to a positive integer, or
 * undefined when the stored value is missing or not a plain integer (#971).
 */
function cellSpan(value: unknown, name: keyof typeof CELL_SPAN_LIMITS): number | undefined {
    const raw = typeof value === 'number' && Number.isInteger(value) ? String(value) : value;

    if (typeof raw !== 'string') {
        return undefined;
    }

    const match = /^([0-9]+)$/.exec(raw);

    if (match === null) {
        return undefined;
    }

    const span = Number.parseInt(match[1], 10);

    return span < 1 ? undefined : Math.min(span, CELL_SPAN_LIMITS[name]);
}

/** Allowlist a `<th>` scope; `<td>` never carries one (upstream save drops it). */
function cellScope(value: unknown, tag: 'td' | 'th'): string | undefined {
    const scope = typeof value === 'string' ? value.toLowerCase() : '';

    return tag === 'th' && (ALLOWED_CELL_SCOPE as ReadonlyArray<string>).includes(scope) ? scope : undefined;
}

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

    // `_veSpacerWidthScope` is stamped by `stampResponsiveScopes()` for
    // per-breakpoint widths; Blade appends it after the compiled classes.
    const classes = classList([
        'wp-block-spacer',
        attrString(attributes.className),
        attrString(attributes._veSpacerWidthScope),
    ]);

    return <div aria-hidden="true" className={classes} style={{ height }} />;
}

export function CoverBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const url = coverImageUrl(attributes);
    const isDark = attributes.isDark === undefined ? true : attrBoolean(attributes.isDark);

    // block.json defaults dimRatio to 100 and the editor omits defaults (#906).
    const rawDim = attrInt(attributes.dimRatio, 100);
    const dimRatio = Math.max(0, Math.min(100, rawDim));

    const align = attrString(attributes.align);
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-cover',
        isDark ? 'is-dark' : 'is-light',
        // Cover always lays its inner container out as flex (#888).
        'is-layout-flex',
        'wp-block-cover-is-layout-flex',
        // Duotone scope stamped by `stampDuotoneScopes()`.
        attrString(attributes._veDuotone) || null,
        align !== '' ? `align${align}` : null,
        dimensionsClassName(attributes),
        className,
    ]);

    // The cover's own Block spacing rides first, in `cover.blade.php` order.
    let style: Record<string, string> = { ...applyBlockGap(attributes) };

    // An aspect ratio replaces the cover's own min-height (#906).
    const hasAspectRatio = attrString(attrRecord(attrRecord(attributes.style).dimensions).aspectRatio) !== '';

    if (attributes.minHeight !== undefined && attributes.minHeight !== null && !hasAspectRatio) {
        const minHeight = Math.max(0, attrFloat(attributes.minHeight));
        const requestedUnit = attrString(attributes.minHeightUnit);
        const unit = (ALLOWED_MIN_HEIGHT_UNITS as ReadonlyArray<string>).includes(requestedUnit)
            ? requestedUnit
            : 'px';

        style = { ...style, minHeight: `${cssNumber(minHeight)}${unit}` };
    }

    // F-3 — cover's layout applies to its inner container, as in
    // `cover.blade.php`; cover doesn't allow justification.
    const innerClasses = classList([
        'wp-block-cover__inner-container',
        ...layoutWrapperClasses(attributes, 'cover', layoutClass(attributes), true, false),
    ]);

    return (
        <div className={classes} style={Object.keys(style).length > 0 ? (style as React.CSSProperties) : undefined}>
            <span
                aria-hidden="true"
                className="wp-block-cover__background has-background-dim"
                style={{ opacity: dimRatio / 100 }}
            />
            {url === '' ? null : (
                <img className="wp-block-cover__image-background" alt="" src={url} />
            )}
            <div className={innerClasses}>{children}</div>
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
    // Defaults to true in block.json and is unsourced, so a saved block
    // only carries it when false. The class belongs on `<table>` (#971).
    const hasFixedLayout = attrBoolean(attributes.hasFixedLayout, true);
    const caption = attrString(attributes.caption);

    const classes = classList(['wp-block-table', className]);

    const sections = [
        { key: 'head' as const, Tag: 'thead' as const, defaultTag: 'th' as const },
        { key: 'body' as const, Tag: 'tbody' as const, defaultTag: 'td' as const },
        { key: 'foot' as const, Tag: 'tfoot' as const, defaultTag: 'td' as const },
    ];

    return (
        <figure className={classes}>
            <table className={hasFixedLayout ? 'has-fixed-layout' : undefined}>
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
                                                    scope={cellScope(cellRecord.scope, CellTag)}
                                                    colSpan={cellSpan(cellRecord.colspan, 'colspan')}
                                                    rowSpan={cellSpan(cellRecord.rowspan, 'rowspan')}
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
    // F-3 — layout pair, per-instance scope and modifiers, as in `details.blade.php`.
    const classes = classList([
        'wp-block-details',
        ...layoutWrapperClasses(attributes, 'details', layoutClass(attributes)),
        className,
    ]);
    const gapStyle = applyBlockGap(attributes);

    return (
        <details
            className={classes}
            open={showContent}
            style={Object.keys(gapStyle).length > 0 ? (gapStyle as React.CSSProperties) : undefined}
        >
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
    // #961: the form's target and the term it shows (see support/searchForm).
    const action = searchFormAction(attributes);
    const searchValue = attrString(attributes._resolvedSearchValue);

    const ariaLabel =
        buttonText.trim() !== ''
            ? buttonText
            : label.trim() !== ''
            ? label
            : 'Search';

    // #961: button position classes as WordPress prints them; `no-button`
    // renders no button, so Enter submits.
    const hasButton = searchButtonPosition(attributes) !== 'no-button';
    const classes = classList([
        'wp-block-search',
        ...searchButtonClasses(attributes, useIcon),
        className,
    ]);

    const buttonClasses = classList([
        'wp-block-search__button',
        useIcon ? 'has-icon' : null,
    ]);

    const inputId = `wp-block-search-input-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;

    return (
        <form role="search" method="get" action={action} className={classes}>
            {/* A hidden label stays for screen readers: it is the input's only accessible name. */}
            <label
                className={showLabel ? 'wp-block-search__label' : 'wp-block-search__label screen-reader-text'}
                htmlFor={inputId}
            >
                {label}
            </label>
            <div className="wp-block-search__inside-wrapper">
                <input
                    id={inputId}
                    type="search"
                    className="wp-block-search__input"
                    name={queryName}
                    defaultValue={searchValue}
                    placeholder={placeholder === '' ? undefined : placeholder}
                />
                {!hasButton ? null : useIcon ? (
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

