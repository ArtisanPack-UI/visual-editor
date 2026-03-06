<?php

declare(strict_types=1);

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use Tests\Stubs\StubBlock;
use Tests\Stubs\StubContainerBlock;
use Tests\Stubs\StubCustomUiBlock;

beforeEach(function () {
    $this->registry = app(BlockRegistry::class);
    $this->registry->clear();
});

afterEach(function () {
    $this->registry->clear();
});

it('can be resolved from the container', function () {
    expect($this->registry)->toBeInstanceOf(BlockRegistry::class);
});

it('can be resolved via alias', function () {
    expect(app('visual-editor.blocks'))->toBeInstanceOf(BlockRegistry::class);
});

it('is a singleton', function () {
    expect(app(BlockRegistry::class))->toBe(app(BlockRegistry::class));
});

it('registers a block', function () {
    $block = new StubBlock;
    $this->registry->register($block);

    expect($this->registry->has('stub'))->toBeTrue()
        ->and($this->registry->get('stub'))->toBe($block);
});

it('returns null for unregistered block', function () {
    expect($this->registry->get('nonexistent'))->toBeNull()
        ->and($this->registry->has('nonexistent'))->toBeFalse();
});

it('returns all registered blocks', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    $all = $this->registry->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toHaveKeys(['stub', 'stub-container']);
});

it('unregisters a block by type', function () {
    $this->registry->register(new StubBlock);
    $this->registry->unregister('stub');

    expect($this->registry->has('stub'))->toBeFalse();
});

it('unregisters multiple blocks', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);
    $this->registry->unregister(['stub', 'stub-container']);

    expect($this->registry->all())->toBeEmpty();
});

it('unregisters blocks by category', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);
    $this->registry->unregisterCategory('text');

    expect($this->registry->has('stub'))->toBeFalse()
        ->and($this->registry->has('stub-container'))->toBeTrue();
});

it('gets blocks by category', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    $textBlocks = $this->registry->getByCategory('text');

    expect($textBlocks)->toHaveCount(1)
        ->and($textBlocks)->toHaveKey('stub');
});

it('gets all unique categories', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    $categories = $this->registry->getCategories();

    expect($categories)->toContain('text', 'layout')
        ->and($categories)->toHaveCount(2);
});

it('gets container blocks', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    $containers = $this->registry->getContainerBlocks();

    expect($containers)->toHaveCount(1)
        ->and($containers)->toHaveKey('stub-container');
});

it('gets dynamic blocks with JS renderers', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    $dynamic = $this->registry->getDynamicBlocks();

    expect($dynamic)->toHaveCount(1)
        ->and($dynamic)->toHaveKey('stub-container');
});

it('includes custom UI blocks in toArray with correct flags', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubCustomUiBlock);

    $array = $this->registry->toArray();

    expect($array['stub']['hasCustomToolbar'])->toBeFalse()
        ->and($array['stub']['hasCustomInspector'])->toBeFalse()
        ->and($array['stub-custom-ui']['hasCustomToolbar'])->toBeTrue()
        ->and($array['stub-custom-ui']['hasCustomInspector'])->toBeTrue();
});

it('rejects blocks with empty type', function () {
    $block = new class extends \ArtisanPackUI\VisualEditor\Blocks\BaseBlock {};

    $this->registry->register($block);
})->throws(\InvalidArgumentException::class, 'Block type must be a non-empty string.');

it('clears all registered blocks', function () {
    $this->registry->register(new StubBlock);
    $this->registry->register(new StubContainerBlock);

    expect($this->registry->all())->toHaveCount(2);

    $this->registry->clear();

    expect($this->registry->all())->toBeEmpty();
});

it('serializes to array', function () {
    $this->registry->register(new StubBlock);

    $array = $this->registry->toArray();

    expect($array)->toHaveKey('stub')
        ->and($array['stub'])->toHaveKeys([
            'type', 'name', 'description', 'icon', 'category',
            'keywords', 'public', 'supportsInnerBlocks',
            'innerBlocksOrientation', 'allowedChildren',
            'allowedParents', 'hasJsRenderer', 'hasCustomInspector',
            'hasCustomToolbar', 'alignments',
        ]);
});
