/**
 * Public Vue component for rendering a template by slug.
 *
 * Walks the WordPress-style fallback chain (`single-{slug}` → `single`
 * → `index`, `page-{slug}` → `page` → `index`, etc.) over the supplied
 * `templates` list, and renders the first match's block tree via
 * {@link BlockTree}. Template-part references inside the matched
 * template are inlined via the supplied `templateParts` collection.
 *
 * Resolution failures render an empty wrapper in production; in dev the
 * wrapper carries a `data-ve-resolution-error` attribute so the
 * developer can spot the misconfiguration in the inspector.
 */

import { Fragment, computed, defineComponent, h } from 'vue';
import type { PropType } from 'vue';
import { BlockTree } from './BlockTree';
import { GlobalStyles } from './GlobalStyles';
import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    resolveTemplate,
    templateFallbackChain,
} from './templateParts';
import type { TemplatePartRecord, TemplateRecord } from './templateParts';

const SLUG_CLASS_PATTERN = /[^a-zA-Z0-9_-]/g;

function isDevelopment(): boolean {
    if (typeof process === 'undefined') {
        return false;
    }

    const env = process.env?.NODE_ENV;

    return env !== 'production';
}

export interface TemplateProps {
    slug: string;
    theme?: string;
    templates: TemplateRecord[];
    templateParts?: TemplatePartRecord[];
    dynamicBlockEndpoint?: string;
    fetchOptions?: RequestInit;
    maxTemplatePartDepth?: number;
    /**
     * Compiled global-styles CSS — same string the PHP
     * `GlobalStylesCssProvider` emits on Blade pages. Hosts that mount
     * `<Template>` at the page root pass this once and let it emit
     * before the wrapper div.
     */
    globalStylesCss?: string | null;
}

export const Template = defineComponent({
    name: 'Template',
    props: {
        slug: {
            type: String,
            required: true as const,
        },
        theme: {
            type: String,
            default: undefined,
        },
        templates: {
            type: Array as PropType<TemplateRecord[]>,
            required: true as const,
        },
        templateParts: {
            type: Array as PropType<TemplatePartRecord[]>,
            default: () => [],
        },
        dynamicBlockEndpoint: {
            type: String,
            default: undefined,
        },
        fetchOptions: {
            type: Object as PropType<RequestInit>,
            default: undefined,
        },
        maxTemplatePartDepth: {
            type: Number,
            default: DEFAULT_MAX_TEMPLATE_PART_DEPTH,
        },
        globalStylesCss: {
            type: String as PropType<string | null | undefined>,
            default: null,
        },
    },
    setup(props) {
        const matched = computed(() => resolveTemplate(props.templates, props.slug, props.theme));
        const fallbackChain = computed(() => templateFallbackChain(props.slug));

        return () => {
            const wrapperClasses = ['wp-site-blocks'];

            if (matched.value !== undefined) {
                wrapperClasses.push(
                    `wp-site-blocks--${matched.value.slug.replace(SLUG_CLASS_PATTERN, '-')}`
                );
            }

            const elementProps: Record<string, string> = {
                class: wrapperClasses.join(' '),
                'data-ve-template': props.slug,
            };

            if (matched.value !== undefined && matched.value.slug !== props.slug) {
                elementProps['data-ve-matched-template'] = matched.value.slug;
            }

            if (matched.value === undefined && isDevelopment()) {
                elementProps['data-ve-resolution-error'] = 'no-matching-template';
                elementProps['data-ve-fallback-chain'] = fallbackChain.value.join(',');
            }

            const globalStylesNode = h(GlobalStyles, { css: props.globalStylesCss });

            if (matched.value === undefined) {
                return h(Fragment, null, [globalStylesNode, h('div', elementProps)]);
            }

            return h(Fragment, null, [
                globalStylesNode,
                h('div', elementProps, [
                    h(BlockTree, {
                        tree: matched.value.blocks,
                        templateParts: props.templateParts,
                        defaultTheme: props.theme ?? matched.value.theme,
                        dynamicBlockEndpoint: props.dynamicBlockEndpoint,
                        fetchOptions: props.fetchOptions,
                        maxTemplatePartDepth: props.maxTemplatePartDepth,
                    }),
                ]),
            ]);
        };
    },
});
