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

import { speak } from '@wordpress/a11y'
import { InspectorControls } from '@wordpress/block-editor'
import { getBlockType } from '@wordpress/blocks'
import {
	Notice,
	RangeControl,
	SelectControl,
	TextareaControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components'
import { createHigherOrderComponent, useDebounce } from '@wordpress/compose'
import { addFilter } from '@wordpress/hooks'
import { __, _x } from '@wordpress/i18n'
import { useCallback, useEffect, useMemo, useRef } from 'react'
import type { ComponentType, ReactNode } from 'react'

import { useCanvasScopedStyle } from '../support/canvas-scoped-styles'
import { TEXT_DOMAIN } from '../vendor/i18n'
import { canEditBlockCss, customCssAllowsUrls } from './access'
import {
	BLEND_MODES,
	CLIP_SHAPES,
	EFFECTS_ATTRIBUTE,
	OVERFLOW_VALUES,
	compileEffects,
	hasBackgroundFill,
	sanitizeCustomCss,
} from './compiler'
import { effectsEnabled } from './register-attribute'

import './with-effects.css'

/**
 * How long Custom CSS validity must hold before a change is announced,
 * so a rule that is only half-typed isn't read out on every keystroke.
 *
 * @since 1.15.0
 */
export const CUSTOM_CSS_ANNOUNCE_DELAY = 500

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

function keywordOptions(
	values: readonly string[],
	labels: Record<string, string>,
): Array<{ label: string, value: string }> {
	return [
		{ label: __( 'Default', TEXT_DOMAIN ), value: '' },
		...values.map( ( value ) => ( { label: labels[ value ] ?? value, value } ) ),
	]
}

function numberOrUndefined( value: unknown ): number | undefined {
	if ( '' === value || null === value || undefined === value ) {
		return undefined
	}

	const numeric = Number( value )

	return Number.isFinite( numeric ) ? Math.round( numeric ) : undefined
}

/**
 * Translated labels for the overflow keywords. Built at render time, so
 * locale data loaded after this module still applies.
 */
function overflowLabels(): Record<string, string> {
	return {
		visible: _x( 'Visible', 'overflow value', TEXT_DOMAIN ),
		hidden:  _x( 'Hidden', 'overflow value', TEXT_DOMAIN ),
		clip:    _x( 'Clip', 'overflow value', TEXT_DOMAIN ),
		scroll:  _x( 'Scroll', 'overflow value', TEXT_DOMAIN ),
		auto:    _x( 'Auto', 'overflow value', TEXT_DOMAIN ),
	}
}

/**
 * Translated labels for the blend modes. Built at render time.
 */
function blendModeLabels(): Record<string, string> {
	return {
		normal:        _x( 'Normal', 'blend mode', TEXT_DOMAIN ),
		multiply:      _x( 'Multiply', 'blend mode', TEXT_DOMAIN ),
		screen:        _x( 'Screen', 'blend mode', TEXT_DOMAIN ),
		overlay:       _x( 'Overlay', 'blend mode', TEXT_DOMAIN ),
		darken:        _x( 'Darken', 'blend mode', TEXT_DOMAIN ),
		lighten:       _x( 'Lighten', 'blend mode', TEXT_DOMAIN ),
		'color-dodge': _x( 'Color dodge', 'blend mode', TEXT_DOMAIN ),
		'color-burn':  _x( 'Color burn', 'blend mode', TEXT_DOMAIN ),
		'hard-light':  _x( 'Hard light', 'blend mode', TEXT_DOMAIN ),
		'soft-light':  _x( 'Soft light', 'blend mode', TEXT_DOMAIN ),
		difference:    _x( 'Difference', 'blend mode', TEXT_DOMAIN ),
		exclusion:     _x( 'Exclusion', 'blend mode', TEXT_DOMAIN ),
		hue:           _x( 'Hue', 'blend mode', TEXT_DOMAIN ),
		saturation:    _x( 'Saturation', 'blend mode', TEXT_DOMAIN ),
		color:         _x( 'Color', 'blend mode', TEXT_DOMAIN ),
		luminosity:    _x( 'Luminosity', 'blend mode', TEXT_DOMAIN ),
	}
}

/**
 * Translated labels for the clip shapes. Built at render time.
 */
function clipShapeLabels(): Record<string, string> {
	return {
		circle:   __( 'Circle', TEXT_DOMAIN ),
		ellipse:  __( 'Ellipse', TEXT_DOMAIN ),
		triangle: __( 'Triangle', TEXT_DOMAIN ),
		diamond:  __( 'Diamond', TEXT_DOMAIN ),
		hexagon:  __( 'Hexagon', TEXT_DOMAIN ),
		slant:    __( 'Slanted bottom', TEXT_DOMAIN ),
	}
}

interface CustomCssFieldProps {
	value: string
	onChange: ( value: string ) => void
}

/**
 * The Custom CSS textarea. Marks invalid CSS with `aria-invalid`, a
 * visible error style and an "Error:" prefix (so color isn't the only
 * cue), and announces valid ⇄ invalid changes once the value has held
 * for {@link CUSTOM_CSS_ANNOUNCE_DELAY} ms.
 */
function CustomCssField( { value, onChange }: CustomCssFieldProps ): JSX.Element {
	const allowUrls   = customCssAllowsUrls()
	const invalid     = null === sanitizeCustomCss( value, allowUrls )
	const urlsBlocked = invalid && ! allowUrls && null !== sanitizeCustomCss( value, true )
	const announced   = useRef( invalid )

	const announceChange = useCallback( ( nextInvalid: boolean ): void => {
		if ( nextInvalid === announced.current ) {
			return
		}

		announced.current = nextInvalid

		speak( nextInvalid
			? __( 'Custom CSS has an error and won’t be printed.', TEXT_DOMAIN )
			: __( 'Custom CSS is valid.', TEXT_DOMAIN ) )
	}, [] )

	const debouncedAnnounce = useDebounce( announceChange, CUSTOM_CSS_ANNOUNCE_DELAY )

	useEffect( () => {
		debouncedAnnounce( invalid )
	}, [ invalid, debouncedAnnounce ] )

	useEffect( () => () => debouncedAnnounce.cancel(), [ debouncedAnnounce ] )

	let help: ReactNode = __( 'Applies to this block only. Write declarations for the block, or nest rules with “&”, e.g. “& img { border-radius: 8px; }”.', TEXT_DOMAIN )

	if ( invalid ) {
		help = (
			<span className="ap-effects-custom-css__error">
				<strong>{ __( 'Error:', TEXT_DOMAIN ) }</strong>
				{ ' ' }
				{ urlsBlocked
					? __( 'This site doesn’t allow Custom CSS to load URLs (url(), image-set(), image() or src()). It won’t be printed.', TEXT_DOMAIN )
					: __( 'This CSS can’t be used: check for unclosed or mismatched braces, parentheses, brackets, strings or comments, unquoted url() values, “<”, backslashes, or at-rules other than @media, @supports and @container. It won’t be printed.', TEXT_DOMAIN ) }
			</span>
		)
	}

	return (
		<TextareaControl
			__nextHasNoMarginBottom
			className="ap-effects-custom-css"
			label={ __( 'Custom CSS', TEXT_DOMAIN ) }
			help={ help }
			value={ value }
			rows={ 6 }
			dir="ltr"
			spellCheck={ false }
			aria-invalid={ invalid ? true : undefined }
			onChange={ onChange }
		/>
	)
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

			const customCss    = 'string' === typeof effects.customCss ? effects.customCss : ''
			const shapeLabels  = clipShapeLabels()
			const clipsNothing = true === effects.textClip && ! hasBackgroundFill( attributes )

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls group="styles">
						<ToolsPanel
							label={ __( 'Effects', TEXT_DOMAIN ) }
							resetAll={ resetAll }
							panelId={ clientId }
						>
							{ item( 'opacity', __( 'Opacity', TEXT_DOMAIN ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Opacity (%)', TEXT_DOMAIN ) }
									min={ 0 }
									max={ 100 }
									value={ numberOrUndefined( effects.opacity ) ?? 100 }
									onChange={ ( value ) => update( 'opacity', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'overflowX', __( 'Overflow (horizontal)', TEXT_DOMAIN ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Overflow (horizontal)', TEXT_DOMAIN ) }
									value={ 'string' === typeof effects.overflowX ? effects.overflowX : '' }
									options={ keywordOptions( OVERFLOW_VALUES, overflowLabels() ) }
									onChange={ ( value ) => update( 'overflowX', value ) }
								/>
							) ) }
							{ item( 'overflowY', __( 'Overflow (vertical)', TEXT_DOMAIN ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Overflow (vertical)', TEXT_DOMAIN ) }
									value={ 'string' === typeof effects.overflowY ? effects.overflowY : '' }
									options={ keywordOptions( OVERFLOW_VALUES, overflowLabels() ) }
									onChange={ ( value ) => update( 'overflowY', value ) }
								/>
							) ) }
							{ item( 'backdropBlur', __( 'Backdrop blur', TEXT_DOMAIN ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Backdrop blur (px)', TEXT_DOMAIN ) }
									help={ __( 'Blurs whatever shows through a translucent background, for frosted glass.', TEXT_DOMAIN ) }
									min={ 0 }
									max={ 100 }
									value={ numberOrUndefined( effects.backdropBlur ) ?? 0 }
									onChange={ ( value ) => update( 'backdropBlur', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'backdropSaturate', __( 'Backdrop saturation', TEXT_DOMAIN ), (
								<RangeControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Backdrop saturation (%)', TEXT_DOMAIN ) }
									min={ 0 }
									max={ 300 }
									value={ numberOrUndefined( effects.backdropSaturate ) ?? 100 }
									onChange={ ( value ) => update( 'backdropSaturate', numberOrUndefined( value ) ) }
									allowReset
									resetFallbackValue={ undefined }
								/>
							) ) }
							{ item( 'mixBlendMode', __( 'Blend mode', TEXT_DOMAIN ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Blend mode', TEXT_DOMAIN ) }
									value={ 'string' === typeof effects.mixBlendMode ? effects.mixBlendMode : '' }
									options={ keywordOptions( BLEND_MODES, blendModeLabels() ) }
									onChange={ ( value ) => update( 'mixBlendMode', value ) }
								/>
							) ) }
							{ item( 'zIndex', __( 'Z-index', TEXT_DOMAIN ), (
								<NumberControl
									__next40pxDefaultSize
									label={ __( 'Z-index', TEXT_DOMAIN ) }
									help={ __( 'Stacks the block above or below its neighbors. A block that isn’t positioned becomes position: relative.', TEXT_DOMAIN ) }
									value={ numberOrUndefined( effects.zIndex ) ?? '' }
									onChange={ ( value: string | undefined ) => update( 'zIndex', numberOrUndefined( value ) ) }
								/>
							) ) }
							{ item( 'isolation', __( 'Isolate', TEXT_DOMAIN ), (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __( 'Isolate', TEXT_DOMAIN ) }
									help={ __( 'Starts a new stacking context, so blend modes inside the block don’t mix with what’s behind it.', TEXT_DOMAIN ) }
									checked={ true === effects.isolation }
									onChange={ ( value ) => update( 'isolation', value ) }
								/>
							) ) }
							{ item( 'clipShape', __( 'Clip shape', TEXT_DOMAIN ), (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Clip shape', TEXT_DOMAIN ) }
									value={ 'string' === typeof effects.clipShape ? effects.clipShape : '' }
									options={ [
										{ label: __( 'None', TEXT_DOMAIN ), value: '' },
										...Object.keys( CLIP_SHAPES ).map( ( slug ) => ( { label: shapeLabels[ slug ] ?? slug, value: slug } ) ),
									] }
									onChange={ ( value ) => update( 'clipShape', value ) }
								/>
							) ) }
							{ item( 'textClip', __( 'Clip background to text', TEXT_DOMAIN ), (
								<>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __( 'Clip background to text', TEXT_DOMAIN ) }
										help={ __( 'Paints the background only inside the letters. Pair it with a gradient background for gradient text.', TEXT_DOMAIN ) }
										checked={ true === effects.textClip }
										onChange={ ( value ) => update( 'textClip', value ) }
									/>
									{ clipsNothing && (
										<Notice status="warning" isDismissible={ false } className="ap-effects-text-clip-notice">
											{ __( 'This block has no gradient or background image, so text clip is left off: the text would be invisible. Add a gradient or a background image to use it.', TEXT_DOMAIN ) }
										</Notice>
									) }
								</>
							) ) }
							{ canEditBlockCss() && item( 'customCss', __( 'Custom CSS', TEXT_DOMAIN ), (
								<CustomCssField
									value={ customCss }
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

			const allowUrls = customCssAllowsUrls()
			const compiled  = useMemo(
				() => compileEffects( attributes, { allowCustomCssUrls: allowUrls } ),
				[ attributes, allowUrls ],
			)

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
