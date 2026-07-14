/**
 * `editor.BlockEdit` HOC — Position inspector panel (#642, #644).
 *
 * Renders a Position panel in the "styles" InspectorControls group for
 * every block whose `supports.position` is truthy. The panel houses:
 *
 *  - A position dropdown (`static / relative / absolute / fixed / sticky`).
 *  - When non-`static`: number+unit UnitControls for top/right/bottom/
 *    left and an integer input for z-index.
 *  - A breakpoint switcher — clicking a breakpoint tab flips the
 *    editor's active-breakpoint store so ALL responsive controls
 *    (spacing, typography, etc.) follow along, mirroring the pattern
 *    used elsewhere in the package.
 *  - An "Inherited from smaller breakpoint" affordance when the
 *    currently-viewed breakpoint doesn't override the field.
 *  - #644 warning notice when the effective position at the active
 *    breakpoint is `absolute` and no ancestor block sets a non-static
 *    position.
 *
 * Writes go to `attributes.style.position` (base) or
 * `attributes.responsive['style.position'][<key>]` (per-breakpoint).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import {
	BaseControl,
	Notice,
	SelectControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components'
import { createHigherOrderComponent } from '@wordpress/compose'
import { getBlockType } from '@wordpress/blocks'
import { InspectorControls } from '@wordpress/block-editor'
import { useSelect } from '@wordpress/data'
import { addFilter } from '@wordpress/hooks'
import { __ } from '@wordpress/i18n'
import { useCallback, useMemo, useSyncExternalStore } from 'react'
import type { ComponentType } from 'react'

import {
	getActiveBreakpoint,
	subscribeActiveBreakpoint,
} from '../responsive/active-breakpoint'
import { BreakpointRegistry, TAILWIND_V4_DEFAULTS } from '../responsive/registry'
import { BASE_KEY } from '../responsive/types'
import { positionEnabled } from './extend-supports'
import {
	coerceSubtree,
	rawOffsetAt,
	rawValueAt,
	rawZIndexAt,
	resolveAtBreakpoint,
} from './resolver'
import {
	OFFSET_SIDES,
	OFFSET_UNITS,
	POSITION_ATTRIBUTE_PATH,
	POSITION_VALUES,
	type OffsetSide,
	type OffsetValue,
	type PositionAttributes,
	type PositionSubtree,
	type PositionValue,
} from './types'

const FILTER_HOOK      = 'editor.BlockEdit'
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/position-control'

const REGISTERED_KEY = Symbol.for(
	'artisanpack-ui.visual-editor.position-control.registered',
)

interface GlobalSentinelHost {
	[REGISTERED_KEY]?: boolean
}

interface BlockEditProps {
	name: string
	clientId?: string
	attributes: PositionAttributes & Record<string, unknown>
	setAttributes: ( updates: Record<string, unknown> ) => void
	[key: string]: unknown
}

const breakpoints = new BreakpointRegistry( TAILWIND_V4_DEFAULTS )

function blockSupportsPosition( name: string ): boolean {
	const blockType = getBlockType( name )
	if ( ! blockType ) {
		return false
	}
	return positionEnabled( blockType.supports as Record<string, unknown> | undefined )
}

function readBaseSubtree( attributes: PositionAttributes ): PositionSubtree {
	const raw = coerceSubtree( attributes.style?.position )
	return raw ?? {}
}

function readBreakpointSubtree(
	attributes: PositionAttributes,
	key: string,
): PositionSubtree {
	if ( BASE_KEY === key ) {
		return readBaseSubtree( attributes )
	}

	const raw = coerceSubtree( attributes.responsive?.[ POSITION_ATTRIBUTE_PATH ]?.[ key ] )
	return raw ?? {}
}

/**
 * Compose a `setAttributes` payload that writes a patched subtree to
 * the base slot (`style.position`) or the responsive slot (
 * `responsive['style.position'][key]`) depending on the active
 * breakpoint. Empty subtrees are collapsed to `null` (base) or the key
 * is removed (responsive) so downstream consumers don't have to
 * distinguish "empty object" from "no data".
 */
