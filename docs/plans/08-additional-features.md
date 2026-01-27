# Visual Editor - Additional Features

## Versioning & Revisions

### Auto-Save System

```php
class RevisionManager
{
    protected int $autoSaveInterval = 60; // seconds
    protected int $maxRevisions = 100;

    public function autoSave(Content $content): void
    {
        // Only save if content changed
        if (!$this->hasChanges($content)) {
            return;
        }

        $this->createRevision($content, 'autosave');
    }

    public function createRevision(Content $content, string $type = 'manual'): ContentRevision
    {
        $revision = ContentRevision::create([
            'content_id' => $content->id,
            'user_id' => auth()->id(),
            'type' => $type, // autosave, manual, publish
            'name' => null, // User can name later
            'data' => [
                'sections' => $content->sections,
                'settings' => $content->settings,
                'styles' => $content->styles,
            ],
            'created_at' => now(),
        ]);

        $this->cleanupOldRevisions($content);

        return $revision;
    }

    public function createNamedVersion(Content $content, string $name): ContentRevision
    {
        $revision = $this->createRevision($content, 'named');
        $revision->update(['name' => $name]);

        return $revision;
    }

    public function restore(ContentRevision $revision): void
    {
        $content = $revision->content;

        // Create backup of current state
        $this->createRevision($content, 'pre_restore');

        // Restore revision data
        $content->update([
            'sections' => $revision->data['sections'],
            'settings' => $revision->data['settings'],
            'styles' => $revision->data['styles'],
        ]);
    }

    protected function cleanupOldRevisions(Content $content): void
    {
        $revisions = $content->revisions()
            ->where('type', 'autosave')
            ->orderByDesc('created_at')
            ->skip($this->maxRevisions)
            ->take(1000)
            ->get();

        foreach ($revisions as $revision) {
            $revision->delete();
        }
    }
}
```

### Version History UI

```php
class VersionHistoryModal extends Component
{
    public Content $content;
    public ?ContentRevision $selectedRevision = null;
    public bool $showDiff = false;

    public function getRevisions(): Collection
    {
        return $this->content->revisions()
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($r) => $r->created_at->format('Y-m-d'));
    }

    public function selectRevision(int $revisionId): void
    {
        $this->selectedRevision = ContentRevision::find($revisionId);
    }

    public function restore(): void
    {
        if (!$this->selectedRevision) return;

        app(RevisionManager::class)->restore($this->selectedRevision);

        $this->dispatch('content-restored');
        $this->dispatch('close-modal');
    }

    public function nameRevision(int $revisionId, string $name): void
    {
        ContentRevision::where('id', $revisionId)->update([
            'name' => $name,
            'type' => 'named',
        ]);
    }
}
```

---

## AI Assistant

### AI Provider Interface

```php
interface AIProviderInterface
{
    public function generateText(string $prompt, array $options = []): string;
    public function improveText(string $text, string $instruction): string;
    public function generateAltText(string $imageUrl): string;
    public function suggestHeadlines(string $content, int $count = 5): array;
    public function analyzeContent(string $content): array;
}
```

### AI Assistant Service

```php
class AIAssistant
{
    protected AIProviderInterface $provider;

    public function __construct()
    {
        $providerClass = match(config('visual-editor.ai.provider')) {
            'openai' => OpenAIProvider::class,
            'anthropic' => AnthropicProvider::class,
            default => throw new \Exception('Invalid AI provider'),
        };

        $this->provider = app($providerClass);
    }

    public function suggestHeadline(string $content): array
    {
        if (!$this->isEnabled('content_suggestions')) {
            throw new AIDisabledException();
        }

        return $this->provider->suggestHeadlines($content, 5);
    }

    public function improveText(string $text, string $tone = 'professional'): string
    {
        if (!$this->isEnabled('content_suggestions')) {
            throw new AIDisabledException();
        }

        return $this->provider->improveText($text, "Improve this text to be more {$tone}");
    }

    public function generateAltText(string $imageUrl): string
    {
        if (!$this->isEnabled('alt_text')) {
            throw new AIDisabledException();
        }

        return $this->provider->generateAltText($imageUrl);
    }

    public function suggestSections(string $pageType, string $description): array
    {
        if (!$this->isEnabled('layout_suggestions')) {
            throw new AIDisabledException();
        }

        $prompt = "Suggest page sections for a {$pageType} page. Description: {$description}";
        $response = $this->provider->generateText($prompt);

        return $this->parseSectionSuggestions($response);
    }

    public function analyzeSEO(string $content, string $focusKeyword): array
    {
        if (!$this->isEnabled('seo_suggestions')) {
            throw new AIDisabledException();
        }

        return $this->provider->analyzeContent($content);
    }

    protected function isEnabled(string $feature): bool
    {
        return config('visual-editor.ai.enabled', false) &&
               config("visual-editor.ai.features.{$feature}", false);
    }
}
```

