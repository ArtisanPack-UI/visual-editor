/**
 * Whether the server can import a pasted image for the current user
 * (#1066), read from `data-can-import-media`. Separate from the import flow
 * so the mounts can set it without loading the block editor. It only decides
 * whether the offer appears: the endpoint re-checks.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

let allowed = false;

/**
 * Record whether the current user can import media.
 *
 * @since 1.20.0
 */
export function setMediaImportAccess( value: boolean ): void {
    allowed = value;
}

/**
 * Whether to offer the import.
 *
 * @since 1.20.0
 */
export function canImportMedia(): boolean {
    return allowed;
}
