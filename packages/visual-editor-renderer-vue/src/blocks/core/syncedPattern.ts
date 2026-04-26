/**
 * `core/block` (synced-pattern reference) renderer.
 *
 * Operates on a tree where the synced-pattern reference already carries
 * its resolved pattern's blocks under `innerBlocks` (see
 * {@link inlinePatterns}). The renderer wraps the children in a
 * `wp-block-block` div and stamps the pattern id onto a data attribute
 * so client-side scripts can target the rendered region.
 *
 * If the inliner could not resolve the pattern, it stamps an
 * `_resolutionError` attribute. In production this renderer emits an
 * empty wrapper so the surrounding layout stays intact; in dev the
 * wrapper carries a `data-ve-resolution-error` attribute the developer
 * can spot in the inspector.
 */

import { defineComponent, h } from 'vue';
import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

function isDevelopment(): boolean {
    if (typeof process === 'undefined') {
        return false;
    }

    const env = process.env?.NODE_ENV;

    return env !== 'production';
}

function refString(value: unknown): string {
    if (typeof value === 'number' && Number.isInteger(value)) {
        return String(value);
    }

    if (typeof value === 'string') {
        return value.trim();
    }

    return '';
}

export const SyncedPatternBlock = defineComponent({
    name: 'SyncedPatternBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const ref = refString(props.attributes.ref);
            const resolutionError = attrString(props.attributes._resolutionError);
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-block', className]);

            const elementProps: Record<string, string> = {
                class: classes,
            };

            if (ref !== '') {
                elementProps['data-ve-pattern-ref'] = ref;
            }

            if (resolutionError !== '' && isDevelopment()) {
                elementProps['data-ve-resolution-error'] = resolutionError;
            }

            const children = resolutionError !== '' ? [] : slots.default ? slots.default() : [];

            return h('div', elementProps, children);
        };
    },
});
