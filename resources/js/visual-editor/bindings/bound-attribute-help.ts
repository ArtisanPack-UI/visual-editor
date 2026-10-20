/**
 * Help text for a control whose attribute is locked by a binding (#995).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import type { BoundAttributeInfo } from './bound-attributes-context';

export function getBoundAttributeHelp( info: BoundAttributeInfo ): string {
	if ( info.key !== null ) {
		return sprintf(
			/* translators: 1: binding source name, 2: source field key. */
			__( 'Connected to %1$s → %2$s. Disconnect it in the Block bindings panel to edit.', TEXT_DOMAIN ),
			info.source,
			info.key,
		);
	}

	return sprintf(
		/* translators: %s: binding source name. */
		__( 'Connected to %s. Disconnect it in the Block bindings panel to edit.', TEXT_DOMAIN ),
		info.source,
	);
}
