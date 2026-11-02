/**
 * Picks the record a block's bindings resolve against (#1041).
 *
 * A block inside Single Content or a Query Loop row reads from that
 * entry, not from the page being edited, matching what the front end
 * renders. Those blocks are recognised by the `artisanpack/postPreview`
 * context key: the editor canvas also puts the page's own `postId` and
 * `postType` in context, so those alone can't tell a nested block from a
 * top-level one. The key is set before a picked entry finishes loading,
 * so the block's first request already targets the right record.
 *
 * Everything else uses the fallback, which is the page being edited.
 * That includes a Single Content block with no entry picked, whose
 * `postId` of `0` means "use the post in scope".
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import {
	hasPreviewContext,
	readPostEntityIdentity,
} from '../blocks/_shared/entity-placeholder-edit';
import { resolveCollection } from '../editor/content-type-slugs';

export interface BindingTarget {
	resource: string | null;
	id: number | string | null;
}

/**
 * Resolve the binding target from block context, falling back to the
 * given page-level target.
 *
 * @since 1.20.0
 */
export function resolveBindingTarget(
	context: unknown,
	fallback: BindingTarget,
): BindingTarget {
	if ( ! hasPreviewContext( context ) ) {
		return fallback;
	}

	const identity = readPostEntityIdentity( context );

	if ( null === identity ) {
		return fallback;
	}

	return {
		resource: resolveCollection( identity.postType ),
		id: identity.postId,
	};
}
