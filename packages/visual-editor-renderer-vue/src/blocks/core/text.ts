/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * Vue-rendered pages ship identical markup.
 */

import { defineComponent, h } from 'vue';
import { attrBoolean, attrInt, attrRecord, attrString, classList, phpTrim } from '../../support/attributes';
import { applyBackground } from '../../support/background';
import { applyDimensions, hasDimensionStyle } from '../../support/dimensions';
import { resolveAnchor } from '../../support/headingAnchors';
import { presetClasses } from '../../support/presetClasses';
import { blockRendererProps } from '../shared';

export const ParagraphBlock = defineComponent({
    name: 'ParagraphBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const style = attrRecord(props.attributes.style);
            const typography = attrRecord(style.typography);
            const align = attrString(typography.textAlign, attrString(props.attributes.align));
            const className = attrString(props.attributes.className);
            const content = attrString(props.attributes.content);
            const dropCap = attrBoolean(props.attributes.dropCap);
            const direction = attrString(props.attributes.direction);

            // Upstream save.js disables the drop-cap when the text
            // alignment matches the reading-end side — `right` in LTR,
            // `left` in RTL — or when it's centered. The block's own
            // `direction` attribute is the per-block override; absent
            // that we assume LTR (no signal for global page RTL).
            const isRtl = direction === 'rtl';
            const dropCapDisabled =
                align === 'center' || align === (isRtl ? 'left' : 'right');
            const classes = classList([
                'wp-block-paragraph',
                align !== '' ? `has-text-align-${align}` : null,
                dropCap && !dropCapDisabled ? 'has-drop-cap' : null,
                className,
            ]);

            const nodeProps: Record<string, unknown> = {
                class: classes,
                innerHTML: content,
            };

            if (direction === 'ltr' || direction === 'rtl') {
                nodeProps.dir = direction;
            }

            return h('p', nodeProps);
        };
    },
});

export const HeadingBlock = defineComponent({
    name: 'HeadingBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const rawLevel = attrInt(props.attributes.level, 2);
            const level = Math.max(1, Math.min(6, rawLevel));
            const textAlign = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);
            const content = attrString(props.attributes.content);

            // Preset colour / font-size classes and the anchor `id` match
            // the Blade partial's `BlockSupports::wrapperAttrs()` output (#888).
            const classes = classList([
                'wp-block-heading',
                textAlign !== '' ? `has-text-align-${textAlign}` : null,
                ...presetClasses(props.attributes),
                className,
            ]);

            return h(`h${level}`, { class: classes, id: resolveAnchor(props.attributes.anchor), innerHTML: content });
        };
    },
});

export const ListBlock = defineComponent({
    name: 'ListBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const ordered = attrBoolean(props.attributes.ordered);
            const className = attrString(props.attributes.className);
            const legacyValues = attrString(props.attributes.values);
            const classes = classList(['wp-block-list', className]);

            const tag = ordered ? 'ol' : 'ul';
            const commonProps: Record<string, unknown> = { class: classes };

            if (ordered) {
                if (props.attributes.start !== undefined && props.attributes.start !== null) {
                    commonProps.start = attrInt(props.attributes.start);
                }

                if (attrBoolean(props.attributes.reversed)) {
                    commonProps.reversed = true;
                }
            }

            const children = slots.default ? slots.default() : [];

            if (children.length > 0) {
                return h(tag, commonProps, children);
            }

            if (legacyValues === '') {
                return h(tag, commonProps);
            }

            return h(tag, { ...commonProps, innerHTML: legacyValues });
        };
    },
});

export const ListItemBlock = defineComponent({
    name: 'ListItemBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const content = attrString(props.attributes.content);
            const className = classList([attrString(props.attributes.className)]);
            const liProps = className === '' ? {} : { class: className };
            const children = slots.default ? slots.default() : [];

            if (children.length === 0) {
                return h('li', { ...liProps, innerHTML: content });
            }

            return h('li', liProps, [h('span', { innerHTML: content }), ...children]);
        };
    },
});

export const QuoteBlock = defineComponent({
    name: 'QuoteBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const citation = attrString(props.attributes.citation);
            const classes = classList(['wp-block-quote', className]);
            const children = slots.default ? slots.default() : [];
            const nodes = [...children];

            if (citation.trim() !== '') {
                nodes.push(h('cite', { innerHTML: citation }));
            }

            const wrapperStyle = backgroundAndDimensions(props.attributes);
            const nodeProps: Record<string, unknown> = { class: classes };

            if (wrapperStyle !== undefined) {
                nodeProps.style = wrapperStyle;
            }

            return h('blockquote', nodeProps, nodes);
        };
    },
});

export const CodeBlock = defineComponent({
    name: 'CodeBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const content = attrString(props.attributes.content);

            return h('pre', { class: 'wp-block-code' }, h('code', { innerHTML: content }));
        };
    },
});

export const PreformattedBlock = defineComponent({
    name: 'PreformattedBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const content = attrString(props.attributes.content);

            return h('pre', { class: 'wp-block-preformatted', innerHTML: content });
        };
    },
});

export const VerseBlock = defineComponent({
    name: 'VerseBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const content = attrString(props.attributes.content);
            const textAlign = attrString(props.attributes.textAlign);
            const classes = classList([
                'wp-block-verse',
                textAlign !== '' ? `has-text-align-${textAlign}` : null,
            ]);

            const wrapperStyle = backgroundAndDimensions(props.attributes);
            const nodeProps: Record<string, unknown> = { class: classes, innerHTML: content };

            if (wrapperStyle !== undefined) {
                nodeProps.style = wrapperStyle;
            }

            return h('pre', nodeProps);
        };
    },
});

export const PullquoteBlock = defineComponent({
    name: 'PullquoteBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const className = attrString(props.attributes.className);
            const value = attrString(props.attributes.value);
            const citation = attrString(props.attributes.citation);
            const classes = classList(['wp-block-pullquote', className]);

            const wrapperStyle = backgroundAndDimensions(props.attributes);
            const nodeProps: Record<string, unknown> = { class: classes };

            if (wrapperStyle !== undefined) {
                nodeProps.style = wrapperStyle;
            }

            // The value's HTML goes straight into the blockquote, as in
            // `pullquote.blade.php` (no wrapping element).
            return h('figure', nodeProps, [h('blockquote', { innerHTML: pullquoteInnerHtml(value, citation) })]);
        };
    },
});

/**
 * The wrapper style for blocks with background-image support (F-4): the
 * background image and then the dimensions, in `BlockSupports::compile()`
 * order. `undefined` when neither is set.
 */
function backgroundAndDimensions(attributes: Record<string, unknown>): Record<string, string> | undefined {
    const dimensionStyle = applyDimensions(attributes);
    const style: Record<string, string> = {
        ...(applyBackground(attributes) as Record<string, string>),
        ...(hasDimensionStyle(dimensionStyle) ? (dimensionStyle as Record<string, string>) : {}),
    };

    return Object.keys(style).length > 0 ? style : undefined;
}

/**
 * The pullquote `<blockquote>` content, as `pullquote.blade.php` prints it:
 * the value's HTML, then the citation in a `<cite>`, each only when it has
 * non-whitespace content.
 */
function pullquoteInnerHtml(value: string, citation: string): string {
    return (phpTrim(value) !== '' ? value : '') + (phpTrim(citation) !== '' ? `<cite>${citation}</cite>` : '');
}
