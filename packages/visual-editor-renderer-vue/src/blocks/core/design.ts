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
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

const ALLOWED_MIN_HEIGHT_UNITS = ['px', 'em', 'rem', 'vh', 'vw', '%'] as const;
const ALLOWED_CELL_ALIGN = ['left', 'center', 'right', 'justify'] as const;

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

            return h('div', {
                'aria-hidden': 'true',
                class: 'wp-block-spacer',
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
            const url = safeUrl(props.attributes.url);
            const isDark = props.attributes.isDark === undefined ? true : attrBoolean(props.attributes.isDark);

            const rawDim = attrInt(props.attributes.dimRatio, 50);
            const dimRatio = Math.max(0, Math.min(100, rawDim));

            const align = attrString(props.attributes.align);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-cover',
                isDark ? 'is-dark' : 'is-light',
                align !== '' ? `align${align}` : null,
                className,
            ]);

            let outerStyle: Record<string, string> | undefined;

            if (props.attributes.minHeight !== undefined && props.attributes.minHeight !== null) {
                const minHeight = Math.max(0, attrFloat(props.attributes.minHeight));
                const requestedUnit = attrString(props.attributes.minHeightUnit);
                const unit = (ALLOWED_MIN_HEIGHT_UNITS as ReadonlyArray<string>).includes(requestedUnit)
                    ? requestedUnit
                    : 'px';

                outerStyle = { 'min-height': `${minHeight}${unit}` };
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
                    { class: 'wp-block-cover__inner-container' },
                    slots.default ? slots.default() : []
                )
            );

            const coverProps: Record<string, unknown> = { class: classes };

            if (outerStyle !== undefined) {
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
            const hasFixedLayout = attrBoolean(props.attributes.hasFixedLayout);
            const caption = attrString(props.attributes.caption);

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
                        const cellStyle = align === '' ? undefined : { 'text-align': align };

                        return h(cellTag, {
                            key: cellIndex,
                            style: cellStyle,
                            innerHTML: content,
                        });
                    });

                    return h('tr', { key: rowIndex }, cellNodes);
                });

                tableChildren.push(h(Tag, { key }, rowNodes));
            }

            const figureChildren: VNode[] = [h('table', null, tableChildren)];

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
            const classes = classList(['wp-block-details', className]);

            const children: VNode[] = [h('summary', { innerHTML: summary })];

            if (slots.default) {
                children.push(...(slots.default() as VNode[]));
            }

            return h('details', { class: classes, open: showContent }, children);
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

            const classes = classList([
                'wp-block-search',
                !showLabel ? 'wp-block-search__button-inside' : null,
                className,
            ]);

            const children: VNode[] = [];

            if (showLabel) {
                children.push(
                    h(
                        'label',
                        { class: 'wp-block-search__label', for: inputId },
                        label
                    )
                );
            }

            children.push(
                h('div', { class: 'wp-block-search__inside-wrapper' }, [
                    h('input', {
                        id: inputId,
                        type: 'search',
                        class: 'wp-block-search__input',
                        name: queryName,
                        placeholder: placeholder === '' ? undefined : placeholder,
                    }),
                    useIcon
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
                          ),
                ])
            );

            return h(
                'form',
                { role: 'search', method: 'get', action: '/', class: classes },
                children
            );
        };
    },
});
