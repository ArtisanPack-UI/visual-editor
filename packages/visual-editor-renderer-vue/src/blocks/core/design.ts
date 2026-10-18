/**
 * Design-family core block renderers: separator, spacer, cover, media-text,
 * table, details, search. Validates and clamps attributes the same way the
 * Blade partials do — dimRatio 0-100, minHeightUnit allowlist, table cell
 * alignment allowlist, group tag allowlist — to keep parity with the
 * server-side output.
 */

import { defineComponent, h, useId } from 'vue';
import type { VNode } from 'vue';
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
import { blockRendererProps } from '../shared';

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

export const SeparatorBlock = defineComponent({
    name: 'SeparatorBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const style = attrString(props.attributes.style, 'default');
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-separator',
                'has-alpha-channel-opacity',
                style === 'wide' ? 'is-style-wide' : null,
                style === 'dots' ? 'is-style-dots' : null,
                className,
            ]);

            return h('hr', { class: classes });
        };
    },
});

export const SpacerBlock = defineComponent({
    name: 'SpacerBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const rawHeight = props.attributes.height ?? '100px';
            const height =
                typeof rawHeight === 'number'
                    ? `${rawHeight}px`
                    : typeof rawHeight === 'string' && /^\d+(\.\d+)?$/.test(rawHeight.trim())
                    ? `${rawHeight.trim()}px`
                    : attrString(rawHeight, '100px');

            // `_veSpacerWidthScope` is stamped by `stampResponsiveScopes()`
            // for per-breakpoint widths; Blade appends it after the
            // compiled classes.
            const classes = classList([
                'wp-block-spacer',
                attrString(props.attributes.className),
                attrString(props.attributes._veSpacerWidthScope),
            ]);

            return h('div', {
                'aria-hidden': 'true',
                class: classes,
                style: { height },
            });
        };
    },
});

export const CoverBlock = defineComponent({
    name: 'CoverBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const url = coverImageUrl(props.attributes);
            const isDark = props.attributes.isDark === undefined ? true : attrBoolean(props.attributes.isDark);

            // block.json defaults dimRatio to 100 and the editor omits defaults (#906).
            const rawDim = attrInt(props.attributes.dimRatio, 100);
            const dimRatio = Math.max(0, Math.min(100, rawDim));

            const align = attrString(props.attributes.align);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-cover',
                isDark ? 'is-dark' : 'is-light',
                // Cover always lays its inner container out as flex (#888).
                'is-layout-flex',
                'wp-block-cover-is-layout-flex',
                // Duotone scope stamped by `stampDuotoneScopes()`.
                attrString(props.attributes._veDuotone) || null,
                align !== '' ? `align${align}` : null,
                dimensionsClassName(props.attributes),
                className,
            ]);

            // The cover's own Block spacing rides first, in
            // `cover.blade.php` order.
            let outerStyle: Record<string, string> = { ...applyBlockGap(props.attributes) };

            // An aspect ratio replaces the cover's own min-height (#906).
            const hasAspectRatio =
                attrString(attrRecord(attrRecord(props.attributes.style).dimensions).aspectRatio) !== '';

            if (props.attributes.minHeight !== undefined && props.attributes.minHeight !== null && !hasAspectRatio) {
                const minHeight = Math.max(0, attrFloat(props.attributes.minHeight));
                const requestedUnit = attrString(props.attributes.minHeightUnit);
                const unit = (ALLOWED_MIN_HEIGHT_UNITS as ReadonlyArray<string>).includes(requestedUnit)
                    ? requestedUnit
                    : 'px';

                outerStyle = { ...outerStyle, 'min-height': `${cssNumber(minHeight)}${unit}` };
            }

            const children: VNode[] = [
                h('span', {
                    'aria-hidden': 'true',
                    class: 'wp-block-cover__background has-background-dim',
                    style: { opacity: String(dimRatio / 100) },
                }),
            ];

            if (url !== '') {
                children.push(
                    h('img', {
                        class: 'wp-block-cover__image-background',
                        alt: '',
                        src: url,
                    })
                );
            }

            children.push(
                h(
                    'div',
                    {
                        // F-3 — cover's layout applies to its inner
                        // container, as in `cover.blade.php`; cover doesn't
                        // allow justification.
                        class: classList([
                            'wp-block-cover__inner-container',
                            ...layoutWrapperClasses(props.attributes, 'cover', layoutClass(props.attributes), true, false),
                        ]),
                    },
                    slots.default ? slots.default() : []
                )
            );

            const coverProps: Record<string, unknown> = { class: classes };

            if (Object.keys(outerStyle).length > 0) {
                coverProps.style = outerStyle;
            }

            return h('div', coverProps, children);
        };
    },
});

