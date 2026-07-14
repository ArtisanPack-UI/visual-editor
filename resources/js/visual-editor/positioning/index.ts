/**
 * Public entry point for the CSS positioning feature (#640).
 *
 * Mirrors the split in `box-shadows/index.ts` — the HOC + filter
 * registrars are NOT re-exported here because they import
 * `@wordpress/blocks`, which trips JSON-import-attribute requirements
 * when host bundlers walk this barrel transitively.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

export {
	coerceSubtree,
	rawOffsetAt,
	rawValueAt,
	rawZIndexAt,
	resolveAtBreakpoint,
	resolvePosition,
} from './resolver'

export {
	emitPositionCss,
	layerDeclarations,
	mergedBreakpointLayers,
} from './emitter'

export type {
	OffsetSide,
	OffsetSubtree,
	OffsetUnit,
	OffsetValue,
	PositionAttributes,
	PositionSubtree,
	PositionValue,
	ResolvedPosition,
	ResolvedPositionLayer,
} from './types'

export {
	OFFSET_SIDES,
	OFFSET_UNITS,
	POSITION_ATTRIBUTE_PATH,
	POSITION_VALUES,
} from './types'
