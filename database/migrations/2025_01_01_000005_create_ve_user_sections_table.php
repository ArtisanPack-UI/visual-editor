<?php
/**
 * Create ve_user_sections table migration.
 *
 * Creates the user sections table for storing user-created
 * reusable section templates.
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
 * Create ve_user_sections table migration class.
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
        Schema::create('ve_user_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->json('blocks');
            $table->json('styles')->nullable();
            $table->string('preview_image')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->integer('use_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'category']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_user_sections');
    }
};
