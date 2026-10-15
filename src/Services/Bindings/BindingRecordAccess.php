<?php

/**
 * Per-record access check for the binding context endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Whether the current user may read the record a binding context names
 * (F-13). The bindings resolve and block preview endpoints take any
 * `resource` / `id` pair from the request, so without this check an
 * editor could read bound fields off records they can't open.
 *
 * When the host registers a policy for the record's model, the user
 * needs its `view` ability. A model without a policy keeps the earlier
 * behavior (any user who passes the API middleware may read it), so
 * hosts that never wrote one don't break.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
final class BindingRecordAccess
{
	/**
	 * Whether the current user may read `$model` through a binding.
	 *
	 * @since 1.15.0
	 *
	 * @param  Model  $model  The record the binding context resolved to.
	 */
	public static function allows( Model $model ): bool
	{
		if ( null === Gate::getPolicyFor( $model ) ) {
			return true;
		}

		return Gate::allows( 'view', $model );
	}
}
