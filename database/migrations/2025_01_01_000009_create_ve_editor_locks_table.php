<?php
/**
 * Create ve_editor_locks table migration.
 *
 * Creates the editor locks table for preventing concurrent edits
 * to the same content with heartbeat-based lock management.
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
 * Create ve_editor_locks table migration class.
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
        Schema::create('ve_editor_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('ve_contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id');
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat');

            // Indexes
            $table->unique(['content_id', 'user_id']);
            $table->index(['content_id', 'last_heartbeat']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_editor_locks');
    }
};
