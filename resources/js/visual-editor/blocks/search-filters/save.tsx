/**
 * Search Filters — save component.
 *
 * Dynamic block: the renderers walk the persisted inner-block tree at
 * render time so the wrapper form's `action` can resolve to the host
 * site's home URL. But the block hosts inner blocks inside a wrapper
 * `<div>` (see `edit.tsx`), so `save()` must reproduce that wrapper —
 * mirroring the editor's `useBlockProps` className — or Gutenberg's
 * validator flags the saved markup as "unexpected or invalid content"
 * (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function SearchFiltersSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({
        className: 'ap-search-filters',
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return <div {...innerBlocksProps} />;
}
