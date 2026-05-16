<?php

/**
 * Drop the plan-11 Phase D legacy `visual_editor_*` tables.
 *
 * Phase H replaced the plan-11 site-editor backing tables with
 * cms-framework's `template_parts`, `templates`, `block_patterns`,
 * `block_pattern_categories`, `global_styles`, `menus`, `menu_items`,
 * and `menu_location_assignments` tables.
 *
 * This migration drops the now-orphaned legacy tables on apps that
 * ran the old `create_visual_editor_*` migrations before #434 landed.
 * Fresh installs from v1.x onward never create them in the first place.
 *
 * Order matters: pivots first, then the keyed-from tables. We use
 * `dropIfExists` so the migration is safe to run on a fresh install
 * (where the tables never existed) and on an env where some prior
 * cleanup already dropped a subset.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		// Drop the pivot first to release foreign-key dependencies.
		Schema::dropIfExists( 'visual_editor_pattern_category' );

		Schema::dropIfExists( 'visual_editor_templates' );
		Schema::dropIfExists( 'visual_editor_template_parts' );
		Schema::dropIfExists( 'visual_editor_global_styles' );
		Schema::dropIfExists( 'visual_editor_navigations' );
		Schema::dropIfExists( 'visual_editor_patterns' );
		Schema::dropIfExists( 'visual_editor_pattern_categories' );
	}

	public function down(): void
	{
		// Irreversible by design. The plan-11 Phase D schema is
		// retired; data in those tables was never authoritative once
		// Phase H landed and there's no migration path back. Apps that
		// need the old tables should roll back the entire visual-editor
		// package to a pre-v1 release.
	}
};
