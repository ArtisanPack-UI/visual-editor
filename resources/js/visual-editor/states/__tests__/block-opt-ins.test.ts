import { describe, expect, it } from 'vitest'

import column from '../../blocks/column/block.json'
import columns from '../../blocks/columns/block.json'
import gridItem from '../../blocks/grid-item/block.json'
import group from '../../blocks/group/block.json'
import heading from '../../blocks/heading/block.json'
import paragraph from '../../blocks/paragraph/block.json'

interface StatesSupport {
	attributes: string[]
}

function statesRoots( blockJson: { supports: Record<string, unknown> } ): string[] {
	return ( blockJson.supports.artisanpackStates as StatesSupport | undefined )?.attributes ?? []
}

/**
 * #875 — the renderer compiles `attributes.states` for every block, so
 * the container blocks (card hover: lift + border + shadow) and the
 * text blocks have to expose the state UI to author it.
 */
describe( 'artisanpackStates block opt-ins (#875)', () => {
	it.each( [
		[ 'group', group ],
		[ 'column', column ],
		[ 'columns', columns ],
		[ 'grid-item', gridItem ],
	] )( '%s opts color, border, shadow and transform into states', ( _name, blockJson ) => {
		expect( statesRoots( blockJson ) ).toEqual( expect.arrayContaining( [
			'backgroundColor',
			'color.background',
			'color.text',
			'border.color',
			'border.width',
			'shadow',
			'dimensions.transform',
			'transition',
		] ) )
	} )

	it.each( [
		[ 'heading', heading ],
		[ 'paragraph', paragraph ],
	] )( '%s opts color and text decoration into states', ( _name, blockJson ) => {
		expect( statesRoots( blockJson ) ).toEqual( expect.arrayContaining( [
			'textColor',
			'color.text',
			'typography.textDecoration',
			'transition',
		] ) )
	} )
} )
