/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * Vue-rendered pages ship identical markup.
 */

import { defineComponent, h } from 'vue';
import { attrBoolean, attrInt, attrRecord, attrString, classList, layoutClass, phpTrim } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { layoutWrapperClasses } from '../../support/layoutContainer';
import { resolveAnchor } from '../../support/headingAnchors';
import { blockRendererProps, withStyle } from '../shared';

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
            // #920 — the `paragraph.blade.php` supports.
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-paragraph',
                align !== '' ? `has-text-align-${align}` : null,
                dropCap && !dropCapDisabled ? 'has-drop-cap' : null,
                ...supports.classNames,
                className,
            ]);

            const nodeProps = withStyle({ class: classes }, supports.style);

            nodeProps.innerHTML = content;

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

            // The supports (#888, #920) and the anchor `id` match the Blade
            // partial's `BlockSupports::wrapperAttrs()` output.
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-heading',
                textAlign !== '' ? `has-text-align-${textAlign}` : null,
                ...supports.classNames,
                className,
            ]);
            const nodeProps = withStyle({ class: classes, id: resolveAnchor(props.attributes.anchor) }, supports.style);

            return h(`h${level}`, { ...nodeProps, innerHTML: content });
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
            const supports = compileBlockSupports(props.attributes);
            const classes = classList(['wp-block-list', ...supports.classNames, className]);

            const tag = ordered ? 'ol' : 'ul';
            const commonProps = withStyle({ class: classes }, supports.style);

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
            const supports = compileBlockSupports(props.attributes);
            const className = classList([...supports.classNames, attrString(props.attributes.className)]);
            const liProps = withStyle(className === '' ? {} : { class: className }, supports.style);
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
            const supports = compileBlockSupports(props.attributes);
            // F-3 — layout pair, per-instance scope and modifiers, as in
            // `quote.blade.php`.
            const classes = classList([
                'wp-block-quote',
                ...layoutWrapperClasses(props.attributes, 'quote', layoutClass(props.attributes)),
                ...supports.classNames,
                className,
            ]);
            const children = slots.default ? slots.default() : [];
            const nodes = [...children];

            if (citation.trim() !== '') {
                nodes.push(h('cite', { innerHTML: citation }));
            }

            return h('blockquote', withStyle({ class: classes }, supports.style), nodes);
        };
    },
});

export const CodeBlock = defineComponent({
    name: 'CodeBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const content = attrString(props.attributes.content);
            const supports = compileBlockSupports(props.attributes);
            // `className` carries the `ve-r-*` / `ve-fx-*` scopes too (M-7).
            const classes = classList(['wp-block-code', ...supports.classNames, attrString(props.attributes.className)]);

            return h('pre', withStyle({ class: classes }, supports.style), h('code', { innerHTML: content }));
        };
    },
});

export const PreformattedBlock = defineComponent({
    name: 'PreformattedBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const content = attrString(props.attributes.content);

            const supports = compileBlockSupports(props.attributes);
            const classes = classList(['wp-block-preformatted', ...supports.classNames, attrString(props.attributes.className)]);

            return h('pre', { ...withStyle({ class: classes }, supports.style), innerHTML: content });
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
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-verse',
                textAlign !== '' ? `has-text-align-${textAlign}` : null,
                ...supports.classNames,
                attrString(props.attributes.className),
            ]);

            return h('pre', { ...withStyle({ class: classes }, supports.style), innerHTML: content });
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
            const supports = compileBlockSupports(props.attributes);
            const classes = classList(['wp-block-pullquote', ...supports.classNames, className]);
            const nodeProps = withStyle({ class: classes }, supports.style);

            // The value's HTML goes straight into the blockquote, as in
            // `pullquote.blade.php` (no wrapping element).
            return h('figure', nodeProps, [h('blockquote', { innerHTML: pullquoteInnerHtml(value, citation) })]);
        };
    },
});

/**
 * The pullquote `<blockquote>` content, as `pullquote.blade.php` prints it:
 * the value's HTML, then the citation in a `<cite>`, each only when it has
 * non-whitespace content.
 */
function pullquoteInnerHtml(value: string, citation: string): string {
    return (phpTrim(value) !== '' ? value : '') + (phpTrim(citation) !== '' ? `<cite>${citation}</cite>` : '');
}
