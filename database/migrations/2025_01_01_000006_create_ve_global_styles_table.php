<?php
/**
 * Create ve_global_styles table migration.
 *
 * Creates the global styles table for storing theme customizations
 * and CSS custom property overrides.
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
 * Create ve_global_styles table migration class.
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
        Schema::create('ve_global_styles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->json('theme_default')->nullable();
            $table->boolean('is_customized')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_global_styles');
    }
};
