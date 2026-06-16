/**
 * Flex item inspector panel (#595).
 *
 * Renders the per-breakpoint flex child controls inside the block
 * inspector. Disables itself with a tooltip when the parent block is
 * not a flex container.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react'
import {
	PanelBody,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalNumberControl as NumberControl,
	TextControl,
} from '@wordpress/components'
import { __ } from '@wordpress/i18n'
import { useEffect, useMemo, useState } from '@wordpress/element'

import { BreakpointRegistry } from '../../../responsive/registry'
import {
	getActiveBreakpoint,
	subscribeActiveBreakpoint,
} from '../../../responsive/active-breakpoint'

import { readAt, writeAt } from './responsive-helpers'
import type {
	AlignSelfValue,
	ArtisanpackFlexAttribute,
	FlexItemAttributes,
} from './types'
import { useFlexParent } from './parent-detector'

interface ItemControlsProps {
	flex: ArtisanpackFlexAttribute | null | undefined
	clientId: string
	onChange: ( next: ArtisanpackFlexAttribute ) => void
	registry?: BreakpointRegistry
}

function useActive(): string {
	const [ active, setActive ] = useState<string>( () => getActiveBreakpoint() )
	useEffect( () => subscribeActiveBreakpoint( setActive ), [] )
	return active
}

function updateItem(
	flex: ArtisanpackFlexAttribute | null | undefined,
	patch: Partial<FlexItemAttributes>,
): ArtisanpackFlexAttribute {
	const base = flex ?? {}
	return {
		...base,
		item: {
			...( base.item ?? {} ),
			...patch,
		},
	}
}

export function FlexItemControls( {
	flex,
	clientId,
	onChange,
	registry,
}: ItemControlsProps ): ReactElement {
	const breakpointRegistry = useMemo( () => registry ?? new BreakpointRegistry(), [ registry ] )
	const active             = useActive()
	const item               = flex?.item ?? {}
	const parent             = useFlexParent( clientId )

	const alignSelf = readAt<AlignSelfValue | null>( item.alignSelf, active, breakpointRegistry )
	const grow      = readAt<number | null>( item.grow, active, breakpointRegistry )
	const shrink    = readAt<number | null>( item.shrink, active, breakpointRegistry )
	const basis     = readAt<string | null>( item.basis, active, breakpointRegistry )
	const order     = readAt<number | null>( item.order, active, breakpointRegistry )

	const setItemSlot = <K extends keyof FlexItemAttributes>(
		key: K,
		value: unknown,
	): void => {
		const next = writeAt( item[ key ] as never, active, value as never )
		onChange( updateItem( flex, { [ key ]: next ?? undefined } as Partial<FlexItemAttributes> ) )
	}

	const disabled = ! parent.isFlexChild

	return (
		<PanelBody title={ __( 'Flex Item', 'artisanpack-visual-editor' ) } initialOpen={ false }>
			{ disabled && (
				<p style={ { fontSize: '12px', opacity: 0.7 } }>
					{ __( 'Parent is not a flex container. Re-parent under a flex block to apply these values.', 'artisanpack-visual-editor' ) }
				</p>
			) }

			{ /*
			   * Render the controls inside a real <fieldset disabled> so each input/button
			   * is semantically disabled — keyboard focus + form submission are blocked,
			   * matching how WP a11y review expects "not applicable" UI to behave. A
			   * `pointerEvents: 'none'` wrapper alone only stops mouse interaction.
			   */ }
			<fieldset
				disabled={ disabled }
				aria-disabled={ disabled || undefined }
				style={ {
					border: 0,
					padding: 0,
					margin: 0,
					minInlineSize: 0,
					opacity: disabled ? 0.5 : undefined,
				} }
			>
				<ToggleGroupControl
					label={ __( 'Align Self', 'artisanpack-visual-editor' ) }
					value={ alignSelf ?? '' }
					isBlock
					disabled={ disabled }
					onChange={ ( v: any ) => setItemSlot( 'alignSelf', v || null ) }
				>
					<ToggleGroupControlOption value="auto" label={ __( 'Auto', 'artisanpack-visual-editor' ) } />
					<ToggleGroupControlOption value="flex-start" label={ __( 'Start', 'artisanpack-visual-editor' ) } />
					<ToggleGroupControlOption value="center" label={ __( 'Center', 'artisanpack-visual-editor' ) } />
					<ToggleGroupControlOption value="flex-end" label={ __( 'End', 'artisanpack-visual-editor' ) } />
					<ToggleGroupControlOption value="stretch" label={ __( 'Stretch', 'artisanpack-visual-editor' ) } />
					<ToggleGroupControlOption value="baseline" label={ __( 'Baseline', 'artisanpack-visual-editor' ) } />
				</ToggleGroupControl>

				<NumberControl
					label={ __( 'Grow', 'artisanpack-visual-editor' ) }
					value={ null === grow ? '' : String( grow ) }
					min={ 0 }
					max={ 999 }
					disabled={ disabled }
					onChange={ ( v: string | undefined ) => {
						const parsed = '' === v || undefined === v ? null : Number( v )
						setItemSlot( 'grow', null === parsed || Number.isNaN( parsed ) ? null : parsed )
					} }
				/>
				<NumberControl
					label={ __( 'Shrink', 'artisanpack-visual-editor' ) }
					value={ null === shrink ? '' : String( shrink ) }
					min={ 0 }
					max={ 999 }
					disabled={ disabled }
					onChange={ ( v: string | undefined ) => {
						const parsed = '' === v || undefined === v ? null : Number( v )
						setItemSlot( 'shrink', null === parsed || Number.isNaN( parsed ) ? null : parsed )
					} }
				/>
				<TextControl
					label={ __( 'Basis', 'artisanpack-visual-editor' ) }
					value={ basis ?? '' }
					placeholder={ __( 'auto, 50%, 200px…', 'artisanpack-visual-editor' ) }
					disabled={ disabled }
					onChange={ ( v: string ) => setItemSlot( 'basis', v ? v : null ) }
				/>
				<NumberControl
					label={ __( 'Order', 'artisanpack-visual-editor' ) }
					value={ null === order ? '' : String( order ) }
					min={ -999 }
					max={ 999 }
					disabled={ disabled }
					onChange={ ( v: string | undefined ) => {
						const parsed = '' === v || undefined === v ? null : Number( v )
						setItemSlot( 'order', null === parsed || Number.isNaN( parsed ) ? null : parsed )
					} }
				/>
			</fieldset>
		</PanelBody>
	)
}

export { updateItem }
