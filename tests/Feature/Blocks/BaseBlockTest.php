<?php

declare(strict_types=1);

use Tests\Stubs\StubBlock;
use Tests\Stubs\StubContainerBlock;
use Tests\Stubs\StubCustomUiBlock;

it('returns the correct type', function () {
    $block = new StubBlock;
    expect($block->getType())->toBe('stub');
});

it('returns the correct name', function () {
    $block = new StubBlock;
    expect($block->getName())->toBe('Stub Block');
});

it('returns the correct description', function () {
    $block = new StubBlock;
    expect($block->getDescription())->toBe('A stub block for testing.');
});

it('returns the correct icon', function () {
    $block = new StubBlock;
    expect($block->getIcon())->toBe('cube');
});

it('returns the correct category', function () {
    $block = new StubBlock;
    expect($block->getCategory())->toBe('text');
});

it('returns keywords', function () {
    $block = new StubBlock;
    expect($block->getKeywords())->toBe(['test', 'stub']);
});

it('returns content schema', function () {
    $block = new StubBlock;
    $schema = $block->getContentSchema();

    expect($schema)->toHaveKey('text')
        ->and($schema['text']['type'])->toBe('text');
});

it('returns default content from schema', function () {
    $block = new StubBlock;
    $defaults = $block->getDefaultContent();

    expect($defaults)->toHaveKey('text')
        ->and($defaults['text'])->toBe('');
});

it('is public by default', function () {
    $block = new StubBlock;
    expect($block->isPublic())->toBeTrue();
});

it('does not support inner blocks by default', function () {
    $block = new StubBlock;
    expect($block->supportsInnerBlocks())->toBeFalse();
});

it('supports inner blocks when configured', function () {
    $block = new StubContainerBlock;
    expect($block->supportsInnerBlocks())->toBeTrue();
});

it('returns inner blocks orientation', function () {
    $block = new StubContainerBlock;
    expect($block->getInnerBlocksOrientation())->toBe('vertical');
});

it('returns allowed children for container blocks', function () {
    $block = new StubContainerBlock;
    expect($block->getAllowedChildren())->toBe(['stub', 'paragraph']);
});

it('returns null allowed parents by default', function () {
    $block = new StubBlock;
    expect($block->getAllowedParents())->toBeNull();
});

it('reports JS renderer status', function () {
    expect((new StubBlock)->hasJsRenderer())->toBeFalse()
        ->and((new StubContainerBlock)->hasJsRenderer())->toBeTrue();
});

it('does not have custom inspector by default', function () {
    $block = new StubBlock;
    expect($block->hasCustomInspector())->toBeFalse()
        ->and($block->renderInspector())->toBe('');
});

it('does not have custom toolbar by default', function () {
    $block = new StubBlock;
    expect($block->hasCustomToolbar())->toBeFalse()
        ->and($block->renderToolbar())->toBe('')
        ->and($block->getToolbarControls())->toBe([]);
});

it('serializes to array', function () {
    $block = new StubBlock;
    $array = $block->toArray();

    expect($array['type'])->toBe('stub')
        ->and($array['name'])->toBe('Stub Block')
        ->and($array['category'])->toBe('text')
        ->and($array['public'])->toBeTrue()
        ->and($array['supportsInnerBlocks'])->toBeFalse()
        ->and($array['hasJsRenderer'])->toBeFalse();
});

it('returns version 1 by default', function () {
    $block = new StubBlock;
    expect($block->getVersion())->toBe(1);
});

it('returns content unchanged from migrate', function () {
    $block = new StubBlock;
    $content = ['text' => 'hello'];
    expect($block->migrate($content, 1))->toBe($content);
});

it('supports custom toolbar when declared', function () {
    $block = new StubCustomUiBlock;
    expect($block->hasCustomToolbar())->toBeTrue()
        ->and($block->renderToolbar())->toContain('custom-toolbar-btn');
});

it('supports custom inspector when declared', function () {
    $block = new StubCustomUiBlock;
    expect($block->hasCustomInspector())->toBeTrue()
        ->and($block->renderInspector())->toContain('custom-inspector-panel');
});

it('includes custom UI flags in toArray', function () {
    $block = new StubCustomUiBlock;
    $array = $block->toArray();
    expect($array['hasCustomToolbar'])->toBeTrue()
        ->and($array['hasCustomInspector'])->toBeTrue();
});