function writeSubtree(
	attributes: PositionAttributes,
	setAttributes: ( updates: Record<string, unknown> ) => void,
	breakpointKey: string,
	next: PositionSubtree,
): void {
	const cleaned = collapseSubtree( next )

	if ( BASE_KEY === breakpointKey ) {
		const style = ( attributes.style && 'object' === typeof attributes.style )
			? ( attributes.style as Record<string, unknown> )
			: {}

		if ( null === cleaned ) {
			const { position: _drop, ...rest } = style
			setAttributes( { style: rest } )
			return
		}

		// Preserve any private scope-id already on the current subtree
		// (see `with-position-styles.tsx` for `_positionScopeId`).
		const existing = style.position && 'object' === typeof style.position
			? ( style.position as Record<string, unknown> )
			: {}

		const preserved: Record<string, unknown> = {}
		if ( 'string' === typeof existing._positionScopeId ) {
			preserved._positionScopeId = existing._positionScopeId
		}

		setAttributes( {
			style: {
				...style,
				position: { ...preserved, ...cleaned },
			},
		} )
		return
	}

	const responsive = ( attributes.responsive && 'object' === typeof attributes.responsive )
		? ( attributes.responsive as Record<string, Record<string, unknown>> )
		: {}

	const bag = ( responsive[ POSITION_ATTRIBUTE_PATH ] && 'object' === typeof responsive[ POSITION_ATTRIBUTE_PATH ] )
		? { ...responsive[ POSITION_ATTRIBUTE_PATH ] }
		: {}

	if ( null === cleaned ) {
		delete bag[ breakpointKey ]
	} else {
		bag[ breakpointKey ] = cleaned
	}

	const nextResponsive: Record<string, unknown> = { ...responsive }

	if ( 0 === Object.keys( bag ).length ) {
		delete nextResponsive[ POSITION_ATTRIBUTE_PATH ]
	} else {
		nextResponsive[ POSITION_ATTRIBUTE_PATH ] = bag
	}

	setAttributes( { responsive: nextResponsive } )
}

/**
 * Strip null/undefined leaves and collapse empty objects. Returns
 * `null` when nothing meaningful remains.
 */
function collapseSubtree( subtree: PositionSubtree ): PositionSubtree | null {
	const out: PositionSubtree = {}

	if ( null !== subtree.value && undefined !== subtree.value ) {
		out.value = subtree.value
	}

	if ( subtree.offsets && 'object' === typeof subtree.offsets ) {
		const offsets: Record<string, OffsetValue> = {}
		for ( const side of OFFSET_SIDES ) {
			const val = subtree.offsets[ side ]
			if ( val && 'object' === typeof val ) {
				offsets[ side ] = val
			}
		}
		if ( 0 < Object.keys( offsets ).length ) {
			out.offsets = offsets
		}
	}

	if ( null !== subtree.zIndex && undefined !== subtree.zIndex ) {
		out.zIndex = subtree.zIndex
	}

	return 0 === Object.keys( out ).length ? null : out
}

/**
 * Compute whether any ancestor block sets a non-static position (#644).
 * Reads the effective position at the currently-viewed breakpoint so
 * the warning stays in sync with the switcher.
 */
function useHasPositionedAncestor(
	clientId: string | undefined,
	activeBreakpoint: string,
): boolean {
	return useSelect(
		( select ) => {
			if ( ! clientId ) {
				return false
			}

			const store = select( 'core/block-editor' ) as {
				getBlockParents: ( id: string ) => string[]
				getBlockAttributes: ( id: string ) => Record<string, unknown> | undefined
			}

			const parents = store.getBlockParents( clientId )

			for ( const parentId of parents ) {
				const attrs = store.getBlockAttributes( parentId )
				if ( ! attrs ) {
					continue
				}

				const layer = resolveAtBreakpoint(
					attrs as PositionAttributes,
					activeBreakpoint,
					breakpoints,
				)

				if ( layer && null !== layer.value && 'static' !== layer.value ) {
					return true
				}
			}

			return false
		},
		[ clientId, activeBreakpoint ],
	)
}

function parseUnitControl( raw: string | undefined ): OffsetValue | null {
	if ( undefined === raw || null === raw ) {
		return null
	}
	const trimmed = raw.trim()
	if ( '' === trimmed ) {
		return null
	}
	if ( 'auto' === trimmed.toLowerCase() ) {
		return { value: 0, unit: 'auto' }
	}

	const match = trimmed.match( /^(-?\d+(?:\.\d+)?)\s*([a-z%]+)$/i )
	if ( ! match ) {
		return null
	}

	const unit = match[ 2 ].toLowerCase()
	if ( ! ( OFFSET_UNITS as readonly string[] ).includes( unit ) ) {
		return null
	}

	const numeric = Number( match[ 1 ] )
	if ( ! Number.isFinite( numeric ) ) {
		return null
	}

	return { value: numeric, unit: unit as OffsetValue[ 'unit' ] }
}

