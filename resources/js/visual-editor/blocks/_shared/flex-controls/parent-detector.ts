/**
 * Detect whether a block is the direct child of a flex container (#595).
 *
 * Walks up the block tree once and reads the parent's `artisanpackFlex`
 * attribute. Returns the relevant container slice so child controls can
 * render with awareness of (or be disabled by) the parent.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import { useSelect } from '@wordpress/data'
import { store as blockEditorStore } from '@wordpress/block-editor'

import type { ArtisanpackFlexAttribute, FlexContainerAttributes } from './types'

const FLEX_PARENT_BLOCKS = new Set<string>( [
	'artisanpack/group',
	'artisanpack/column',
	'artisanpack/columns',
	'artisanpack/grid-item',
] )

export interface FlexParentInfo {
	isFlexChild: boolean
	parentName: string | null
	parentContainer: FlexContainerAttributes | null
}

export function useFlexParent( clientId: string ): FlexParentInfo {
	return useSelect( ( select: any ): FlexParentInfo => {
		const { getBlockRootClientId, getBlockName, getBlockAttributes } =
			select( blockEditorStore )

		const rootId = getBlockRootClientId( clientId )

		if ( ! rootId ) {
			return { isFlexChild: false, parentName: null, parentContainer: null }
		}

		const parentName = getBlockName( rootId ) as string | null

		if ( ! parentName || ! FLEX_PARENT_BLOCKS.has( parentName ) ) {
			return { isFlexChild: false, parentName: parentName ?? null, parentContainer: null }
		}

		const parentAttributes = getBlockAttributes( rootId ) as { artisanpackFlex?: ArtisanpackFlexAttribute } | null
		const container        = parentAttributes?.artisanpackFlex?.container ?? null

		// `artisanpack/columns` is implicitly a flex container by core's
		// layout default, so we treat children of it as flex children
		// regardless of whether our attribute is set.
		const isImplicitFlex = 'artisanpack/columns' === parentName

		return {
			isFlexChild: isImplicitFlex || hasAnyFlexEnabled( container ),
			parentName,
			parentContainer: container,
		}
	}, [ clientId ] )
}

function hasAnyFlexEnabled( container: FlexContainerAttributes | null ): boolean {
	if ( ! container?.enabled ) {
		return false
	}

	const value = container.enabled as unknown

	if ( null === value || undefined === value ) {
		return false
	}

	if ( 'boolean' === typeof value ) {
		return value
	}

	if ( 'object' === typeof value ) {
		const slots = value as Record<string, unknown>
		return Object.values( slots ).some( ( v ) => true === v )
	}

	return false
}
