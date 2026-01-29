<?php

/**
 * Rename sections to blocks on ve_contents table.
 *
 * Flattens the nested sections/blocks hierarchy into a single
 * flat blocks array. Extracts all blocks from their section
 * wrappers and stores them at the top level.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.1.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename sections to blocks migration class.
 *
 * @since 1.1.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * Renames the sections column to blocks and flattens
     * existing nested section/block data into a flat block array.
     *
     * @since 1.1.0
     */
    public function up(): void
    {
        Schema::table('ve_contents', function (Blueprint $table) {
            $table->renameColumn('sections', 'blocks');
        });

        // Transform existing data: flatten nested sections into flat blocks.
        DB::table('ve_contents')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                $sections = json_decode($row->blocks, true);

                if (!is_array($sections)) {
                    continue;
                }

                $flatBlocks = [];
                foreach ($sections as $section) {
                    if (isset($section['blocks']) && is_array($section['blocks'])) {
                        foreach ($section['blocks'] as $block) {
                            $flatBlocks[] = $block;
                        }
                    }
                }

                DB::table('ve_contents')
                    ->where('id', $row->id)
                    ->update(['blocks' => json_encode($flatBlocks)]);
            }
        });
    }

    /**
     * Reverses the migrations.
     *
     * Renames blocks back to sections and wraps each block
     * in a generic section wrapper for backward compatibility.
     *
     * @since 1.1.0
     */
    public function down(): void
    {
        // Transform data back: wrap flat blocks in generic sections.
        DB::table('ve_contents')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                $blocks = json_decode($row->blocks, true);

                if (!is_array($blocks) || empty($blocks)) {
                    continue;
                }

                $sections = [
                    [
                        'id'       => 've-section-' . uniqid(),
                        'type'     => 'generic',
                        'blocks'   => $blocks,
                        'settings' => [],
                    ],
                ];

                DB::table('ve_contents')
                    ->where('id', $row->id)
                    ->update(['blocks' => json_encode($sections)]);
            }
        });

        Schema::table('ve_contents', function (Blueprint $table) {
            $table->renameColumn('blocks', 'sections');
        });
    }
};
