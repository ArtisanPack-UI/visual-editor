/**
 * Inline-icon RichText format — value operations (#717).
 *
 * Thin wrappers over `@wordpress/rich-text` primitives that insert,
 * replace, and remove the inline-icon object at the current selection.
 * Kept separate from the React `edit` component so they can be unit
 * tested against the real rich-text engine.
 */

import { insertObject, remove } from '@wordpress/rich-text';

import type { InlineIconObject } from './settings';

/**
 * Minimal structural view of a `@wordpress/rich-text` value — just the
 * fields these helpers touch. Avoids depending on the package's exported
 * type surface, which varies across versions.
 */
export interface RichTextValueLike {
    readonly start?: number;
    readonly end?: number;
    readonly replacements: unknown[];
    readonly formats: unknown[];
    readonly text: string;
}

/**
 * Insert an inline icon at the current selection, replacing any selected
 * text with the object character.
 */
export function insertInlineIcon< T extends RichTextValueLike >(
    value: T,
    object: InlineIconObject,
): T {
    return insertObject( value as never, object as never ) as unknown as T;
}

/**
 * Replace the icon object under the caret in place, preserving its
 * position. Used by the popover's size / colour / replace controls.
 */
export function replaceActiveInlineIcon< T extends RichTextValueLike >(
    value: T,
    object: InlineIconObject,
): T {
    const replacements = value.replacements.slice();
    const index        = value.start ?? 0;
    replacements[ index ] = object;

    return { ...value, replacements } as T;
}

/**
 * Remove the icon object under the caret / current selection.
 */
export function removeActiveInlineIcon< T extends RichTextValueLike >( value: T ): T {
    return remove( value as never, value.start as never, value.end as never ) as unknown as T;
}
