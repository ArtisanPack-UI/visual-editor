/**
 * Search — constants + helpers.
 *
 * Ported verbatim from `@wordpress/block-library/src/search/utils.js`
 * (v9.43.0). Kept as a local module so the fork does not reach into the
 * block-library package's source tree.
 */

export const PC_WIDTH_DEFAULT = 50;
export const PX_WIDTH_DEFAULT = 350;
export const MIN_WIDTH = 220;

export function isPercentageUnit( unit: string | undefined ): boolean {
	return unit === '%';
}
