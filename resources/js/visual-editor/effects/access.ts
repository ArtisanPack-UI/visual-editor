/**
 * Whether the current user may write a block's Custom CSS (#881).
 *
 * The editor mounts read `data-can-edit-block-css` (stamped by the host
 * from the `visual-editor.edit-block-css` gate) and call
 * {@link setBlockCssAccess}. The flag only hides the field: every save
 * path re-checks the gate server-side (`CustomCssGuard`).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.15.0
 */

let allowed = false

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
