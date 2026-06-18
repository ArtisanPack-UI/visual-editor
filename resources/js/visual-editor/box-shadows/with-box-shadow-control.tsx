/**
 * `editor.BlockEdit` HOC — inject the Shadow tools panel into the
 * inspector for every block with border support (#607).
 *
 * Per #607, the panel lives in its own `InspectorControls` group
 * ("styles" — alongside the native color/typography panels) rather
 * than piggybacking on the Border tools panel. This leaves room for
 * a future Effects suite (filters, backdrop-filter) under the same
 * roof.
 *
 * Writes go to `attributes.style.shadow.{offsetX,offsetY,blur,spread,
 * color,gradient,inset,preset}`. State + breakpoint composition is
 * handled by the standard routing HOCs once `style.shadow` is in
 * `artisanpackStates.attributes` / `artisanpackResponsive.attributes`
 * (see `extend-supports.ts`).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import {
	BaseControl,
	Button,
	ColorIndicator,
	Dropdown,
	ToggleControl,
	__experimentalUnitControl as UnitControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	Notice,
} from '@wordpress/components'
import { createHigherOrderComponent } from '@wordpress/compose'
import { getBlockType } from '@wordpress/blocks'
import {
	InspectorControls,
	// eslint-disable-next-line camelcase
	__experimentalColorGradientControl as ColorGradientControl,
} from '@wordpress/block-editor'
import { useSelect } from '@wordpress/data'
import { addFilter } from '@wordpress/hooks'
import { __, sprintf } from '@wordpress/i18n'
import { useCallback, useEffect, useMemo, useRef } from 'react'
import type { ComponentType } from 'react'

import { referencedSlugs } from './resolver'
import type { BoxShadowAttributes, ShadowSubtree } from './types'

const FILTER_HOOK      = 'editor.BlockEdit'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/box-shadow-control'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.box-shadow-control.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockEditProps {
	name: string
	clientId?: string
	attributes: BoxShadowAttributes & Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
	[key: string]: unknown
}

interface ThemeShadowEntry {
	slug?: string
	name?: string
	shadow?: string
}

interface ThemeGradientEntry {
	slug?: string
	name?: string
	gradient?: string
}

interface BlockEditorSettings {
	gradients?: ThemeGradientEntry[]
	colors?: unknown[]
	[key: string]: unknown
}

function blockSupportsBoxShadow( name: string ): boolean {
	const blockType = getBlockType( name )

	if ( ! blockType ) {
		return false
	}

	const supports = blockType.supports as Record<string, unknown> | undefined
	const support  = supports?.[ '__experimentalBorder' ] ?? supports?.[ 'border' ]

	return Boolean( support && 'object' === typeof support )
}

function readShadow( attributes: BoxShadowAttributes ): ShadowSubtree {
	const shadow = attributes.style?.shadow ?? null
	return shadow && 'object' === typeof shadow ? shadow : {}
}

interface ShadowPatch {
	offsetX?: string | null
	offsetY?: string | null
	blur?: string | null
	spread?: string | null
	color?: string | null
	gradient?: string | null
	inset?: boolean
	preset?: string | null
}

function applyPatchToShadow(
	shadow: Record<string, unknown>,
	patch: ShadowPatch,
): Record<string, unknown> {
	const next: Record<string, unknown> = { ...shadow }

	for ( const key of Object.keys( patch ) as Array<keyof ShadowPatch> ) {
		const value = patch[ key ]

		if ( null === value || '' === value || undefined === value ) {
			delete next[ key ]
			continue
		}

		next[ key ] = value
	}

	return next
}

function MissingTokenWarning(
	{ missing, kind }: { missing: string[]; kind: 'shadow' | 'gradient' },
): JSX.Element | null {
	if ( 0 === missing.length ) {
		return null
	}

	const message = 'shadow' === kind
		? sprintf(
			/* translators: %s: comma-separated list of slugs. */
			__( 'Shadow preset(s) no longer available: %s. The affected breakpoint or state will fall back to the idle layer until restored.', 'artisanpack-visual-editor' ),
			missing.join( ', ' ),
		)
		: sprintf(
			/* translators: %s: comma-separated list of slugs. */
			__( 'Gradient token(s) no longer available: %s. The affected shadow fill will render as transparent until restored.', 'artisanpack-visual-editor' ),
			missing.join( ', ' ),
		)

	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="ap-box-shadow__missing-token-notice"
		>
			{ message }
		</Notice>
	)
}

