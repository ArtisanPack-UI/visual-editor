/**
 * `editor.BlockEdit` HOC — inject a unified Border color + gradient
 * picker into the inspector (#490).
 *
 * Uses Gutenberg's `__experimentalColorGradientControl` rendered inline
 * inside a `ToolsPanelItem` so the user sees the same tabbed Color |
 * Gradient UX they get for background and text colors — the tabs are
 * always visible (no popover indirection). Picking a solid color writes
 * to `attributes.style.border.color`; picking a gradient writes to
 * `attributes.style.border.gradient`. Choosing a gradient clears the
 * solid color (and vice versa) — only one value is "active" at a time
 * since CSS itself can't render both as a border simultaneously.
 *
 * Placement: `InspectorControls group="border"` so the dropdown lands
 * inside the native Border tools panel next to the radius / width
 * controls.
 *
 * State + breakpoint composition is handled by the standard
 * `withStateAttributes` / `withResponsiveAttributes` HOCs: when the
 * inspector chip is on `hover` (or `md`, etc.), our write is routed
 * automatically into `attributes.states['style.border.gradient']` /
 * `attributes.responsive['style.border.gradient']`.
 *
 * A token-missing warning surfaces above the picker when a referenced
 * gradient slug isn't present in the resolved settings.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import {
	BaseControl,
	Button,
	ColorIndicator,
	Dropdown,
	Notice,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanelItem as ToolsPanelItem,
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
import type { GradientBorderAttributes } from './types'

const FILTER_HOOK      = 'editor.BlockEdit'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/gradient-border-control'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.gradient-border-control.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockEditProps {
	name: string
	clientId?: string
	attributes: GradientBorderAttributes & Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
	[key: string]: unknown
}

interface ThemeGradientEntry {
	slug?: string
	name?: string
	gradient?: string
}

interface BlockEditorSettings {
	gradients?: ThemeGradientEntry[]
	[key: string]: unknown
}

function blockSupportsGradientBorder( name: string ): boolean {
	const blockType = getBlockType( name )

	if ( ! blockType ) {
		return false
	}

	const support = ( blockType.supports as Record<string, unknown> | undefined )?.[ '__experimentalBorder' ]
		?? ( blockType.supports as Record<string, unknown> | undefined )?.[ 'border' ]

	if ( ! support || 'object' !== typeof support ) {
		return false
	}

	return true === ( support as Record<string, unknown> ).gradient
}

function readBorder(
	attributes: GradientBorderAttributes,
): { color: string | null; gradient: string | null; style: string | null } {
	const border = attributes.style?.border ?? null
	const borderStyle = border && 'string' === typeof ( border as Record<string, unknown> ).style
		? ( ( border as Record<string, unknown> ).style as string )
		: null

	return {
		color:    border && 'string' === typeof border.color ? border.color as string : null,
		gradient: border && 'string' === typeof border.gradient ? border.gradient : null,
		style:    borderStyle,
	}
}

interface BorderPatch {
	color?: string | null
	gradient?: string | null
	style?: string | null
}

function applyPatchToBorder(
	border: Record<string, unknown>,
	patch: BorderPatch,
): Record<string, unknown> {
	const next: Record<string, unknown> = { ...border }

	for ( const key of [ 'color', 'gradient', 'style' ] as const ) {
		if ( ! ( key in patch ) ) {
			continue
		}

		const value = patch[ key ]

		if ( null === value || '' === value ) {
			delete next[ key ]
		} else {
			next[ key ] = value
		}
	}

	return next
}

/**
 * Build a stable border-writer that survives the back-to-back
 * `onColorChange` + `onGradientChange` calls Gutenberg's
 * `ColorGradientControl` fires when the user picks a value. Both calls
 * happen synchronously in the same event handler; using
 * `attributes.style.border` from the React closure leaves the second
 * call reading STALE state and overwriting the first call's write
 * (gradient set, then immediately cleared — the symptom is the
 * Gradient tab snapping back to Color after a swatch click).
 *
 * The fix: track the latest-written border in a ref that updates
 * synchronously inside `write`. Subsequent calls within the same tick
 * see the just-written state.
 */
