/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * Vue-rendered pages ship identical markup.
 */

import { defineComponent, h } from 'vue';
import { attrBoolean, attrInt, attrRecord, attrString, classList } from '../../support/attributes';
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

            const classes = classList([
                'wp-block-heading',
                textAlign !== '' ? `has-text-align-${textAlign}` : null,
                className,
            ]);

            return h(`h${level}`, { class: classes, innerHTML: content });
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
            const children = slots.default ? slots.default() : [];

            if (children.length === 0) {
                return h('li', { innerHTML: content });
            }

            return h('li', null, [h('span', { innerHTML: content }), ...children]);
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

            return h('blockquote', { class: classes }, nodes);
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

            return h('pre', { class: classes, innerHTML: content });
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
            const quoteChildren = [];

            if (value.trim() !== '') {
                quoteChildren.push(h('span', { innerHTML: value }));
            }

            if (citation.trim() !== '') {
                quoteChildren.push(h('cite', { innerHTML: citation }));
            }

            return h('figure', { class: classes }, h('blockquote', null, quoteChildren));
        };
    },
});
