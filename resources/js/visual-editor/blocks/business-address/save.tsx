/**
 * Business Address — save component.
 *
 * Dynamic block: the address envelope + map embed URL are resolved
 * server-side and stamped onto the block via BusinessInfoResolver.
 * Returning `null` from save is the Gutenberg convention for dynamic
 * blocks.
 */

export default function BusinessAddressSave(): null {
    return null;
}
