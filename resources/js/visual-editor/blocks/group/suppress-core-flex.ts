/**
 * Suppress core's Flex layout variation on `artisanpack/group` (#595).
 *
 * Authors should use our `Flex Layout` inspector panel instead of the
 * core layout-type picker's Flex entry. Other layout variations
 * (default, constrained, grid) are untouched.
 *
 * Runs via a `blocks.registerBlockType` filter that strips the flex
 * default from the block's `supports.layout` when present, so the core
 * layout panel does not offer it.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import { addFilter } from '@wordpress/hooks'

const FILTER_HOOK      = 'blocks.registerBlockType'
const FILTER_NAMESPACE = 'artisanpack/visual-editor/suppress-core-flex'

function suppressFlex( settings: any, name: string ): any {
	if ( 'artisanpack/group' !== name ) {
		return settings
	}

	const layoutSupport = settings?.supports?.layout
	if ( ! layoutSupport || 'object' !== typeof layoutSupport ) {
		return settings
	}

	const defaultLayout = layoutSupport.default
	if ( defaultLayout && 'flex' === defaultLayout.type ) {
		return {
			...settings,
			supports: {
				...settings.supports,
				layout: { ...layoutSupport, default: undefined },
			},
		}
	}

	return settings
}

let registered = false

export function registerSuppressCoreFlex(): void {
	if ( registered ) {
		return
	}
	addFilter( FILTER_HOOK, FILTER_NAMESPACE, suppressFlex )
	registered = true
}

registerSuppressCoreFlex()
