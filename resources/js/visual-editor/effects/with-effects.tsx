/**
 * Effects inspector panel + canvas preview (#881).
 *
 * Adds an "Effects" panel to the Styles tab of every block that takes
 * effects (opacity, overflow, backdrop filter, blend mode, isolation,
 * z-index, clip shape, text clip and, for users with the block-CSS
 * ability, Custom CSS), and previews them in the canvas by adding the
 * compiled scope class to the block wrapper and publishing its rule into
 * the shared canvas style host.
 *
 * Nothing is added to saved markup: the renderers compile the same CSS
 * from the `artisanpackEffects` attribute.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

import { InspectorControls } from '@wordpress/block-editor'
import { getBlockType } from '@wordpress/blocks'
import {
	RangeControl,
	SelectControl,
	TextareaControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components'
import { createHigherOrderComponent } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { __ } from '@wordpress/i18n'
import { useMemo } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { useCanvasScopedStyle } from '../support/canvas-scoped-styles'
import { canEditBlockCss } from './access'
import {
	BLEND_MODES,
	CLIP_SHAPES,
	EFFECTS_ATTRIBUTE,
	OVERFLOW_VALUES,
	compileEffects,
	sanitizeCustomCss,
} from './compiler'
import { effectsEnabled } from './register-attribute'

const CONTROL_HOOK     = 'editor.BlockEdit'
const STYLES_HOOK      = 'editor.BlockListBlock'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/effects'

/**
 * Shared canvas style host the effects rules are published into.
 *
 * @since 1.15.0
 */
export const EFFECTS_STYLE_CHANNEL = 'effects'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.effects.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

type Effects = Record<string, unknown>

interface BlockProps {
	name: string
	clientId: string
	attributes: Record<string, unknown>
	setAttributes?: ( updates: Record<string, unknown> ) => void
	isSelected?: boolean
	wrapperProps?: Record<string, unknown>
	[key: string]: unknown
}

function blockTakesEffects( name: string ): boolean {
	return effectsEnabled( getBlockType( name ) as { supports?: Record<string, unknown> } | undefined )
}

function readEffects( attributes: Record<string, unknown> ): Effects {
	const raw = attributes[ EFFECTS_ATTRIBUTE ]

	return raw && 'object' === typeof raw && ! Array.isArray( raw ) ? ( raw as Effects ) : {}
}

function keywordOptions( values: readonly string[] ): Array<{ label: string, value: string }> {
	return [
		{ label: __( 'Default', 'artisanpack-visual-editor' ), value: '' },
		...values.map( ( value ) => ( { label: value, value } ) ),
	]
}

function numberOrUndefined( value: unknown ): number | undefined {
	if ( '' === value || null === value || undefined === value ) {
		return undefined
	}

	const numeric = Number( value )

	return Number.isFinite( numeric ) ? Math.round( numeric ) : undefined
}

const CLIP_SHAPE_LABELS: Record<string, string> = {
	circle:   __( 'Circle', 'artisanpack-visual-editor' ),
	ellipse:  __( 'Ellipse', 'artisanpack-visual-editor' ),
	triangle: __( 'Triangle', 'artisanpack-visual-editor' ),
	diamond:  __( 'Diamond', 'artisanpack-visual-editor' ),
	hexagon:  __( 'Hexagon', 'artisanpack-visual-editor' ),
	slant:    __( 'Slanted bottom', 'artisanpack-visual-editor' ),
}

