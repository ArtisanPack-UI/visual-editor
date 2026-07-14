<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture for #619's applied-template endpoint tests. Carries a `template`
 * column so tests can exercise the endpoint's resolution against a real
 * model attribute (rather than mocking).
 *
 * @property int $id
 * @property string $title
 * @property string|null $template
 * @property array<int, array<string, mixed>>|null $content
 */
class TestAppliedTemplateModel extends Model
{
	use HasBlockContent;

	protected $table = 'test_applied_template_pages';

	protected $guarded = [];
}
