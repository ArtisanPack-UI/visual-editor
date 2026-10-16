/**
 * Glow color mapping for the Hover animation panel (#878).
 *
 * The panel stores a palette pick as a `var:preset|color|{slug}`
 * reference, so the glow follows later palette edits, and a custom
 * color raw. `AnimationCssEmitter::hoverColor()` resolves both.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

export interface PaletteColor {
	name?: string;
	slug: string;
	color: string;
}

const PRESET_COLOR_PREFIX = 'var:preset|color|';

/**
 * Resolve a stored glow color into the hex `ColorPalette` shows as
 * selected: a palette reference maps back to its color, a raw color
 * passes through.
 */
export function glowColorDisplayValue( stored: string | undefined, palette: PaletteColor[] ): string | undefined {
	if ( ! stored ) {
		return undefined;
	}
	if ( stored.startsWith( PRESET_COLOR_PREFIX ) ) {
		const slug = stored.slice( PRESET_COLOR_PREFIX.length );
		return palette.find( ( entry ) => entry.slug === slug )?.color;
	}
	return stored;
}

/**
 * Turn a `ColorPalette` pick into the stored value: a palette color is
 * kept as a preset reference so it follows later palette edits, and a
 * custom color is stored raw.
 */
export function glowColorStoredValue( picked: string | undefined, palette: PaletteColor[] ): string | undefined {
	if ( ! picked ) {
		return undefined;
	}
	const match = palette.find( ( entry ) => entry.color.toLowerCase() === picked.toLowerCase() );
	return match ? `${ PRESET_COLOR_PREFIX }${ match.slug }` : picked;
}