export const withEffectsControl = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockProps> ) => {
		function EffectsControlledBlock( props: BlockProps ): JSX.Element {
			const { name, clientId, attributes, setAttributes, isSelected } = props

			if ( ! isSelected || ! setAttributes || ! blockTakesEffects( name ) ) {
				return <BlockEdit { ...props } />
			}

			const effects = readEffects( attributes )

			const update = ( key: string, value: unknown ): void => {
				const next: Effects = { ...effects }

				if ( undefined === value || '' === value || false === value ) {
					delete next[ key ]
				} else {
					next[ key ] = value
				}

				setAttributes( { [ EFFECTS_ATTRIBUTE ]: Object.keys( next ).length > 0 ? next : undefined } )
			}

			const resetAll = (): void => {
				// Users without the block-CSS ability can't see the field,
				// so a reset leaves the Custom CSS an administrator set.
				const kept = canEditBlockCss() || undefined === effects.customCss
					? undefined
					: { customCss: effects.customCss }

				setAttributes( { [ EFFECTS_ATTRIBUTE ]: kept } )
			}

			const item = ( key: string, label: string, control: ReactNode ): JSX.Element => (
				<ToolsPanelItem
					key={ key }
					panelId={ clientId }
					label={ label }
					hasValue={ () => undefined !== effects[ key ] }
					onDeselect={ () => update( key, undefined ) }
					isShownByDefault={ 'opacity' === key }
				>
					{ control }
				</ToolsPanelItem>
			)

			const customCss       = 'string' === typeof effects.customCss ? effects.customCss : ''
			const customCssResult = sanitizeCustomCss( customCss )

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls group="styles">
						<ToolsPanel
							label={ __( 'Effects', 'artisanpack-visual-editor' ) }
							resetAll={ resetAll }
							panelId={ clientId }
						>
							{ item( 'opacity', __( 'Opacity', 'artisanpack-visual-editor' ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Opacity (%)', 'artisanpack-visual-editor' ) }
									min={ 0 }
									max={ 100 }
									value={ numberOrUndefined( effects.opacity ) ?? 100 }
									onChange={ ( value ) => update( 'opacity', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'overflowX', __( 'Overflow (horizontal)', 'artisanpack-visual-editor' ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Overflow (horizontal)', 'artisanpack-visual-editor' ) }
									value={ 'string' === typeof effects.overflowX ? effects.overflowX : '' }
									options={ keywordOptions( OVERFLOW_VALUES ) }
									onChange={ ( value ) => update( 'overflowX', value ) }
								/>
							) ) }
							{ item( 'overflowY', __( 'Overflow (vertical)', 'artisanpack-visual-editor' ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Overflow (vertical)', 'artisanpack-visual-editor' ) }
									value={ 'string' === typeof effects.overflowY ? effects.overflowY : '' }
									options={ keywordOptions( OVERFLOW_VALUES ) }
									onChange={ ( value ) => update( 'overflowY', value ) }
								/>
							) ) }
							{ item( 'backdropBlur', __( 'Backdrop blur', 'artisanpack-visual-editor' ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Backdrop blur (px)', 'artisanpack-visual-editor' ) }
									help={ __( 'Blurs whatever shows through a translucent background, for frosted glass.', 'artisanpack-visual-editor' ) }
									min={ 0 }
									max={ 100 }
									value={ numberOrUndefined( effects.backdropBlur ) ?? 0 }
									onChange={ ( value ) => update( 'backdropBlur', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'backdropSaturate', __( 'Backdrop saturation', 'artisanpack-visual-editor' ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Backdrop saturation (%)', 'artisanpack-visual-editor' ) }
									min={ 0 }
									max={ 300 }
									value={ numberOrUndefined( effects.backdropSaturate ) ?? 100 }
									onChange={ ( value ) => update( 'backdropSaturate', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'mixBlendMode', __( 'Blend mode', 'artisanpack-visual-editor' ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Blend mode', 'artisanpack-visual-editor' ) }
									value={ 'string' === typeof effects.mixBlendMode ? effects.mixBlendMode : '' }
									options={ keywordOptions( BLEND_MODES ) }
									onChange={ ( value ) => update( 'mixBlendMode', value ) }
								/>
							) ) }
							{ item( 'zIndex', __( 'Z-index', 'artisanpack-visual-editor' ), (
								<NumberControl
									__next40pxDefaultSize
									label={ __( 'Z-index', 'artisanpack-visual-editor' ) }
									help={ __( 'Stacks the block above or below its neighbours. A block that isn’t positioned becomes position: relative.', 'artisanpack-visual-editor' ) }
									value={ numberOrUndefined( effects.zIndex ) ?? '' }
									onChange={ ( value: string | undefined ) => update( 'zIndex', numberOrUndefined( value ) ) }
								/>
							) ) }
							{ item( 'isolation', __( 'Isolate', 'artisanpack-visual-editor' ), (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Isolate', 'artisanpack-visual-editor' ) }
									help={ __( 'Starts a new stacking context, so blend modes inside the block don’t mix with what’s behind it.', 'artisanpack-visual-editor' ) }
									checked={ true === effects.isolation }
									onChange={ ( value ) => update( 'isolation', value ) }
								/>
							) ) }
							{ item( 'clipShape', __( 'Clip shape', 'artisanpack-visual-editor' ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Clip shape', 'artisanpack-visual-editor' ) }
									value={ 'string' === typeof effects.clipShape ? effects.clipShape : '' }
									options={ [
										{ label: __( 'None', 'artisanpack-visual-editor' ), value: '' },
										...Object.keys( CLIP_SHAPES ).map( ( slug ) => ( { label: CLIP_SHAPE_LABELS[ slug ] ?? slug, value: slug } ) ),
									] }
									onChange={ ( value ) => update( 'clipShape', value ) }
								/>
							) ) }
							{ item( 'textClip', __( 'Clip background to text', 'artisanpack-visual-editor' ), (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Clip background to text', 'artisanpack-visual-editor' ) }
									help={ __( 'Paints the background only inside the letters. Pair it with a gradient background for gradient text.', 'artisanpack-visual-editor' ) }
									checked={ true === effects.textClip }
									onChange={ ( value ) => update( 'textClip', value ) }
								/>
							) ) }
							{ canEditBlockCss() && item( 'customCss', __( 'Custom CSS', 'artisanpack-visual-editor' ), (
								<TextareaControl
									__nextHasNoMarginBottom
									className="ap-effects-custom-css"
									label={ __( 'Custom CSS', 'artisanpack-visual-editor' ) }
									help={ null === customCssResult
										? __( 'This CSS can’t be used: check for unclosed braces, strings or comments, “<”, backslashes, or at-rules other than @media, @supports and @container. It won’t be printed.', 'artisanpack-visual-editor' )
										: __( 'Applies to this block only. Write declarations for the block, or nest rules with “&”, e.g. “& img { border-radius: 8px; }”.', 'artisanpack-visual-editor' ) }
									value={ customCss }
									rows={ 6 }
									spellCheck={ false }
									onChange={ ( value ) => update( 'customCss', value ) }
								/>
							) ) }
						</ToolsPanel>
					</InspectorControls>
				</>
			)
		}

		EffectsControlledBlock.displayName = 'EffectsControlledBlock'

		return EffectsControlledBlock
	},
	'withEffectsControl',
)

export const withEffectsStyles = createHigherOrderComponent(
	( BlockListBlock: ComponentType<BlockProps> ) => {
		function EffectsStyledBlock( props: BlockProps ): JSX.Element {
			const { clientId, attributes, wrapperProps } = props

			const compiled = useMemo( () => compileEffects( attributes ), [ attributes ] )

			useCanvasScopedStyle( EFFECTS_STYLE_CHANNEL, clientId, compiled?.css ?? '' )

			const effectiveWrapperProps = useMemo( () => {
				if ( null === compiled ) {
					return wrapperProps
				}

				const existing = ( wrapperProps?.className as string | undefined ) ?? ''

				return {
					...( wrapperProps ?? {} ),
					className: existing ? `${ existing } ${ compiled.className }` : compiled.className,
				}
			}, [ compiled, wrapperProps ] )

			return <BlockListBlock { ...props } wrapperProps={ effectiveWrapperProps } />
		}

		EffectsStyledBlock.displayName = 'EffectsStyledBlock'

		return EffectsStyledBlock
	},
	'withEffectsStyles',
)

/**
 * Install the panel and canvas-preview filters. Idempotent — safe to
 * call from both editor bootstraps.
 *
 * @since 1.15.0
 */
export function registerEffectsFilters(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( CONTROL_HOOK, FILTER_NAMESPACE, withEffectsControl )
	addFilter( STYLES_HOOK, FILTER_NAMESPACE, withEffectsStyles )
	host[ REGISTERED_KEY ] = true
}
