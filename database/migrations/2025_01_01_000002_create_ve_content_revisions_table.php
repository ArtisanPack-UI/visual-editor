<?php
/**
 * Create ve_content_revisions table migration.
 *
 * Creates the content revisions table for storing autosaves,
 * manual saves, and named snapshots of content.
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
 * Create ve_content_revisions table migration class.
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
        Schema::create('ve_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('ve_contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['autosave', 'manual', 'named', 'publish', 'pre_restore'])->default('autosave');
            $table->string('name')->nullable();
            $table->json('data');
            $table->text('change_summary')->nullable();
            $table->timestamp('created_at');

            // Indexes
            $table->index(['content_id', 'created_at']);
            $table->index(['content_id', 'type']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_content_revisions');
    }
};