function formatUnitControl( offset: OffsetValue | null ): string {
	if ( ! offset ) {
		return ''
	}
	if ( 'auto' === offset.unit ) {
		return 'auto'
	}
	return `${ offset.value }${ offset.unit }`
}

export const withPositionControl = createHigherOrderComponent(
	( BlockEdit: ComponentType<BlockEditProps> ) => {
		function PositionBlockEdit( props: BlockEditProps ): JSX.Element {
			const { name, attributes, setAttributes, clientId } = props

			const enabled = useMemo( () => blockSupportsPosition( name ), [ name ] )

			const activeBreakpoint = useSyncExternalStore(
				subscribeActiveBreakpoint,
				getActiveBreakpoint,
				getActiveBreakpoint,
			)

			const effectiveLayer = useMemo(
				() => resolveAtBreakpoint( attributes, activeBreakpoint, breakpoints ),
				[ attributes, activeBreakpoint ],
			)

			const rawValue = useMemo(
				() => rawValueAt( attributes, activeBreakpoint ),
				[ attributes, activeBreakpoint ],
			)

			const rawZ = useMemo(
				() => rawZIndexAt( attributes, activeBreakpoint ),
				[ attributes, activeBreakpoint ],
			)

			const rawOffsets = useMemo(
				() => ( {
					top:    rawOffsetAt( attributes, activeBreakpoint, 'top' ),
					right:  rawOffsetAt( attributes, activeBreakpoint, 'right' ),
					bottom: rawOffsetAt( attributes, activeBreakpoint, 'bottom' ),
					left:   rawOffsetAt( attributes, activeBreakpoint, 'left' ),
				} ),
				[ attributes, activeBreakpoint ],
			)

			const hasPositionedAncestor = useHasPositionedAncestor(
				clientId,
				activeBreakpoint,
			)

			const writePatch = useCallback(
				( patch: Partial<PositionSubtree> ) => {
					const current = readBreakpointSubtree( attributes, activeBreakpoint )
					const next: PositionSubtree = { ...current }

					if ( 'value' in patch ) {
						if ( null === patch.value ) {
							delete next.value
						} else {
							next.value = patch.value
						}
					}

					if ( 'zIndex' in patch ) {
						if ( null === patch.zIndex || undefined === patch.zIndex ) {
							delete next.zIndex
						} else {
							next.zIndex = patch.zIndex
						}
					}

					if ( 'offsets' in patch && patch.offsets ) {
						const nextOffsets = { ...( next.offsets ?? {} ) }
						for ( const side of OFFSET_SIDES ) {
							if ( side in patch.offsets ) {
								const nextVal = patch.offsets[ side ]
								if ( null === nextVal || undefined === nextVal ) {
									delete nextOffsets[ side ]
								} else {
									nextOffsets[ side ] = nextVal
								}
							}
						}
						if ( 0 === Object.keys( nextOffsets ).length ) {
							delete next.offsets
						} else {
							next.offsets = nextOffsets
						}
					}

					writeSubtree( attributes, setAttributes, activeBreakpoint, next )
				},
				[ attributes, setAttributes, activeBreakpoint ],
			)

			const resetPanel = useCallback( () => {
				writeSubtree( attributes, setAttributes, activeBreakpoint, {} )
			}, [ attributes, setAttributes, activeBreakpoint ] )

			if ( ! enabled ) {
				return <BlockEdit { ...props } />
			}

			const effectiveValue = effectiveLayer?.value ?? 'static'
			const showFields     = 'static' !== effectiveValue && null !== effectiveValue

			// #644: warning is shown when the RESOLVED position at this
			// breakpoint is absolute and no ancestor is positioned.
			const showAncestorWarning =
				'absolute' === effectiveValue && ! hasPositionedAncestor

			const positionOptions = [
				{ label: __( 'Static', 'artisanpack-visual-editor' ),   value: 'static' },
				{ label: __( 'Relative', 'artisanpack-visual-editor' ), value: 'relative' },
				{ label: __( 'Absolute', 'artisanpack-visual-editor' ), value: 'absolute' },
				{ label: __( 'Fixed', 'artisanpack-visual-editor' ),    value: 'fixed' },
				{ label: __( 'Sticky', 'artisanpack-visual-editor' ),   value: 'sticky' },
			]

			const hasAnyValue =
				null !== rawValue
				|| null !== rawZ
				|| null !== rawOffsets.top
				|| null !== rawOffsets.right
				|| null !== rawOffsets.bottom
				|| null !== rawOffsets.left

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls group="styles">
						<ToolsPanel
							label={ __( 'Position', 'artisanpack-visual-editor' ) }
							resetAll={ resetPanel }
							panelId={ clientId }
						>
							<ToolsPanelItem
								panelId={ clientId }
								hasValue={ () => hasAnyValue }
								label={ __( 'Position', 'artisanpack-visual-editor' ) }
								onDeselect={ resetPanel }
								isShownByDefault
							>
								<BaseControl __nextHasNoMarginBottom>
									<SelectControl
										label={ __( 'Position', 'artisanpack-visual-editor' ) }
										value={ effectiveValue }
										options={ positionOptions }
										help={ null === rawValue && BASE_KEY !== activeBreakpoint
											? __(
												'Inherited from a smaller breakpoint. Change to override.',
												'artisanpack-visual-editor',
											)
											: undefined }
										onChange={ ( next: string ) => {
											if ( ! POSITION_VALUES.includes( next as PositionValue ) ) {
												return
											}
											writePatch( { value: next as PositionValue } )
										} }
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>

									{ showAncestorWarning && (
										<Notice
											status="warning"
											isDismissible={ false }
											className="ap-position__ancestor-warning"
										>
											{ __(
												'This block is set to position: absolute but none of its ancestor blocks is positioned. It will position relative to the nearest positioned ancestor — often the page.',
												'artisanpack-visual-editor',
											) }
										</Notice>
									) }

									{ showFields && (
										<>
											<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 12 } }>
												{ OFFSET_SIDES.map( ( side ) => {
													const raw       = rawOffsets[ side ]
													const effective = effectiveLayer?.offsets[ side ] ?? null
													const inherited = null === raw && BASE_KEY !== activeBreakpoint && null !== effective

													return (
														<UnitControl
															key={ side }
															label={ sideLabel( side ) }
															value={ formatUnitControl( effective ) }
															units={ OFFSET_UNITS.map( ( unit ) => ( { value: unit, label: unit } ) ) }
															help={ inherited
																? __( 'Inherited', 'artisanpack-visual-editor' )
																: undefined }
															onChange={ ( next: string | undefined ) => {
																const parsed = parseUnitControl( next )
																writePatch( { offsets: { [ side ]: parsed } } )
															} }
															__next40pxDefaultSize
														/>
													)
												} ) }
											</div>

											<div style={ { marginTop: 12 } }>
												<NumberControl
													label={ __( 'z-index', 'artisanpack-visual-editor' ) }
													value={ effectiveLayer?.zIndex ?? '' }
													onChange={ ( next: string | number | undefined ) => {
														if ( undefined === next || '' === next ) {
															writePatch( { zIndex: null } )
															return
														}
														const parsed = Number( next )
														if ( Number.isFinite( parsed ) ) {
															writePatch( { zIndex: Math.trunc( parsed ) } )
														}
													} }
													help={ null === rawZ && BASE_KEY !== activeBreakpoint && null !== effectiveLayer?.zIndex
														? __( 'Inherited', 'artisanpack-visual-editor' )
														: undefined }
													__next40pxDefaultSize
												/>
											</div>
										</>
									) }
								</BaseControl>
							</ToolsPanelItem>
						</ToolsPanel>
					</InspectorControls>
				</>
			)
		}

		PositionBlockEdit.displayName = 'PositionBlockEdit'

		return PositionBlockEdit
	},
	'withPositionControl',
)

function sideLabel( side: OffsetSide ): string {
	switch ( side ) {
		case 'top':
			return __( 'Top', 'artisanpack-visual-editor' )
		case 'right':
			return __( 'Right', 'artisanpack-visual-editor' )
		case 'bottom':
			return __( 'Bottom', 'artisanpack-visual-editor' )
		case 'left':
			return __( 'Left', 'artisanpack-visual-editor' )
	}
}

/**
 * Register the BlockEdit filter at most once per page. Idempotent —
 * safe to call from both the post-editor and site-editor bootstrap
 * paths.
 */
export function registerPositionControl(): void {
	const host = globalThis as unknown as GlobalSentinelHost

	if ( host[ REGISTERED_KEY ] ) {
		return
	}

	addFilter( FILTER_HOOK, FILTER_NAMESPACE, withPositionControl )
	host[ REGISTERED_KEY ] = true
}
