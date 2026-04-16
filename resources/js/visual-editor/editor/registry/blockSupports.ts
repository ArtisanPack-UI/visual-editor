import { getBlock } from './blockRegistry';

/**
 * Queries a block's `supports` object by dot-path.
 *
 * Returns `true` when the resolved value is strictly `true`, a non-empty
 * object, or a non-empty array. Returns `false` for `undefined`, `false`,
 * or when the block/path doesn't exist.
 *
 * @example
 * ```ts
 * blockSupports('artisanpack/paragraph', 'splitting');      // true
 * blockSupports('artisanpack/paragraph', 'typography.fontSize'); // true
 * blockSupports('artisanpack/code', 'splitting');            // false
 * ```
 */
export function blockSupports(blockName: string, feature: string): boolean {
    const definition = getBlock(blockName);

    if (!definition?.supports) {
        return false;
    }

    const parts = feature.split('.');
    let current: unknown = definition.supports;

    for (const part of parts) {
        if (current == null || typeof current !== 'object') {
            return false;
        }
        current = (current as Record<string, unknown>)[part];
    }

    if (current === true) {
        return true;
    }

    if (current !== null && typeof current === 'object') {
        if (Array.isArray(current)) {
            return current.length > 0;
        }
        return Object.keys(current).length > 0;
    }

    return false;
}

/**
 * Returns the raw value at a dot-path in the block's supports object.
 * Useful when you need the actual value (e.g. an array of align options)
 * rather than a boolean check.
 */
export function getBlockSupport(blockName: string, feature: string): unknown {
    const definition = getBlock(blockName);

    if (!definition?.supports) {
        return undefined;
    }

    const parts = feature.split('.');
    let current: unknown = definition.supports;

    for (const part of parts) {
        if (current == null || typeof current !== 'object') {
            return undefined;
        }
        current = (current as Record<string, unknown>)[part];
    }

    return current;
}