### AI Configuration

```php
// config/visual-editor.php

'ai' => [
    'enabled' => env('VISUAL_EDITOR_AI_ENABLED', false),

    'provider' => env('VISUAL_EDITOR_AI_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => 'gpt-4',
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => 'claude-3-sonnet',
    ],

    'features' => [
        'content_suggestions' => true,
        'alt_text' => true,
        'layout_suggestions' => false,
        'seo_suggestions' => false,
    ],

    'rate_limits' => [
        'requests_per_minute' => 10,
        'requests_per_day' => 100,
    ],
],
```

---

## Performance Budgets

### Page Analyzer

```php
class PageAnalyzer
{
    public function analyze(Content $content): array
    {
        $analysis = [
            'total_weight' => 0,
            'image_count' => 0,
            'image_weight' => 0,
            'script_count' => 0,
            'embed_count' => 0,
            'estimated_load_time' => 0,
            'warnings' => [],
            'recommendations' => [],
        ];

        foreach ($content->getAllBlocks() as $block) {
            $this->analyzeBlock($block, $analysis);
        }

        $this->calculateMetrics($analysis);
        $this->generateWarnings($analysis);
        $this->generateRecommendations($analysis);

        return $analysis;
    }

    protected function analyzeBlock(array $block, array &$analysis): void
    {
        switch ($block['type']) {
            case 'image':
                $analysis['image_count']++;
                if ($media = Media::find($block['content']['media_id'] ?? null)) {
                    $analysis['image_weight'] += $media->file_size;
                    $analysis['total_weight'] += $media->file_size;
                }
                break;

            case 'video':
                $analysis['embed_count']++;
                $analysis['total_weight'] += 500000; // Estimated embed weight
                break;

            case 'map':
            case 'social_embed':
                $analysis['embed_count']++;
                $analysis['total_weight'] += 300000;
                break;
        }
    }

    protected function generateWarnings(array &$analysis): void
    {
        $budget = config('visual-editor.performance.budget');

        if ($analysis['total_weight'] > $budget['max_weight']) {
            $analysis['warnings'][] = [
                'type' => 'weight',
                'message' => 'Page exceeds recommended weight limit',
                'value' => $this->formatBytes($analysis['total_weight']),
                'limit' => $this->formatBytes($budget['max_weight']),
            ];
        }

        if ($analysis['image_count'] > $budget['max_images']) {
            $analysis['warnings'][] = [
                'type' => 'images',
                'message' => 'Too many images on page',
                'value' => $analysis['image_count'],
                'limit' => $budget['max_images'],
            ];
        }

        if ($analysis['embed_count'] > $budget['max_embeds']) {
            $analysis['warnings'][] = [
                'type' => 'embeds',
                'message' => 'Too many third-party embeds',
                'value' => $analysis['embed_count'],
                'limit' => $budget['max_embeds'],
            ];
        }
    }

    protected function generateRecommendations(array &$analysis): void
    {
        // Check for unoptimized images
        foreach ($this->getImages($content) as $image) {
            if ($image->file_size > 500000 && !$image->has_webp) {
                $analysis['recommendations'][] = [
                    'type' => 'optimize_image',
                    'message' => "Image '{$image->name}' could be optimized",
                    'action' => 'Convert to WebP format',
                ];
            }
        }

        // Check for lazy loading
        if ($analysis['image_count'] > 5) {
            $analysis['recommendations'][] = [
                'type' => 'lazy_load',
                'message' => 'Enable lazy loading for below-fold images',
            ];
        }
    }
}
```

