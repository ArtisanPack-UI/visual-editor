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

import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

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

export function SyncedPatternBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const ref = refString(attributes.ref);
    const resolutionError = attrString(attributes._resolutionError);
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-block', className]);

    const dataAttributes: Record<string, string> = {};

    if (ref !== '') {
        dataAttributes['data-ve-pattern-ref'] = ref;
    }

    if (resolutionError !== '' && isDevelopment()) {
        dataAttributes['data-ve-resolution-error'] = resolutionError;
    }

    if (resolutionError !== '') {
        return <div className={classes} {...dataAttributes} />;
    }

    return (
        <div className={classes} {...dataAttributes}>
            {children}
        </div>
    );
}
