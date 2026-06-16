/**
 * Flex layout attribute shapes (#595).
 *
 * The `artisanpackFlex` attribute is structured by axis (container vs
 * item) and every leaf value is a `ResponsiveAttribute<T>` so the
 * existing per-breakpoint cascade applies.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

import type { ResponsiveAttribute } from '../../../responsive/types'

export type FlexDirectionValue = 'row' | 'row-reverse' | 'column' | 'column-reverse'

export type FlexWrapValue = 'nowrap' | 'wrap' | 'wrap-reverse'

export type JustifyContentValue =
	| 'flex-start'
	| 'flex-end'
	| 'center'
	| 'space-between'
	| 'space-around'
	| 'space-evenly'
	| 'start'
	| 'end'
	| 'left'
	| 'right'
	| 'stretch'

export type AlignItemsValue =
	| 'stretch'
	| 'flex-start'
	| 'flex-end'
	| 'center'
	| 'baseline'
	| 'start'
	| 'end'
	| 'self-start'
	| 'self-end'
	| 'first baseline'
	| 'last baseline'

export type AlignContentValue =
	| 'stretch'
	| 'flex-start'
	| 'flex-end'
	| 'center'
	| 'space-between'
	| 'space-around'
	| 'space-evenly'
	| 'start'
	| 'end'
	| 'baseline'

export type AlignSelfValue =
	| 'auto'
	| 'flex-start'
	| 'flex-end'
	| 'center'
	| 'stretch'
	| 'baseline'
	| 'start'
	| 'end'
	| 'self-start'
	| 'self-end'

export interface FlexContainerAttributes {
	enabled?: ResponsiveAttribute<boolean | null>
	direction?: ResponsiveAttribute<FlexDirectionValue | null>
	wrap?: ResponsiveAttribute<FlexWrapValue | null>
	justifyContent?: ResponsiveAttribute<JustifyContentValue | null>
	alignItems?: ResponsiveAttribute<AlignItemsValue | null>
	alignContent?: ResponsiveAttribute<AlignContentValue | null>
	placeContent?: ResponsiveAttribute<string | null>
	gap?: {
		row?: ResponsiveAttribute<string | null>
		column?: ResponsiveAttribute<string | null>
	}
}

export interface FlexItemAttributes {
	alignSelf?: ResponsiveAttribute<AlignSelfValue | null>
	grow?: ResponsiveAttribute<number | null>
	shrink?: ResponsiveAttribute<number | null>
	basis?: ResponsiveAttribute<string | null>
	order?: ResponsiveAttribute<number | null>
}

export interface ArtisanpackFlexAttribute {
	container?: FlexContainerAttributes
	item?: FlexItemAttributes
}

export const ARTISANPACK_FLEX_DEFAULT: ArtisanpackFlexAttribute = {
	container: {},
	item: {},
}