export const MediaTextBlock = defineComponent({
    name: 'MediaTextBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const mediaUrl = safeUrl(props.attributes.mediaUrl);
            const mediaAlt = attrString(props.attributes.mediaAlt);
            const mediaType = attrString(props.attributes.mediaType, 'image');
            const mediaPosition = attrString(props.attributes.mediaPosition, 'left');
            const mediaWidth = attrInt(props.attributes.mediaWidth, 50);
            const className = attrString(props.attributes.className);
            const isStackedOnMobile = attrBoolean(props.attributes.isStackedOnMobile);

            const classes = classList([
                'wp-block-media-text',
                mediaPosition === 'right' ? 'has-media-on-the-right' : null,
                isStackedOnMobile ? 'is-stacked-on-mobile' : null,
                className,
            ]);

            let style: Record<string, string> | undefined;

            if (mediaWidth !== 50) {
                const columns =
                    mediaPosition === 'left' ? `${mediaWidth}% auto` : `auto ${mediaWidth}%`;

                style = { 'grid-template-columns': columns };
            }

            let mediaChild: VNode | null = null;

            if (mediaUrl !== '') {
                mediaChild =
                    mediaType === 'video'
                        ? h('video', { controls: true, src: mediaUrl })
                        : h('img', { src: mediaUrl, alt: mediaAlt });
            }

            const wrapperProps: Record<string, unknown> = { class: classes };

            if (style !== undefined) {
                wrapperProps.style = style;
            }

            return h('div', wrapperProps, [
                h(
                    'figure',
                    { class: 'wp-block-media-text__media' },
                    mediaChild === null ? [] : [mediaChild]
                ),
                h(
                    'div',
                    { class: 'wp-block-media-text__content' },
                    slots.default ? slots.default() : []
                ),
            ]);
        };
    },
});

export const TableBlock = defineComponent({
    name: 'TableBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const className = attrString(props.attributes.className);
            // Defaults to true in block.json and is unsourced, so a saved
            // block only carries it when false. The class belongs on
            // `<table>` (#971).
            const hasFixedLayout = attrBoolean(props.attributes.hasFixedLayout, true);
            const caption = attrString(props.attributes.caption);

            const classes = classList(['wp-block-table', className]);

            const sections = [
                { key: 'head' as const, Tag: 'thead' as const, defaultTag: 'th' as const },
                { key: 'body' as const, Tag: 'tbody' as const, defaultTag: 'td' as const },
                { key: 'foot' as const, Tag: 'tfoot' as const, defaultTag: 'td' as const },
            ];

            const tableChildren: VNode[] = [];

            for (const { key, Tag, defaultTag } of sections) {
                const rows = attrArray(props.attributes[key]);

                if (rows.length === 0) {
                    continue;
                }

                const rowNodes = rows.map((row, rowIndex) => {
                    const rowRecord = attrRecord(row);
                    const cells = attrArray(rowRecord.cells);
                    const cellNodes = cells.map((cell, cellIndex) => {
                        const cellRecord = attrRecord(cell);
                        const content = attrString(cellRecord.content);
                        const rawAlign = attrString(cellRecord.align).toLowerCase();
                        const align = (ALLOWED_CELL_ALIGN as ReadonlyArray<string>).includes(rawAlign)
                            ? rawAlign
                            : '';
                        const cellTagRaw = attrString(cellRecord.tag);
                        const cellTag: 'td' | 'th' =
                            cellTagRaw === 'td' || cellTagRaw === 'th' ? cellTagRaw : defaultTag;
                        return h(cellTag, {
                            key: cellIndex,
                            scope: cellScope(cellRecord.scope, cellTag),
                            colspan: cellSpan(cellRecord.colspan, 'colspan'),
                            rowspan: cellSpan(cellRecord.rowspan, 'rowspan'),
                            // Vue SSR prints `style=""` for an undefined style, so only set it when aligned.
                            ...(align === '' ? {} : { style: { 'text-align': align } }),
                            innerHTML: content,
                        });
                    });

                    return h('tr', { key: rowIndex }, cellNodes);
                });

                tableChildren.push(h(Tag, { key }, rowNodes));
            }

            const figureChildren: VNode[] = [h('table', hasFixedLayout ? { class: 'has-fixed-layout' } : null, tableChildren)];

            if (caption.trim() !== '') {
                figureChildren.push(h('figcaption', { innerHTML: caption }));
            }

            return h('figure', { class: classes }, figureChildren);
        };
    },
});

