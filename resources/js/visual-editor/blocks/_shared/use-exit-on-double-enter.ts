/**
 * Double-Enter exit for whitespace-preserving text blocks.
 *
 * Code, Preformatted and Verse keep Enter as a newline. Upstream exits them
 * on a triple Enter (RichText's `__unstableOnSplitAtDoubleLineEnd` fires
 * only when the text already ends in two newlines). This hook exits on a
 * double Enter instead (#1101): Enter with a collapsed caret at the very end
 * of text that already ends in a newline drops that trailing newline and
 * inserts a default block after this one, in one batch so a single undo
 * reverts both.
 *
 * The trade-off is deliberate: a trailing blank line can't be typed at the
 * end of the block, but blank lines in the middle still work, and
 * Shift+Enter always inserts a newline.
 *
 * Attach the returned ref to the block's RichText element. It runs before
 * RichText's own Enter handling, which it pre-empts with `preventDefault`.
 */

import { useRef } from 'react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { useRefEffect } from '@wordpress/compose';
import { useRegistry, useSelect } from '@wordpress/data';
import { ENTER } from '@wordpress/keycodes';
import { RichTextData, remove, toHTMLString } from '@wordpress/rich-text';
import type { RichTextValue } from '@wordpress/rich-text';

import { hasBoundRichText } from '../../bindings/bound-rich-text';

interface SelectionPoint {
    readonly clientId?: string;
    readonly attributeKey?: string;
    readonly offset?: number;
}

interface BlockEditorSelectors {
    getSelectionStart(): SelectionPoint;
    getSelectionEnd(): SelectionPoint;
    getBlockAttributes(clientId: string): Record<string, unknown> | null;
    getBlockName(clientId: string): string | null;
    getBlockRootClientId(clientId: string): string | null;
    canInsertBlockType(blockName: string, rootClientId: string | null): boolean;
}

export interface UseExitOnDoubleEnterOptions {
    /** The block's client ID. */
    readonly clientId: string;
    /** The block's `setAttributes`. */
    readonly setAttributes: (next: Record<string, unknown>) => void;
    /** The block's `insertBlocksAfter`; without it the hook does nothing. */
    readonly insertBlocksAfter?: (blocks: BlockInstance | BlockInstance[]) => void;
    /** The rich-text attribute the RichText edits. Defaults to `content`. */
    readonly attributeKey?: string;
}

/**
 * Parses a rich-text attribute the way RichText does, keeping whitespace.
 */
function toRecord(value: unknown): RichTextValue {
    let data: RichTextData;

    if (value instanceof RichTextData) {
        data = value;
    } else if ('string' === typeof value && '' !== value) {
        data = RichTextData.fromHTMLString(value);
    } else {
        data = RichTextData.empty();
    }

    return {
        text: data.text,
        formats: data.formats,
        replacements: data.replacements,
    } as unknown as RichTextValue;
}

/**
 * Serializes a record back into the same shape the attribute had, matching
 * RichText's own `onChange` output.
 */
function fromRecord(original: unknown, record: RichTextValue): unknown {
    if (original instanceof RichTextData) {
        return new RichTextData(record as never);
    }

    return toHTMLString({ value: record, preserveWhiteSpace: true } as never);
}

/**
 * Returns a ref that exits the block on Enter at the end of a blank last
 * line, as described in the file header.
 *
 * @param options Hook options.
 *
 * @return A ref callback for the block's RichText element.
 */
export function useExitOnDoubleEnter(
    options: UseExitOnDoubleEnterOptions
): (node: HTMLElement | null) => void {
    const registry = useRegistry();
    const {
        getSelectionStart,
        getSelectionEnd,
        getBlockAttributes,
        getBlockName,
        getBlockRootClientId,
        canInsertBlockType,
    } = useSelect(blockEditorStore) as unknown as BlockEditorSelectors;
    const optionsRef = useRef<UseExitOnDoubleEnterOptions>(options);
    optionsRef.current = options;

    return useRefEffect((element: HTMLElement) => {
        function onKeyDown(event: KeyboardEvent): void {
            if (
                event.defaultPrevented ||
                event.keyCode !== ENTER ||
                event.isComposing ||
                event.shiftKey ||
                event.altKey ||
                event.ctrlKey ||
                event.metaKey
            ) {
                return;
            }

            const {
                clientId,
                setAttributes,
                insertBlocksAfter,
                attributeKey = 'content',
            } = optionsRef.current;
            const defaultBlockName = getDefaultBlockName();

            if (!insertBlocksAfter || !defaultBlockName) {
                return;
            }

            const start = getSelectionStart();
            const end = getSelectionEnd();

            if (
                start.clientId !== clientId ||
                end.clientId !== clientId ||
                start.attributeKey !== attributeKey ||
                end.attributeKey !== attributeKey ||
                start.offset !== end.offset
            ) {
                return;
            }

            const attributes = getBlockAttributes(clientId);

            // A bound block shows its data, not the stored text this hook
            // would trim.
            if (hasBoundRichText(attributes, getBlockName(clientId))) {
                return;
            }

            const original = attributes?.[attributeKey];
            const record = toRecord(original);
            const { text } = record;

            if (end.offset !== text.length || !text.endsWith('\n')) {
                return;
            }

            // Where the default block can't go, keep Enter as a newline
            // rather than trimming the line with nowhere to exit to.
            if (
                !canInsertBlockType(
                    defaultBlockName,
                    getBlockRootClientId(clientId)
                )
            ) {
                return;
            }

            event.preventDefault();

            registry.batch(() => {
                setAttributes({
                    [attributeKey]: fromRecord(
                        original,
                        remove(record, text.length - 1, text.length)
                    ),
                });
                insertBlocksAfter(createBlock(defaultBlockName));
            });
        }

        element.addEventListener('keydown', onKeyDown);

        return () => {
            element.removeEventListener('keydown', onKeyDown);
        };
    }, []);
}