### Performance Configuration

```php
'performance' => [
    'enabled' => true,

    'budget' => [
        'max_weight' => 2097152, // 2MB
        'max_images' => 20,
        'max_embeds' => 5,
        'max_scripts' => 10,
    ],

    'show_warnings' => true,
    'block_publish_on_warning' => false,
],
```

---

## A/B Testing

### Experiment Manager

```php
class ExperimentManager
{
    public function createExperiment(Content $content, array $data): Experiment
    {
        return Experiment::create([
            'content_id' => $content->id,
            'name' => $data['name'],
            'type' => $data['type'], // headline, section, full_page
            'status' => 'draft',
            'traffic_split' => $data['traffic_split'] ?? 50,
            'goal_type' => $data['goal_type'], // clicks, conversions, time_on_page
            'goal_target' => $data['goal_target'] ?? null,
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    public function createVariant(Experiment $experiment, array $data): ExperimentVariant
    {
        return ExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'name' => $data['name'],
            'is_control' => $data['is_control'] ?? false,
            'content_data' => $data['content_data'],
            'impressions' => 0,
            'conversions' => 0,
        ]);
    }

    public function startExperiment(Experiment $experiment): void
    {
        $experiment->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function selectVariant(Experiment $experiment, string $visitorId): ExperimentVariant
    {
        // Consistent assignment based on visitor ID
        $hash = crc32($experiment->id . $visitorId);
        $percentage = $hash % 100;

        if ($percentage < $experiment->traffic_split) {
            return $experiment->variants()->where('is_control', true)->first();
        }

        return $experiment->variants()->where('is_control', false)->first();
    }

    public function trackImpression(ExperimentVariant $variant): void
    {
        $variant->increment('impressions');
    }

    public function trackConversion(Experiment $experiment, string $visitorId): void
    {
        $variant = $this->getAssignedVariant($experiment, $visitorId);
        $variant->increment('conversions');
    }

    public function getResults(Experiment $experiment): array
    {
        $variants = $experiment->variants;
        $control = $variants->firstWhere('is_control', true);
        $treatment = $variants->firstWhere('is_control', false);

        return [
            'control' => [
                'impressions' => $control->impressions,
                'conversions' => $control->conversions,
                'conversion_rate' => $this->calculateRate($control),
            ],
            'treatment' => [
                'impressions' => $treatment->impressions,
                'conversions' => $treatment->conversions,
                'conversion_rate' => $this->calculateRate($treatment),
            ],
            'improvement' => $this->calculateImprovement($control, $treatment),
            'statistical_significance' => $this->calculateSignificance($control, $treatment),
            'winner' => $this->determineWinner($control, $treatment),
        ];
    }

    protected function calculateSignificance($control, $treatment): float
    {
        // Z-test for statistical significance
        $p1 = $control->conversions / max($control->impressions, 1);
        $p2 = $treatment->conversions / max($treatment->impressions, 1);
        $n1 = $control->impressions;
        $n2 = $treatment->impressions;

        $pooledP = ($control->conversions + $treatment->conversions) / max($n1 + $n2, 1);
        $se = sqrt($pooledP * (1 - $pooledP) * (1/$n1 + 1/$n2));

        if ($se == 0) return 0;

        $z = ($p2 - $p1) / $se;

        // Convert Z-score to confidence level
        return $this->zToConfidence($z);
    }
}
```

---

## SEO Integration

When `artisanpack-ui/seo` package is installed:

