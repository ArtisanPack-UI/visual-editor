<?php

/**
 * TemplateAssignmentManager Facade.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the TemplateAssignmentManager service.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Facades
 *
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplateAssignment assign( string $contentType, int $templateId, ?int $userId = null )
 * @method static bool unassign( string $contentType )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template|null defaultFor( string $contentType )
 * @method static \ArtisanPackUI\VisualEditor\Models\Template|array|null resolveTemplate( string $contentType, ?int $pageTemplateId = null )
 * @method static bool validateAssignment( int $templateId, string $contentType )
 * @method static int bulkAssign( int $templateId, string $modelClass, string $contentType, array $entityIds )
 * @method static \Illuminate\Database\Eloquent\Collection allAssignments()
 * @method static \ArtisanPackUI\VisualEditor\Models\TemplateAssignment|null getAssignment( string $contentType )
 *
 * @see \ArtisanPackUI\VisualEditor\Services\TemplateAssignmentManager
 * @since      1.0.0
 */
class TemplateAssignmentManager extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor(): string
	{
		return 'visual-editor.template-assignments';
	}
}
