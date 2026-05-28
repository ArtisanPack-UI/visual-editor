/**
 * List item — editor-side render.
 *
 * Simplified port of `@wordpress/block-library/src/list-item/edit.js`
 * (v9.43.0). The upstream edit ships indent/outdent toolbar buttons + the
 * `useEnter`/`useSpace`/`useMerge` hooks that hook into block-editor
 * private dispatch surface to manage nested-list keyboard navigation.
 * This fork ships the RichText editing surface; the indent/outdent
 * keyboard behaviour falls back to the editor's default block-list
 * navigation. Nesting works via standard inner-block insertion.
 */

import type { ReactElement } from 'react';
import {
    RichText,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';

interface ListItemAttributes {
    readonly placeholder?: string;
    readonly content: string;
}

interface ListItemEditProps {
    readonly attributes: ListItemAttributes;
    readonly setAttributes: (next: Partial<ListItemAttributes>) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
}

export default function ListItemEdit({
    attributes,
    setAttributes,
    mergeBlocks,
}: ListItemEditProps): ReactElement {
    const { placeholder, content } = attributes;
    const blockProps = useBlockProps();
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        renderAppender: false,
        __unstableDisableDropZone: true,
    });

    return (
        <li {...innerBlocksProps}>
            <RichText
                identifier="content"
                tagName="div"
                onChange={(nextContent: string) =>
                    setAttributes({ content: nextContent })
                }
                value={content}
                aria-label="List text"
                placeholder={placeholder || 'List'}
                onMerge={mergeBlocks}
            />
            {(innerBlocksProps as { children?: React.ReactNode }).children}
        </li>
    );
}
