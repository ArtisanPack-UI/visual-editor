/**
 * Whether the current user may write a block's Custom CSS (#881), and
 * whether that CSS may load URLs on this site.
 *
 * The editor mounts read `data-can-edit-block-css` (stamped by the host
 * from the `visual-editor.edit-block-css` gate) and call
 * {@link setBlockCssAccess}. The flag only hides the field: every save
 * path re-checks the gate server-side (`CustomCssGuard`).
 *
 * They also read `data-custom-css-allow-urls` (config
 * `effects.custom_css_allow_urls`) and call
 * {@link setCustomCssUrlAccess}, so the canvas previews exactly what the
 * front end prints.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

let allowed = false

let urlsAllowed = true

/**
 * Record whether the current user holds the block-CSS ability.
 *
 * @since 1.15.0
 */
export function setBlockCssAccess( value: boolean ): void {
	allowed = value
}

/**
 * Whether the Custom CSS field should be shown.
 *
 * @since 1.15.0
 */
export function canEditBlockCss(): boolean {
	return allowed
}

/**
 * Record whether Custom CSS may load resources (`url(`, `image-set(`,
 * `image(`, `src(`), as config `effects.custom_css_allow_urls`.
 *
 * @since 1.15.0
 */
export function setCustomCssUrlAccess( value: boolean ): void {
	urlsAllowed = value
}

/**
 * Whether Custom CSS may load resources. Defaults to `true`, the config
 * default.
 *
 * @since 1.15.0
 */
export function customCssAllowsUrls(): boolean {
	return urlsAllowed
}
