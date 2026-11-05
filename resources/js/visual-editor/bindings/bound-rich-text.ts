/**
 * Whether a block's rich text is bound to data (#1043).
 *
 * Shared by the bindings HOC's merge guard and the list item's own merge
 * hook, which dispatches `mergeBlocks` itself.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { getBlockType } from '@wordpress/blocks';

import type { BindingsMap } from './types';

interface RichTextSchema {
	type?: string | string[];
	source?: string;
}

/**
 * Whether a block has a binding on one of its rich-text attributes.
 *
 * Bindings live in this package's own `attributes.bindings` sidecar, not
 * upstream's `metadata.bindings`, so the block editor's own merge guard
 * for bound blocks never fires (#1043). A merge would write the
 * neighbour's text into the hidden stored value behind the binding.
 *
 * @since 1.20.0
 */
export function hasBoundRichText(
	attributes: Record<string, unknown> | null | undefined,
	blockName: string | null | undefined,
): boolean {
	const bindings = attributes?.bindings as BindingsMap | null | undefined;

	if ( ! bindings || typeof bindings !== 'object' || ! blockName ) {
		return false;
	}

	const schema = ( getBlockType( blockName )?.attributes ?? {} ) as Record<string, RichTextSchema>;

	return Object.keys( bindings ).some( ( key ) => {
		const attribute = schema[ key ];
		const type      = Array.isArray( attribute?.type ) ? attribute?.type[ 0 ] : attribute?.type;

		return 'rich-text' === type || 'rich-text' === attribute?.source || 'html' === attribute?.source;
	} );
}
