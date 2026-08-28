/**
 * Single Post Type Search Results — save component.
 *
 * Dynamic block: each renderer evaluates the `_resolvedActive` stamp (or
 * falls back to `?post_type=` matching) and emits the wrapper + inner
 * blocks only when the section applies to the current request. But the
 * block hosts inner blocks inside a wrapper `<div>` (see `edit.tsx`), so
 * `save()` must reproduce that wrapper — mirroring the editor's
 * `useBlockProps` className — or Gutenberg's validator flags the saved
 * markup as "unexpected or invalid content" (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function SinglePostTypesSearchResultsSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({
        className: 'ap-single-post-types-search-results',
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
