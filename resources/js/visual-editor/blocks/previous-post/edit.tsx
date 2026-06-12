/**
 * Previous Post — editor-side component (#499).
 *
 * Container block: hosts one or more post-* children whose data will be
 * re-resolved against the previous adjacent post in the same post type
 * at render time. The canvas previews the children against the host
 * post (or the active query-preview entry, if any) — server-side
 * `PostResolver` swaps the post context to the resolved adjacent post
 * before the renderer walks the inner tree.
 *
 * No inspector controls of its own — the children carry their own
 * styling / layout knobs; this block is purely the wrapper that scopes
 * them to the previous post.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
];

export default function PreviousPostEdit(): ReactElement {
    const blockProps = useBlockProps({ className: 'wp-block-artisanpack-previous-post navigation-post' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

    return <div {...innerBlocksProps} />;
}
