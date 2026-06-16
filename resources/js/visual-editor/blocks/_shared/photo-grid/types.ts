/**
 * Photo Grid attribute shape (#594).
 *
 * Container-level setting that normalises image-bearing descendants
 * onto a uniform aspect ratio. Orthogonal to layout — authors still
 * pick flex/grid/columns themselves; Photo Grid only enforces fill
 * behaviour for nested images.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

export type PhotoGridObjectFit = 'cover' | 'contain'

/**
 * Aspect ratio key shipped in the preset dropdown. `null` means
 * "inherit container" — the descendants don't get `aspect-ratio`
 * stamped on them and follow whatever sizing the container provides
 * (CSS-grid row, fixed height, etc.).
 */
export type PhotoGridAspectRatio = string | null

export interface PhotoGridAttribute {
	enabled: boolean
	aspectRatio: PhotoGridAspectRatio
	objectFit: PhotoGridObjectFit
	objectPosition: string
}

export interface PhotoGridDefaults {
	enable: boolean
	defaultAspectRatio: PhotoGridAspectRatio
	defaultObjectFit: PhotoGridObjectFit
	defaultObjectPosition: string
}

export const PHOTO_GRID_DEFAULTS: PhotoGridDefaults = {
	enable: true,
	defaultAspectRatio: '1/1',
	defaultObjectFit: 'cover',
	defaultObjectPosition: '50% 50%',
}

export const PHOTO_GRID_ATTRIBUTE_DEFAULT: PhotoGridAttribute = {
	enabled: false,
	aspectRatio: '1/1',
	objectFit: 'cover',
	objectPosition: '50% 50%',
}
