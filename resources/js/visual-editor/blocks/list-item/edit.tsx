/**
 * List item — editor-side render.
 *
 * TypeScript port of `@wordpress/block-library/src/list-item/edit.js`
 * (v9.43.0). Upstream's `useMerge` landed in #1043; `useEnter`, `useSpace`,
 * `useIndentListItem` and the indent/outdent toolbar landed in #1101, so
 * Enter on an empty item leaves the list and Tab / Shift+Tab nest items.
 */

import type { ReactElement } from 'react';
import {
    BlockControls,
    RichText,
    store as blockEditorStore,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import { useMergeRefs } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { __, isRTL } from '@wordpress/i18n';
import {
    formatIndent,
    formatIndentRTL,
    formatOutdent,
    formatOutdentRTL,
} from '@wordpress/icons';
import { displayShortcut } from '@wordpress/keycodes';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import metadata from './block.json';
import useEnter from './hooks/use-enter';
import useIndentListItem from './hooks/use-indent-list-item';
import useMerge from './hooks/use-merge';
import useOutdentListItem from './hooks/use-outdent-list-item';
import useSpace from './hooks/use-space';

const { name: LIST_ITEM_BLOCK } = metadata;

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

interface IndentUIProps {
    readonly clientId: string;
}

interface IndentSelectors {
    getBlockIndex(clientId: string): number;
    getBlockRootClientId(clientId: string | null): string | null;
    getBlockName(clientId: string | null): string | null;
}

/**
 * Indent / Outdent toolbar buttons for a list item.
 *
 * @param props          Component props.
 * @param props.clientId The list item's client ID.
 *
 * @return The two toolbar buttons, disabled where the move isn't possible.
 */
export function IndentUI({ clientId }: IndentUIProps): ReactElement {
    const indentListItem = useIndentListItem(clientId);
    const outdentListItem = useOutdentListItem();
    const { canIndent, canOutdent } = useSelect(
        (select) => {
            const { getBlockIndex, getBlockRootClientId, getBlockName } =
                select(blockEditorStore) as unknown as IndentSelectors;

            return {
                canIndent: getBlockIndex(clientId) > 0,
                canOutdent:
                    getBlockName(
                        getBlockRootClientId(getBlockRootClientId(clientId))
                    ) === LIST_ITEM_BLOCK,
            };
        },
        [clientId]
    );

    return (
        <>
            <ToolbarButton
                icon={isRTL() ? formatOutdentRTL : formatOutdent}
                title={__('Outdent', TEXT_DOMAIN)}
                shortcut={displayShortcut.shift('Tab')}
                description={__('Outdent list item', TEXT_DOMAIN)}
                disabled={!canOutdent}
                onClick={() => outdentListItem()}
            />
            <ToolbarButton
                icon={isRTL() ? formatIndentRTL : formatIndent}
                title={__('Indent', TEXT_DOMAIN)}
                shortcut="Tab"
                description={__('Indent list item', TEXT_DOMAIN)}
                disabled={!canIndent}
                onClick={() => indentListItem()}
            />
        </>
    );
}

/**
 * Renders a list item's RichText, its nested list and the indent toolbar,
 * with the Enter, Space/Tab and merge keyboard hooks attached.
 *
 * @param props Block edit props.
 *
 * @return The editor-side list item.
 */
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
    const useEnterRef = useEnter({ content, clientId });
    const useSpaceRef = useSpace(clientId);
    const onMerge = useMerge(clientId, mergeBlocks);

    return (
        <>
            <li {...innerBlocksProps}>
                <RichText
                    ref={useMergeRefs([useEnterRef, useSpaceRef])}
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
            <BlockControls group="block">
                <IndentUI clientId={clientId} />
            </BlockControls>
        </>
    );
}