```php
class SEOPanel extends Component
{
    public Content $content;
    public array $seoData = [];
    public array $analysis = [];

    public function mount(Content $content)
    {
        $this->content = $content;
        $this->seoData = $content->seo ?? [];
        $this->analyze();
    }

    public function analyze(): void
    {
        $this->analysis = [
            'title' => $this->analyzeTitle(),
            'description' => $this->analyzeDescription(),
            'keywords' => $this->analyzeKeywords(),
            'headings' => $this->analyzeHeadings(),
            'images' => $this->analyzeImages(),
            'links' => $this->analyzeLinks(),
            'score' => 0,
        ];

        $this->analysis['score'] = $this->calculateScore();
    }

    protected function analyzeTitle(): array
    {
        $title = $this->seoData['meta_title'] ?? '';
        $length = strlen($title);

        return [
            'value' => $title,
            'length' => $length,
            'status' => $length >= 30 && $length <= 60 ? 'good' : 'warning',
            'message' => $length < 30 ? 'Title is too short' :
                        ($length > 60 ? 'Title may be truncated' : 'Good length'),
        ];
    }

    protected function analyzeHeadings(): array
    {
        $headings = $this->extractHeadings();

        $hasH1 = count(array_filter($headings, fn($h) => $h['level'] === 'h1')) === 1;
        $hierarchy = $this->checkHeadingHierarchy($headings);

        return [
            'count' => count($headings),
            'has_single_h1' => $hasH1,
            'proper_hierarchy' => $hierarchy,
            'status' => $hasH1 && $hierarchy ? 'good' : 'warning',
        ];
    }

    protected function analyzeImages(): array
    {
        $images = $this->extractImages();
        $withoutAlt = array_filter($images, fn($img) => empty($img['alt']));

        return [
            'total' => count($images),
            'missing_alt' => count($withoutAlt),
            'status' => count($withoutAlt) === 0 ? 'good' : 'error',
            'images_without_alt' => $withoutAlt,
        ];
    }
}
```

---

## Offline Support

### Offline Sync Service

```php
class OfflineSyncService
{
    public function queueChange(string $contentId, array $change): void
    {
        // Store in IndexedDB via JavaScript
        $this->dispatch('offline:queue-change', [
            'content_id' => $contentId,
            'change' => $change,
            'timestamp' => now()->timestamp,
        ]);
    }

    public function syncChanges(string $contentId, array $queuedChanges): array
    {
        $content = Content::find($contentId);
        $conflicts = [];

        // Check for conflicts
        foreach ($queuedChanges as $change) {
            if ($this->hasConflict($content, $change)) {
                $conflicts[] = $change;
            } else {
                $this->applyChange($content, $change);
            }
        }

        return [
            'success' => count($queuedChanges) - count($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    protected function hasConflict(Content $content, array $change): bool
    {
        // Check if content was modified after the offline change
        return $content->updated_at->timestamp > $change['timestamp'];
    }
}
```

### JavaScript Offline Handler

```javascript
// resources/js/offline-sync.js

class OfflineSync {
    constructor() {
        this.db = null;
        this.init();
    }

    async init() {
        this.db = await this.openDatabase();
        window.addEventListener('online', () => this.syncPendingChanges());
    }

    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('visual-editor', 1);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                db.createObjectStore('changes', { keyPath: 'id', autoIncrement: true });
            };
        });
    }

    async queueChange(contentId, change) {
        const tx = this.db.transaction('changes', 'readwrite');
        const store = tx.objectStore('changes');
        await store.add({
            contentId,
            change,
            timestamp: Date.now(),
        });
    }

    async syncPendingChanges() {
        const tx = this.db.transaction('changes', 'readonly');
        const store = tx.objectStore('changes');
        const changes = await store.getAll();

        if (changes.length === 0) return;

        const response = await fetch('/api/visual-editor/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ changes }),
        });

        const result = await response.json();

        if (result.conflicts.length > 0) {
            this.showConflictDialog(result.conflicts);
        }

        // Clear synced changes
        await this.clearSyncedChanges(result.success);
    }
}
```

---

## Presence Awareness

### Presence Manager

