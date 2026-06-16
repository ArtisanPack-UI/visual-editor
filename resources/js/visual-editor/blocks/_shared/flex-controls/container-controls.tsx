/**
 * Flex container inspector panel (#595).
 *
 * Renders the per-breakpoint flex container controls inside the block
 * inspector. Reads/writes the `artisanpackFlex.container` slice at the
 * currently active breakpoint.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react'
import {
	PanelBody,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalNumberControl as NumberControl,
	TextControl,
} from '@wordpress/components'
import { __ } from '@wordpress/i18n'

import { BreakpointRegistry } from '../../../responsive/registry'
import { ViewportSwitcher } from '../../../responsive/ViewportSwitcher'
import {
	getActiveBreakpoint,
	subscribeActiveBreakpoint,
} from '../../../responsive/active-breakpoint'
import { useEffect, useMemo, useState } from '@wordpress/element'

import { readAt, writeAt } from './responsive-helpers'
import type {
	AlignContentValue,
	AlignItemsValue,
	ArtisanpackFlexAttribute,
	FlexContainerAttributes,
	FlexDirectionValue,
	FlexWrapValue,
	JustifyContentValue,
} from './types'

interface ContainerControlsProps {
	flex: ArtisanpackFlexAttribute | null | undefined
	onChange: ( next: ArtisanpackFlexAttribute ) => void
	registry?: BreakpointRegistry
}

function useActiveBreakpointValue(): string {
	const [ active, setActive ] = useState<string>( () => getActiveBreakpoint() )

	useEffect( () => {
		return subscribeActiveBreakpoint( ( bp ) => setActive( bp ) )
	}, [] )

	return active
}

function updateContainer(
	flex: ArtisanpackFlexAttribute | null | undefined,
	patch: Partial<FlexContainerAttributes>,
): ArtisanpackFlexAttribute {
	const base = flex ?? {}
	return {
		...base,
		container: {
			...( base.container ?? {} ),
			...patch,
		},
	}
}

export function FlexContainerControls( {
	flex,
	onChange,
	registry,
}: ContainerControlsProps ): ReactElement {
	const breakpointRegistry = useMemo( () => registry ?? new BreakpointRegistry(), [ registry ] )
	const active             = useActiveBreakpointValue()
	const container          = flex?.container ?? {}

	const enabled        = readAt<boolean | null>( container.enabled, active, breakpointRegistry ) ?? false
	const direction      = readAt<FlexDirectionValue | null>( container.direction, active, breakpointRegistry )
	const wrap           = readAt<FlexWrapValue | null>( container.wrap, active, breakpointRegistry )
	const justifyContent = readAt<JustifyContentValue | null>( container.justifyContent, active, breakpointRegistry )
	const alignItems     = readAt<AlignItemsValue | null>( container.alignItems, active, breakpointRegistry )
	const alignContent   = readAt<AlignContentValue | null>( container.alignContent, active, breakpointRegistry )
	const placeContent   = readAt<string | null>( container.placeContent, active, breakpointRegistry )
	const rowGap         = readAt<string | null>( container.gap?.row, active, breakpointRegistry )
	const columnGap      = readAt<string | null>( container.gap?.column, active, breakpointRegistry )

	const isWrapping     = 'wrap' === wrap || 'wrap-reverse' === wrap
	const hasShorthand   = null !== placeContent

	const setContainerSlot = <K extends keyof FlexContainerAttributes>(
		key: K,
		value: K extends 'gap' ? FlexContainerAttributes[ 'gap' ] : unknown,
	): void => {
		if ( 'gap' === key ) {
			onChange( updateContainer( flex, { gap: value as FlexContainerAttributes[ 'gap' ] } ) )
			return
		}

		const nextValue = writeAt( container[ key ] as never, active, value as never )
		onChange( updateContainer( flex, { [ key ]: nextValue ?? undefined } as Partial<FlexContainerAttributes> ) )
	}

	const setGap = ( axis: 'row' | 'column', value: string | null ): void => {
		const currentGap = container.gap ?? {}
		const nextSlot   = writeAt<string | null>( currentGap[ axis ], active, value )
		setContainerSlot( 'gap', { ...currentGap, [ axis ]: nextSlot ?? undefined } )
	}

	return (
		<PanelBody title={ __( 'Flex Layout', 'artisanpack-visual-editor' ) } initialOpen={ false }>
			<ViewportSwitcher registry={ breakpointRegistry } />

			<ToggleControl
				label={ __( 'Enable Flex', 'artisanpack-visual-editor' ) }
				checked={ true === enabled }
				onChange={ ( v: boolean ) => setContainerSlot( 'enabled', v ? true : null ) }
			/>

			{ true === enabled && (
				<>
					<ToggleGroupControl
						label={ __( 'Direction', 'artisanpack-visual-editor' ) }
						value={ direction ?? '' }
						isBlock
						onChange={ ( v: any ) => setContainerSlot( 'direction', v || null ) }
					>
						<ToggleGroupControlOption value="row" label={ __( 'Row', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="column" label={ __( 'Column', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="row-reverse" label={ __( 'Row rev.', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="column-reverse" label={ __( 'Col rev.', 'artisanpack-visual-editor' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Wrap', 'artisanpack-visual-editor' ) }
						value={ wrap ?? '' }
						isBlock
						onChange={ ( v: any ) => setContainerSlot( 'wrap', v || null ) }
					>
						<ToggleGroupControlOption value="nowrap" label={ __( 'No', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="wrap" label={ __( 'Wrap', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="wrap-reverse" label={ __( 'Rev.', 'artisanpack-visual-editor' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Justify Content', 'artisanpack-visual-editor' ) }
						value={ justifyContent ?? '' }
						isBlock
						onChange={ ( v: any ) => setContainerSlot( 'justifyContent', hasShorthand ? null : ( v || null ) ) }
					>
						<ToggleGroupControlOption value="flex-start" label={ __( 'Start', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="flex-end" label={ __( 'End', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="space-between" label={ __( 'Between', 'artisanpack-visual-editor' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Align Items', 'artisanpack-visual-editor' ) }
						value={ alignItems ?? '' }
						isBlock
						onChange={ ( v: any ) => setContainerSlot( 'alignItems', hasShorthand ? null : ( v || null ) ) }
					>
						<ToggleGroupControlOption value="stretch" label={ __( 'Stretch', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="flex-start" label={ __( 'Start', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="flex-end" label={ __( 'End', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="baseline" label={ __( 'Baseline', 'artisanpack-visual-editor' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Align Content', 'artisanpack-visual-editor' ) }
						value={ alignContent ?? '' }
						isBlock
						disabled={ ! isWrapping }
						onChange={ ( v: any ) => setContainerSlot( 'alignContent', isWrapping ? ( v || null ) : null ) }
					>
						<ToggleGroupControlOption value="stretch" label={ __( 'Stretch', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="flex-start" label={ __( 'Start', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="flex-end" label={ __( 'End', 'artisanpack-visual-editor' ) } />
						<ToggleGroupControlOption value="space-between" label={ __( 'Between', 'artisanpack-visual-editor' ) } />
					</ToggleGroupControl>
					{ ! isWrapping && (
						<p style={ { fontSize: '12px', opacity: 0.7 } }>
							{ __( 'Align Content requires wrapping.', 'artisanpack-visual-editor' ) }
						</p>
					) }

					<details>
						<summary>{ __( 'Advanced', 'artisanpack-visual-editor' ) }</summary>
						<p style={ { fontSize: '12px', opacity: 0.7, margin: '4px 0' } }>
							{ __( 'Reverse helpers affect visual order only — keyboard / screen-reader order follows DOM.', 'artisanpack-visual-editor' ) }
						</p>
						<TextControl
							label={ __( 'Place Content (shorthand)', 'artisanpack-visual-editor' ) }
							value={ placeContent ?? '' }
							help={ __( 'Overrides per-axis Justify/Align controls when set.', 'artisanpack-visual-editor' ) }
							onChange={ ( v: string ) => setContainerSlot( 'placeContent', v ? v : null ) }
						/>
						{ hasShorthand && (
							<button
								type="button"
								onClick={ () => setContainerSlot( 'placeContent', null ) }
								style={ { fontSize: '12px' } }
							>
								{ __( 'Reset shorthand', 'artisanpack-visual-editor' ) }
							</button>
						) }
					</details>

					<TextControl
						label={ __( 'Row Gap', 'artisanpack-visual-editor' ) }
						value={ rowGap ?? '' }
						placeholder={ __( '16px, 1rem…', 'artisanpack-visual-editor' ) }
						onChange={ ( v: string ) => setGap( 'row', v ? v : null ) }
					/>
					<TextControl
						label={ __( 'Column Gap', 'artisanpack-visual-editor' ) }
						value={ columnGap ?? '' }
						placeholder={ __( '16px, 1rem…', 'artisanpack-visual-editor' ) }
						onChange={ ( v: string ) => setGap( 'column', v ? v : null ) }
					/>
				</>
			) }
		</PanelBody>
	)
}

// Re-export for tests that need the helper directly.
export { updateContainer }

// Suppress unused-import warnings for primitives kept for parity with the
// inspector pattern even when this build path doesn't reach them.
void NumberControl