function useBorderWriter(
	attributes: GradientBorderAttributes,
	setAttributes: ( updates: Record<string, unknown> ) => void,
): ( patch: BorderPatch ) => void {
	const borderRef = useRef< Record<string, unknown> >(
		( attributes.style?.border as Record<string, unknown> | undefined ) ?? {},
	)
	const styleRef = useRef< Record<string, unknown> >(
		( attributes.style as Record<string, unknown> | undefined ) ?? {},
	)

	// Keep refs in sync with the canonical attributes after each
	// render so picks on stale data don't overwrite changes made by
	// other inspectors in the meantime.
	useEffect( () => {
		borderRef.current = ( attributes.style?.border as Record<string, unknown> | undefined ) ?? {}
		styleRef.current  = ( attributes.style as Record<string, unknown> | undefined ) ?? {}
	}, [ attributes.style, attributes.style?.border ] )

	return useCallback(
		( patch ) => {
			const nextBorder = applyPatchToBorder( borderRef.current, patch )
			// Update the ref BEFORE setAttributes so a follow-up call
			// inside the same event handler reads the patched state.
			borderRef.current = nextBorder
			const nextStyle = { ...styleRef.current, border: nextBorder }
			styleRef.current = nextStyle

			setAttributes( { style: nextStyle } )
		},
		[ setAttributes ],
	)
}

/**
 * Render-inline token-missing warning. Sits above the dropdown's tabs
 * when one or more referenced slugs no longer resolves to a theme
 * entry.
 */
function MissingTokenWarning(
	{ missing }: { missing: string[] },
): JSX.Element | null {
	if ( 0 === missing.length ) {
		return null
	}

	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="ap-gradient-border__missing-token-notice"
		>
			{ sprintf(
				/* translators: %s: comma-separated list of slugs. */
				__( 'Gradient token(s) no longer available: %s. The affected breakpoint or state will render as transparent until restored.', 'artisanpack-visual-editor' ),
				missing.join( ', ' ),
			) }
		</Notice>
	)
}

