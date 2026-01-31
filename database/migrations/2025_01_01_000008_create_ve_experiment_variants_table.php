<?php
/**
 * Create ve_experiment_variants table migration.
 *
 * Creates the experiment variants table for storing individual
 * A/B test variations with impression and conversion tracking.
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
 * Create ve_experiment_variants table migration class.
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
        Schema::create('ve_experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('ve_experiments')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_control')->default(false);
            $table->json('content_data');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['experiment_id', 'is_control']);
        });

        // Add foreign key for winner_variant_id now that the variants table exists.
        Schema::table('ve_experiments', function (Blueprint $table) {
            $table->foreign('winner_variant_id')
                ->references('id')
                ->on('ve_experiment_variants')
                ->nullOnDelete();
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::table('ve_experiments', function (Blueprint $table) {
            $table->dropForeign(['winner_variant_id']);
        });

        Schema::dropIfExists('ve_experiment_variants');
    }
};
