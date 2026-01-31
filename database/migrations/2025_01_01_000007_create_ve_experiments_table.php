<?php
/**
 * Create ve_experiments table migration.
 *
 * Creates the experiments table for A/B testing content variations
 * including goal tracking and traffic splitting.
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
 * Create ve_experiments table migration class.
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
        Schema::create('ve_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('ve_contents')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['headline', 'section', 'full_page'])->default('headline');
            $table->unsignedTinyInteger('traffic_split')->default(50);
            $table->enum('goal_type', ['clicks', 'conversions', 'time_on_page', 'scroll_depth']);
            $table->string('goal_target')->nullable();
            $table->enum('status', ['draft', 'running', 'paused', 'ended'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // winner_variant_id without constraint since ve_experiment_variants is created after
            $table->foreignId('winner_variant_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['content_id', 'status']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ve_experiments');
    }
};