```php
class PresenceManager
{
    protected $redis;

    public function join(string $contentId, int $userId): void
    {
        $key = "visual-editor:presence:{$contentId}";

        $this->redis->hset($key, $userId, json_encode([
            'user_id' => $userId,
            'name' => auth()->user()->name,
            'avatar' => auth()->user()->avatar_url,
            'joined_at' => now()->toIso8601String(),
            'last_active' => now()->toIso8601String(),
        ]));

        $this->redis->expire($key, 3600); // 1 hour TTL
    }

    public function leave(string $contentId, int $userId): void
    {
        $key = "visual-editor:presence:{$contentId}";
        $this->redis->hdel($key, $userId);
    }

    public function heartbeat(string $contentId, int $userId): void
    {
        $key = "visual-editor:presence:{$contentId}";
        $data = json_decode($this->redis->hget($key, $userId), true);

        if ($data) {
            $data['last_active'] = now()->toIso8601String();
            $this->redis->hset($key, $userId, json_encode($data));
        }
    }

    public function getActiveUsers(string $contentId): array
    {
        $key = "visual-editor:presence:{$contentId}";
        $users = $this->redis->hgetall($key);

        // Filter out stale users (inactive > 5 minutes)
        return collect($users)
            ->map(fn($data) => json_decode($data, true))
            ->filter(fn($user) =>
                now()->diffInMinutes($user['last_active']) < 5
            )
            ->values()
            ->toArray();
    }
}
```

---

## Accessibility Scanner

The accessibility scanner uses `artisanpack-ui/accessibility` to audit content for WCAG compliance.

### Accessibility Scanner Service

```php
use ArtisanPackUI\Accessibility\Facades\A11y;

class AccessibilityScanner
{
    public function scan(Content $content): AccessibilityScanResult
    {
        $issues = [];

        // Scan for all accessibility issues
        $issues = array_merge($issues, $this->scanAltText($content));
        $issues = array_merge($issues, $this->scanHeadingHierarchy($content));
        $issues = array_merge($issues, $this->scanColorContrast($content));
        $issues = array_merge($issues, $this->scanLinkText($content));
        $issues = array_merge($issues, $this->scanEmptyButtons($content));
        $issues = array_merge($issues, $this->scanFormLabels($content));

        // Allow custom checks via hook
        $issues = applyFilters('ap.visualEditor.accessibilityCheck', $issues, $content);

        return new AccessibilityScanResult($issues);
    }

    protected function scanColorContrast(Content $content): array
    {
        $issues = [];

        foreach ($this->extractColorPairs($content) as $pair) {
            $bgColor = $pair['background'];
            $textColor = $pair['text'];
            $linkColor = $pair['link'] ?? null;

            // Check text contrast using accessibility package
            if (!A11y::a11yCheckContrastColor($bgColor, $textColor)) {
                $issues[] = [
                    'type' => 'color_contrast',
                    'severity' => 'error',
                    'wcag' => '1.4.3',
                    'message' => __('Text color does not have sufficient contrast with background'),
                    'location' => $pair['location'],
                    'current' => [
                        'background' => $bgColor,
                        'text' => $textColor,
                    ],
                    'suggestion' => A11y::a11yGetContrastColor($bgColor),
                    'auto_fixable' => true,
                ];
            }

            // Check link contrast if present
            if ($linkColor && !A11y::a11yCheckContrastColor($bgColor, $linkColor)) {
                $issues[] = [
                    'type' => 'color_contrast',
                    'severity' => 'error',
                    'wcag' => '1.4.3',
                    'message' => __('Link color does not have sufficient contrast with background'),
                    'location' => $pair['location'],
                    'current' => [
                        'background' => $bgColor,
                        'link' => $linkColor,
                    ],
                    'suggestion' => A11y::a11yGetContrastColor($bgColor),
                    'auto_fixable' => true,
                ];
            }
        }

        return $issues;
    }

    protected function scanAltText(Content $content): array
    {
        $issues = [];

        foreach ($content->getBlocksByType('image') as $block) {
            if (empty($block['content']['alt'])) {
                $issues[] = [
                    'type' => 'missing_alt_text',
                    'severity' => 'error',
                    'wcag' => '1.1.1',
                    'message' => __('Image is missing alt text'),
                    'location' => $block['id'],
                    'auto_fixable' => false, // Requires user input
                ];
            }
        }

        return $issues;
    }

    protected function scanHeadingHierarchy(Content $content): array
    {
        $issues = [];
        $headings = $content->getBlocksByType('heading');
        $lastLevel = 0;

        foreach ($headings as $heading) {
            $level = (int) substr($heading['content']['level'], 1); // h1 -> 1

            // Check for skipped levels
            if ($level > $lastLevel + 1 && $lastLevel > 0) {
                $issues[] = [
                    'type' => 'heading_hierarchy',
                    'severity' => 'warning',
                    'wcag' => '1.3.1',
                    'message' => __('Heading level skipped from H:from to H:to', [
                        'from' => $lastLevel,
                        'to' => $level,
                    ]),
                    'location' => $heading['id'],
                    'auto_fixable' => false,
                ];
            }

            $lastLevel = $level;
        }

        // Check for multiple H1s
        $h1Count = count(array_filter($headings, fn($h) => $h['content']['level'] === 'h1'));
        if ($h1Count > 1) {
            $issues[] = [
                'type' => 'multiple_h1',
                'severity' => 'warning',
                'wcag' => '1.3.1',
                'message' => __('Page has multiple H1 headings (:count found)', ['count' => $h1Count]),
                'auto_fixable' => false,
            ];
        }

        return $issues;
    }

    public function autoFix(Content $content, array $issue): bool
    {
        if (!$issue['auto_fixable']) {
            return false;
        }

        switch ($issue['type']) {
            case 'color_contrast':
                return $this->fixColorContrast($content, $issue);
            default:
                return false;
        }
    }

    protected function fixColorContrast(Content $content, array $issue): bool
    {
        $location = $issue['location'];
        $suggestedColor = $issue['suggestion'];

        // Apply the suggested accessible color
        $content->updateBlockStyle($location, 'text_color', $suggestedColor);

        doAction('ap.visualEditor.accessibilityAutoFixed', $issue, $content);

        return true;
    }
}
```

