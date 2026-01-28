<?php
/**
 * Create ve_contents table migration.
 *
 * Creates the main content storage table for the visual editor,
 * including sections, settings, SEO metadata, and publishing status.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.0.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create ve_contents table migration class.
 *
 * @since 1.0.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('ve_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('content_type')->default('page');

            // Optional cross-package FK: cms-framework may not be installed
            $table->foreignId('content_type_id')->nullable();

            $table->string('title');
            $table->string('slug')->index();
            $table->text('excerpt')->nullable();
            $table->json('sections');
            $table->json('settings')->nullable();
            $table->string('template')->nullable();
            $table->json('template_overrides')->nullable();
            $table->enum('status', ['draft', 'pending', 'published', 'scheduled', 'private'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();

            // Optional cross-package FK: media-library may not be installed
            $table->foreignId('featured_media_id')->nullable();

            // Ownership
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Lock
            $table->json('lock')->nullable();

            // Timestamps and soft deletes
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['content_type', 'status']);
            $table->index(['content_type', 'slug']);
            $table->unique(['content_type', 'slug']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_contents');
    }
};
