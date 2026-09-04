/**
 * Business Hours — save component.
 *
 * Dynamic block: the hours envelope is resolved server-side from the
 * `ap.visualEditor.businessInfo` filter and stamped onto the block via
 * BusinessInfoResolver. Returning `null` from save is the Gutenberg
 * convention for dynamic blocks.
 */

export default function BusinessHoursSave(): null {
    return null;
}