### Accessibility Scanner Modal Component

```php
use Livewire\Component;

class AccessibilityScannerModal extends Component
{
    public Content $content;
    public array $scanResults = [];
    public bool $isScanning = false;
    public string $filterSeverity = 'all';

    public function scan(): void
    {
        $this->isScanning = true;

        $scanner = app(AccessibilityScanner::class);
        $result = $scanner->scan($this->content);

        $this->scanResults = $result->getIssues();
        $this->isScanning = false;

        doAction('ap.visualEditor.accessibilityScanCompleted', $this->scanResults, $this->content);
    }

    public function autoFixIssue(int $index): void
    {
        $issue = $this->scanResults[$index] ?? null;
        if (!$issue || !$issue['auto_fixable']) {
            return;
        }

        $scanner = app(AccessibilityScanner::class);
        if ($scanner->autoFix($this->content, $issue)) {
            // Remove from results and rescan
            unset($this->scanResults[$index]);
            $this->scanResults = array_values($this->scanResults);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Issue fixed automatically'),
            ]);
        }
    }

    public function autoFixAll(): void
    {
        $fixableIssues = array_filter($this->scanResults, fn($i) => $i['auto_fixable']);
        $scanner = app(AccessibilityScanner::class);
        $fixed = 0;

        foreach ($fixableIssues as $issue) {
            if ($scanner->autoFix($this->content, $issue)) {
                $fixed++;
            }
        }

        $this->scan(); // Rescan

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __(':count issues fixed automatically', ['count' => $fixed]),
        ]);
    }

    public function navigateToIssue(string $location): void
    {
        $this->dispatch('select-block', ['id' => $location]);
        $this->dispatch('close-modal');
    }

    public function exportReport(string $format = 'pdf'): void
    {
        // Generate and download report
        doAction('ap.visualEditor.accessibilityReportExported', $this->scanResults, $format);
    }

    public function getFilteredResultsProperty(): array
    {
        if ($this->filterSeverity === 'all') {
            return $this->scanResults;
        }

        return array_filter($this->scanResults, fn($i) => $i['severity'] === $this->filterSeverity);
    }

    public function render()
    {
        return view('visual-editor::components.accessibility-scanner-modal');
    }
}
```