export const DetailsBlock = defineComponent({
    name: 'DetailsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const summary = attrString(props.attributes.summary);
            const showContent = attrBoolean(props.attributes.showContent);
            const className = attrString(props.attributes.className);
            // F-3 — layout pair, per-instance scope and modifiers, as in
            // `details.blade.php`.
            const classes = classList([
                'wp-block-details',
                ...layoutWrapperClasses(props.attributes, 'details', layoutClass(props.attributes)),
                className,
            ]);

            const children: VNode[] = [h('summary', { innerHTML: summary })];

            if (slots.default) {
                children.push(...(slots.default() as VNode[]));
            }

            const detailsProps: Record<string, unknown> = { class: classes, open: showContent };
            const gapStyle = applyBlockGap(props.attributes);

            if (Object.keys(gapStyle).length > 0) {
                detailsProps.style = gapStyle;
            }

            return h('details', detailsProps, children);
        };
    },
});

export const SearchBlock = defineComponent({
    name: 'SearchBlock',
    props: blockRendererProps,
    setup(props) {
        const inputId = `wp-block-search-input-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;

        return () => {
            const label = attrString(props.attributes.label, 'Search');
            const showLabel =
                props.attributes.showLabel === undefined ? true : attrBoolean(props.attributes.showLabel);
            const placeholder = attrString(props.attributes.placeholder);
            const buttonText = attrString(props.attributes.buttonText, 'Search');
            const useIcon = attrBoolean(props.attributes.buttonUseIcon);
            const queryName = attrString(attrRecord(props.attributes.query).name, 's');
            const className = attrString(props.attributes.className);
            // #961: the form's target and the term it shows (see support/searchForm).
            const action = searchFormAction(props.attributes);
            const searchValue = attrString(props.attributes._resolvedSearchValue);

            // #338: an icon-only button must still expose an accessible
            // name. Precedence matches the Blade + React renderers so all
            // three stay byte-identical: buttonText → label → "Search".
            const ariaLabel =
                buttonText.trim() !== ''
                    ? buttonText
                    : label.trim() !== ''
                      ? label
                      : 'Search';

            const buttonClasses = classList([
                'wp-block-search__button',
                useIcon ? 'has-icon' : null,
            ]);

            // #961: button position classes as WordPress prints them;
            // `no-button` renders no button, so Enter submits.
            const hasButton = searchButtonPosition(props.attributes) !== 'no-button';
            const classes = classList([
                'wp-block-search',
                ...searchButtonClasses(props.attributes, useIcon),
                className,
            ]);

            const children: VNode[] = [];

            // A hidden label stays for screen readers: it is the input's
            // only accessible name.
            children.push(
                h(
                    'label',
                    {
                        class: showLabel
                            ? 'wp-block-search__label'
                            : 'wp-block-search__label screen-reader-text',
                        for: inputId,
                    },
                    label
                )
            );

            children.push(
                h('div', { class: 'wp-block-search__inside-wrapper' }, [
                    h('input', {
                        id: inputId,
                        type: 'search',
                        class: 'wp-block-search__input',
                        name: queryName,
                        value: searchValue,
                        placeholder: placeholder === '' ? undefined : placeholder,
                    }),
                    // `no-button`: no submit button, Enter submits.
                    ...(!hasButton
                        ? []
                        : [useIcon
                            ? h(
                                  'button',
                                  {
                                      type: 'submit',
                                      class: buttonClasses,
                                      'aria-label': ariaLabel,
                                  },
                                  [
                                      h(
                                          'svg',
                                          {
                                              class: 'wp-block-search__button-icon',
                                              xmlns: 'http://www.w3.org/2000/svg',
                                              viewBox: '0 0 24 24',
                                              width: '24',
                                              height: '24',
                                              'aria-hidden': 'true',
                                              focusable: 'false',
                                          },
                                          [
                                              h('path', {
                                                  d: 'M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-3c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z',
                                              }),
                                          ]
                                      ),
                                  ]
                              )
                            : h(
                                  'button',
                                  { type: 'submit', class: buttonClasses },
                                  buttonText
                              )]),
                ])
            );

            return h(
                'form',
                { role: 'search', method: 'get', action, class: classes },
                children
            );
        };
    },
});