export const withBoxShadowControl = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function BoxShadowBlockEdit( props: BlockEditProps ): JSX.Element {
			const { name, attributes, setAttributes, clientId } = props

			const enabled = useMemo(
				() => blockSupportsBoxShadow( name ),
				[ name ],
			)

			const { themeShadows, themeGradients, themeColors } = useSelect(
				( select ) => {
					const settings = (
						select( 'core/block-editor' ) as {
							getSettings: () => BlockEditorSettings & {
								shadow?: { presets?: ThemeShadowEntry[] }
							}
						}
					 ).getSettings()

					const shadowPresets = Array.isArray( settings?.shadow?.presets )
						? settings.shadow!.presets!
						: []

					return {
						themeShadows:   shadowPresets,
						themeGradients: Array.isArray( settings?.gradients ) ? settings.gradients : [],
						themeColors:    Array.isArray( settings?.colors ) ? settings.colors : [],
					}
				},
				[],
			)

			const { missingShadows, missingGradients } = useMemo( () => {
				if ( ! enabled ) {
					return { missingShadows: [] as string[], missingGradients: [] as string[] }
				}

				const referenced = referencedSlugs( attributes )

				const knownShadows = new Set(
					themeShadows
						.map( ( entry ) => ( 'string' === typeof entry?.slug ? entry.slug : null ) )
						.filter( ( slug ): slug is string => null !== slug && '' !== slug ),
				)

				const knownGradients = new Set(
					themeGradients
						.map( ( entry ) => ( 'string' === typeof entry?.slug ? entry.slug : null ) )
						.filter( ( slug ): slug is string => null !== slug && '' !== slug ),
				)

				return {
					missingShadows:   referenced.shadows.filter( ( slug ) => ! knownShadows.has( slug ) ),
					missingGradients: referenced.gradients.filter( ( slug ) => ! knownGradients.has( slug ) ),
				}
			}, [ enabled, attributes, themeShadows, themeGradients ] )

			// Ref-backed writer — survives the back-to-back
			// `onColorChange` + `onGradientChange` calls that
			// `__experimentalColorGradientControl` fires synchronously
			// when the user picks a value. Without the refs both calls
			// read `attributes.style` from the React closure (same stale
			// snapshot) and the second `setAttributes` overwrites the
			// first. Mirrors `useBorderWriter` in gradient-borders/
			// with-gradient-border-control.tsx.
			const styleRef  = useRef< Record<string, unknown> >(
				( attributes.style as Record<string, unknown> | undefined ) ?? {},
			)
			const shadowRef = useRef< Record<string, unknown> >(
				( attributes.style?.shadow as Record<string, unknown> | undefined ) ?? {},
			)

			useEffect( () => {
				styleRef.current  = ( attributes.style as Record<string, unknown> | undefined ) ?? {}
				shadowRef.current = ( attributes.style?.shadow as Record<string, unknown> | undefined ) ?? {}
			}, [ attributes.style, attributes.style?.shadow ] )

			const writeShadow = useCallback(
				( patch: ShadowPatch ) => {
					const nextShadow = applyPatchToShadow( shadowRef.current, patch )
					shadowRef.current = nextShadow

					const nextStyle: Record<string, unknown> = Object.keys( nextShadow ).length > 0
						? { ...styleRef.current, shadow: nextShadow }
						: ( () => {
							const { shadow: _drop, ...rest } = styleRef.current
							return rest
						} )()

					styleRef.current = nextStyle

					setAttributes( { style: nextStyle } )
				},
				[ setAttributes ],
			)

			if ( ! enabled ) {
				return <BlockEdit { ...props } />
			}

			const shadow = readShadow( attributes )

			const offsetX = 'string' === typeof shadow.offsetX ? shadow.offsetX : ''
			const offsetY = 'string' === typeof shadow.offsetY ? shadow.offsetY : ''
			const blur    = 'string' === typeof shadow.blur ? shadow.blur : ''
			const spread  = 'string' === typeof shadow.spread ? shadow.spread : ''
			const color   = 'string' === typeof shadow.color ? shadow.color : undefined
			const grad    = 'string' === typeof shadow.gradient ? shadow.gradient : undefined
			const inset   = true === shadow.inset
			const preset  = 'string' === typeof shadow.preset ? shadow.preset : null

			const hasAnyValue =
				'' !== offsetX
				|| '' !== offsetY
				|| '' !== blur
				|| '' !== spread
				|| undefined !== color
				|| undefined !== grad
				|| inset
				|| null !== preset

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls group="styles">
						<ToolsPanel
							label={ __( 'Shadow', 'artisanpack-visual-editor' ) }
							resetAll={ () => writeShadow( {
								offsetX:  null,
								offsetY:  null,
								blur:     null,
								spread:   null,
								color:    null,
								gradient: null,
								inset:    false,
								preset:   null,
							} ) }
							panelId={ clientId }
						>
							<ToolsPanelItem
								panelId={ clientId }
								hasValue={ () => hasAnyValue }
								label={ __( 'Shadow', 'artisanpack-visual-editor' ) }
								onDeselect={ () => writeShadow( {
									offsetX:  null,
									offsetY:  null,
									blur:     null,
									spread:   null,
									color:    null,
									gradient: null,
									inset:    false,
									preset:   null,
								} ) }
								isShownByDefault
							>
								<BaseControl __nextHasNoMarginBottom>
									<MissingTokenWarning missing={ missingShadows } kind="shadow" />
									<MissingTokenWarning missing={ missingGradients } kind="gradient" />

									{ themeShadows.length > 0 && (
										<div className="ap-box-shadow__preset-row" style={ { marginBottom: 12 } }>
											<BaseControl.VisualLabel>
												{ __( 'Presets', 'artisanpack-visual-editor' ) }
											</BaseControl.VisualLabel>
											<div style={ { display: 'flex', gap: 4, flexWrap: 'wrap' } }>
												{ themeShadows.map( ( entry ) => {
													const slug = 'string' === typeof entry?.slug ? entry.slug : ''
													if ( '' === slug ) {
														return null
													}
													const isActive = slug === preset
													return (
														<button
															key={ slug }
															type="button"
															aria-pressed={ isActive }
															className="ap-box-shadow__preset-chip"
															onClick={ () =>
																writeShadow( { preset: isActive ? null : slug } )
															}
															style={ {
																padding: '4px 8px',
																fontSize: 12,
																border: isActive ? '2px solid var(--wp-admin-theme-color, #007cba)' : '1px solid #ddd',
																borderRadius: 4,
																background: '#fff',
																cursor: 'pointer',
															} }
														>
															{ entry?.name ?? slug }
														</button>
													)
												} ) }
											</div>
										</div>
									) }

									<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 } }>
										<UnitControl
											label={ __( 'X offset', 'artisanpack-visual-editor' ) }
											value={ offsetX }
											onChange={ ( next: string | undefined ) =>
												writeShadow( { offsetX: next ?? null } )
											}
											__next40pxDefaultSize
										/>
										<UnitControl
											label={ __( 'Y offset', 'artisanpack-visual-editor' ) }
											value={ offsetY }
											onChange={ ( next: string | undefined ) =>
												writeShadow( { offsetY: next ?? null } )
											}
											__next40pxDefaultSize
										/>
										<UnitControl
											label={ __( 'Blur', 'artisanpack-visual-editor' ) }
											value={ blur }
											onChange={ ( next: string | undefined ) =>
												writeShadow( { blur: next ?? null } )
											}
											__next40pxDefaultSize
										/>
										<UnitControl
											label={ __( 'Spread', 'artisanpack-visual-editor' ) }
											value={ spread }
											onChange={ ( next: string | undefined ) =>
												writeShadow( { spread: next ?? null } )
											}
											__next40pxDefaultSize
										/>
									</div>

									<div style={ { marginTop: 12 } }>
										<BaseControl.VisualLabel>
											{ __( 'Color', 'artisanpack-visual-editor' ) }
										</BaseControl.VisualLabel>
										<Dropdown
											contentClassName="ap-box-shadow__color-popover"
											popoverProps={ { placement: 'left-start', offset: 36 } }
											renderToggle={ ( { onToggle, isOpen }: {
												onToggle: () => void
												isOpen:   boolean
											} ) => (
												<Button
													onClick={ onToggle }
													aria-expanded={ isOpen }
													aria-haspopup="dialog"
													aria-label={ __(
														'Shadow color and gradient picker',
														'artisanpack-visual-editor',
													) }
													className="ap-box-shadow__color-toggle"
													style={ { display: 'flex', alignItems: 'center', gap: 8 } }
												>
													<ColorIndicator colorValue={ grad ?? color ?? undefined } />
													<span>
														{ grad
															? __( 'Gradient', 'artisanpack-visual-editor' )
															: ( color
																? __( 'Color', 'artisanpack-visual-editor' )
																: __( 'Select color', 'artisanpack-visual-editor' ) ) }
													</span>
												</Button>
											) }
											renderContent={ () => (
												<div className="ap-box-shadow__color-popover-content" style={ { minWidth: 260 } }>
													<ColorGradientControl
														label={ __( 'Shadow', 'artisanpack-visual-editor' ) }
														showTitle={ false }
														colorValue={ color }
														gradientValue={ grad }
														// IMPORTANT: only the field the picker
														// reports a change on is written. We do NOT
														// proactively clear the other field here —
														// `__experimentalColorGradientControl` fires
														// BOTH callbacks synchronously on every pick
														// (one with the value, one with undefined),
														// so any "clear the sibling" logic in either
														// handler races and ends up nuking the value
														// we just wrote. The ref-backed writer above
														// keeps them in sync; the mutex below clears
														// the OTHER field only when this one is set
														// to a non-empty value, never when it's being
														// cleared.
														onColorChange={ ( next: string | undefined ) => {
															if ( undefined !== next && '' !== next ) {
																writeShadow( { color: next, gradient: null } )
															} else {
																writeShadow( { color: null } )
															}
														} }
														onGradientChange={ ( next: string | undefined ) => {
															if ( undefined !== next && '' !== next ) {
																writeShadow( { gradient: next, color: null } )
															} else {
																writeShadow( { gradient: null } )
															}
														} }
														colors={ themeColors as never }
														gradients={ themeGradients as never }
														disableCustomColors={ false }
														disableCustomGradients={ false }
														enableAlpha
														__experimentalIsRenderedInSidebar
													/>
												</div>
											) }
										/>
									</div>

									<div style={ { marginTop: 12 } }>
										<ToggleControl
											label={ __( 'Inset', 'artisanpack-visual-editor' ) }
											checked={ inset }
											onChange={ ( next: boolean ) => writeShadow( { inset: next } ) }
											__nextHasNoMarginBottom
										/>
									</div>
								</BaseControl>
							</ToolsPanelItem>
						</ToolsPanel>
					</InspectorControls>
				</>
			)
		}

		BoxShadowBlockEdit.displayName = 'BoxShadowBlockEdit'

		return BoxShadowBlockEdit
	},
	'withBoxShadowControl',
)

/**
 * Register the BlockEdit filter at most once per page. Idempotent —
 * safe to call from both the post-editor and site-editor bootstrap
 * paths.
 */
export function registerBoxShadowControl(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withBoxShadowControl )
	host[ REGISTERED_KEY ] = true
}