### Accessibility Scanner Blade Template

```blade
{{-- resources/views/components/accessibility-scanner-modal.blade.php --}}

<x-artisanpack-modal wire:model="showScanner" title="{{ __('Accessibility Scanner') }}" size="lg">
    <div class="space-y-4">
        {{-- Scan button and filter --}}
        <div class="flex items-center justify-between">
            <x-artisanpack-button
                wire:click="scan"
                wire:loading.attr="disabled"
                color="primary"
            >
                <span wire:loading.remove wire:target="scan">{{ __('Scan Content') }}</span>
                <span wire:loading wire:target="scan">{{ __('Scanning...') }}</span>
            </x-artisanpack-button>

            <x-artisanpack-select
                wire:model.live="filterSeverity"
                :options="[
                    'all' => __('All Issues'),
                    'error' => __('Errors Only'),
                    'warning' => __('Warnings Only'),
                ]"
            />
        </div>

        {{-- Results summary --}}
        @if(count($scanResults) > 0)
            <div class="flex gap-4">
                <x-artisanpack-badge color="error">
                    {{ count(array_filter($scanResults, fn($i) => $i['severity'] === 'error')) }} {{ __('Errors') }}
                </x-artisanpack-badge>
                <x-artisanpack-badge color="warning">
                    {{ count(array_filter($scanResults, fn($i) => $i['severity'] === 'warning')) }} {{ __('Warnings') }}
                </x-artisanpack-badge>

                @if(count(array_filter($scanResults, fn($i) => $i['auto_fixable'])) > 0)
                    <x-artisanpack-button wire:click="autoFixAll" size="sm" color="success">
                        {{ __('Auto-fix All (:count)', ['count' => count(array_filter($scanResults, fn($i) => $i['auto_fixable']))]) }}
                    </x-artisanpack-button>
                @endif
            </div>
        @endif

        {{-- Issues list --}}
        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($this->filteredResults as $index => $issue)
                <x-artisanpack-card class="p-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-artisanpack-badge
                                    :color="$issue['severity'] === 'error' ? 'error' : 'warning'"
                                >
                                    {{ strtoupper($issue['severity']) }}
                                </x-artisanpack-badge>
                                @if(isset($issue['wcag']))
                                    <span class="text-xs text-gray-500">WCAG {{ $issue['wcag'] }}</span>
                                @endif
                            </div>
                            <p class="mt-1">{{ $issue['message'] }}</p>
                        </div>
                        <div class="flex gap-2">
                            @if($issue['auto_fixable'])
                                <x-artisanpack-button
                                    wire:click="autoFixIssue({{ $index }})"
                                    size="xs"
                                    color="success"
                                >
                                    {{ __('Fix') }}
                                </x-artisanpack-button>
                            @endif
                            @if(isset($issue['location']))
                                <x-artisanpack-button
                                    wire:click="navigateToIssue('{{ $issue['location'] }}')"
                                    size="xs"
                                >
                                    {{ __('Go to') }}
                                </x-artisanpack-button>
                            @endif
                        </div>
                    </div>
                </x-artisanpack-card>
            @empty
                @if(count($scanResults) === 0 && !$isScanning)
                    <x-artisanpack-alert type="info">
                        {{ __('Click "Scan Content" to check for accessibility issues.') }}
                    </x-artisanpack-alert>
                @else
                    <x-artisanpack-alert type="success">
                        {{ __('No accessibility issues found!') }}
                    </x-artisanpack-alert>
                @endif
            @endforelse
        </div>
    </div>

    <x-slot:actions>
        <x-artisanpack-button wire:click="exportReport('pdf')" color="secondary">
            {{ __('Export PDF') }}
        </x-artisanpack-button>
        <x-artisanpack-button wire:click="$set('showScanner', false)">
            {{ __('Close') }}
        </x-artisanpack-button>
    </x-slot:actions>
</x-artisanpack-modal>
```
