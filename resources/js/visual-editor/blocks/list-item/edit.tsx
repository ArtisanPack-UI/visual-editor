/**
 * List item — editor-side render.
 *
 * Simplified port of `@wordpress/block-library/src/list-item/edit.js`
 * (v9.43.0). The upstream edit ships indent/outdent toolbar buttons + the
 * `useEnter`/`useSpace` hooks that manage nested-list keyboard navigation.
 * This fork ships the RichText editing surface and upstream's `useMerge`
 * (#1043); the indent/outdent keyboard behaviour falls back to the editor's
 * default block-list navigation. Nesting works via standard inner-block
 * insertion.
 */

import type { ReactElement } from 'react';
import {
    RichText,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import useMerge from './hooks/use-merge';

interface ListItemAttributes {
    readonly placeholder?: string;
    readonly content: string;
}

interface ListItemEditProps {
    readonly attributes: ListItemAttributes;
    readonly setAttributes: (next: Partial<ListItemAttributes>) => void;
    readonly clientId: string;
    readonly mergeBlocks?: (forward?: boolean) => void;
}

export default function ListItemEdit({
    attributes,
    setAttributes,
    clientId,
    mergeBlocks,
}: ListItemEditProps): ReactElement {
    const { placeholder, content } = attributes;
    const blockProps = useBlockProps();
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        renderAppender: false,
        __unstableDisableDropZone: true,
    });
    const onMerge = useMerge(clientId, mergeBlocks);

    return (
        <li {...innerBlocksProps}>
            <RichText
                identifier="content"
                tagName="div"
                onChange={(nextContent: string) =>
                    setAttributes({ content: nextContent })
                }
                value={content}
                aria-label={__('List text', TEXT_DOMAIN)}
                placeholder={placeholder || __('List', TEXT_DOMAIN)}
                onMerge={onMerge}
            />
            {(innerBlocksProps as { children?: React.ReactNode }).children}
        </li>
    );
}
