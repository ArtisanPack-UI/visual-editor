import { describe, expect, it } from 'vitest';

import { glowColorDisplayValue, glowColorStoredValue } from '../glow-color';

const palette = [
	{ slug: 'primary', color: '#3B82F6', name: 'Primary' },
	{ slug: 'neon-cyan', color: '#22d3ee', name: 'Neon cyan' },
];

describe( 'glow colour mapping (#878)', () => {
	it( 'stores a palette pick as a preset reference', () => {
		expect( glowColorStoredValue( '#22d3ee', palette ) ).toBe( 'var:preset|color|neon-cyan' );
		expect( glowColorStoredValue( '#3b82f6', palette ) ).toBe( 'var:preset|color|primary' );
	} );

	it( 'stores a custom colour raw', () => {
		expect( glowColorStoredValue( '#ff00ff', palette ) ).toBe( '#ff00ff' );
	} );

	it( 'clears when the pick is cleared', () => {
		expect( glowColorStoredValue( undefined, palette ) ).toBeUndefined();
		expect( glowColorStoredValue( '', palette ) ).toBeUndefined();
	} );

	it( 'resolves a preset reference back to its palette colour', () => {
		expect( glowColorDisplayValue( 'var:preset|color|neon-cyan', palette ) ).toBe( '#22d3ee' );
		expect( glowColorDisplayValue( 'var:preset|color|missing', palette ) ).toBeUndefined();
		expect( glowColorDisplayValue( '#ff00ff', palette ) ).toBe( '#ff00ff' );
		expect( glowColorDisplayValue( undefined, palette ) ).toBeUndefined();
	} );
} );