export const withGradientBorderControl = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function GradientBorderBlockEdit( props: BlockEditProps ): JSX.Element {
			const { name, attributes, setAttributes, clientId } = props

			const enabled = useMemo(
				() => blockSupportsGradientBorder( name ),
				[ name ],
			)

			const { themeGradients, themeColors } = useSelect(
				( select ) => {
					const settings = (
						select( 'core/block-editor' ) as {
							getSettings: () => BlockEditorSettings & { colors?: unknown[] }
						}
					 ).getSettings()

					return {
						themeGradients: Array.isArray( settings?.gradients ) ? settings.gradients : [],
						themeColors:    Array.isArray( settings?.colors ) ? settings.colors : [],
					}
				},
				[],
			)

			// Tab visibility in `ColorGradientControl` requires BOTH:
			// (a) both onColorChange and onGradientChange, and (b) a
			// non-empty `gradients` palette. The
			// `useMultipleOriginColorsAndGradients` hook can return
			// empty arrays in a dev theme that doesn't ship multi-
			// origin palettes — so we pass `colors` and `gradients`
			// explicitly from `getSettings()`, which mirrors what the
			// editor's own background picker reads.

			const missing = useMemo( () => {
				if ( ! enabled ) {
					return []
				}

				const referenced = referencedSlugs( attributes )
				const knownSlugs = new Set(
					themeGradients
						.map( ( entry ) => ( 'string' === typeof entry?.slug ? entry.slug : null ) )
						.filter( ( slug ): slug is string => null !== slug && '' !== slug ),
				)

				return referenced.filter( ( slug ) => ! knownSlugs.has( slug ) )
			}, [ enabled, attributes, themeGradients ] )

			// Must be declared unconditionally for Rules of Hooks; the
			// writer only fires when the picker is interacted with, so
			// the cost on blocks without gradient support is one extra
			// ref pair per BlockEdit instance.
			const writeBorder = useBorderWriter( attributes, setAttributes )

			if ( ! enabled ) {
				return <BlockEdit { ...props } />
			}

			const { color, gradient, style: borderStyle } = readBorder( attributes )

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls group="border">
						{ /* Replicate the native BorderControlDropdown UX:
						     - hide the bundled native swatch+popover trigger
						     - render OUR own swatch+popover with Color | Gradient
						       tabs inside (matching the background picker's UX)
						     - reorder via CSS so our swatch lands at the top of
						       the Border panel where the native one used to sit
						     The `<style>` MUST live inside `<InspectorControls>`
						     so it portals into the inspector sidebar's document. */ }
						<style>{ `
							button[aria-label*="Border color and style picker"],
							button[aria-label*="Border color picker"],
							button[aria-label*="Border style picker"] {
								display: none !important;
							}
							/* Render our picker as the first item in the
							   Border tools panel so it visually replaces
							   the native swatch + style trigger. The panel
							   lays out children in source order; CSS order
							   shifts ours to the front without touching the
							   width / radius native items. */
							.ap-border-picker-item {
								order: -1;
							}
						` }</style>
						<ToolsPanelItem
							panelId={ clientId }
							className="ap-border-picker-item"
							hasValue={ () =>
								null !== color || null !== gradient || null !== borderStyle
							}
							label={ __( 'Border', 'artisanpack-visual-editor' ) }
							onDeselect={ () =>
								writeBorder( { color: null, gradient: null, style: null } )
							}
							isShownByDefault
						>
							<BaseControl __nextHasNoMarginBottom>
								<MissingTokenWarning missing={ missing } />
								<Dropdown
									contentClassName="ap-border-picker__content"
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
												'Border color, gradient, and style picker',
												'artisanpack-visual-editor',
											) }
											className="ap-border-picker__toggle"
										>
											{ /* Mirror the native swatch shape: a
											     small ColorIndicator + label row.
											     Showing the gradient as the swatch
											     fill when one is selected keeps the
											     UI honest about what's actually
											     painting. */ }
											<ColorIndicator
												colorValue={ gradient ?? color ?? undefined }
											/>
											<span style={ { marginLeft: 8 } }>
												{ __( 'Border', 'artisanpack-visual-editor' ) }
											</span>
										</Button>
									) }
									renderContent={ () => (
										<div className="ap-border-picker__popover">
											<ColorGradientControl
												label={ __(
													'Border',
													'artisanpack-visual-editor',
												) }
												showTitle={ false }
												colorValue={ color ?? undefined }
												gradientValue={ gradient ?? undefined }
												onColorChange={ (
													next: string | undefined,
												) =>
													writeBorder( {
														color: next ?? null,
													} )
												}
												onGradientChange={ (
													next: string | undefined,
												) =>
													writeBorder( {
														gradient: next ?? null,
													} )
												}
												// ALL FOUR keys must be present or
												// the control falls back to
												// `ColorGradientControlSelect`,
												// which reads from
												// `useSettings('color.gradients')`
												// — returning empty in a theme
												// without origin-grouped gradients
												// and killing the Gradient tab.
												colors={ themeColors as never }
												gradients={ themeGradients as never }
												disableCustomColors={ false }
												disableCustomGradients={ false }
												enableAlpha
												__experimentalIsRenderedInSidebar
											/>
											<div style={ { marginTop: 16 } }>
												<ToggleGroupControl
													label={ __(
														'Style',
														'artisanpack-visual-editor',
													) }
													value={ borderStyle ?? '' }
													onChange={ ( next ) =>
														writeBorder( {
															style:
																'string' === typeof next
																	? next
																	: null,
														} )
													}
													isBlock
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												>
													<ToggleGroupControlOption
														label={ __(
															'Solid',
															'artisanpack-visual-editor',
														) }
														value="solid"
													/>
													<ToggleGroupControlOption
														label={ __(
															'Dashed',
															'artisanpack-visual-editor',
														) }
														value="dashed"
													/>
													<ToggleGroupControlOption
														label={ __(
															'Dotted',
															'artisanpack-visual-editor',
														) }
														value="dotted"
													/>
												</ToggleGroupControl>
											</div>
										</div>
									) }
								/>
							</BaseControl>
						</ToolsPanelItem>
					</InspectorControls>
				</>
			)
		}

		GradientBorderBlockEdit.displayName = 'GradientBorderBlockEdit'

		return GradientBorderBlockEdit
	},
	'withGradientBorderControl',
)

/**
 * Register the BlockEdit filter at most once per page. Idempotent —
 * safe to call from both the post-editor and site-editor bootstrap
 * paths.
 */
export function registerGradientBorderControl(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withGradientBorderControl )
	host[ REGISTERED_KEY ] = true
}
