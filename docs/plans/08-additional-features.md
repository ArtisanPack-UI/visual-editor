# Visual Editor - Additional Features

> **Phases:** 5 & 6 (Advanced Features & Polish)
>
> | Feature | Phase | Priority |
> |---------|-------|----------|
> | Versioning & Revisions | 5 | Medium |
> | AI Assistant | 5 | Low |
> | Performance Budgets | 5 | Medium |
> | A/B Testing | 5 | Low |
> | SEO Integration | 5 | Medium |
> | Offline Support | 6 | Low |
> | Presence Awareness | 6 | Low |
> | Accessibility Scanner | 6 | Medium |

---

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

AI settings are stored using the CMS Framework settings system, allowing administrators to configure providers and API keys through the admin interface.

### AI Settings Registration

```php
// VisualEditorServiceProvider.php

protected function registerAISettings(): void
{
    // Core AI settings
    apRegisterSetting('visual_editor.ai.enabled', false, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.provider', 'openai', fn($v) => sanitizeText($v), 'string');

    // Provider API keys (encrypted in database)
    apRegisterSetting('visual_editor.ai.openai.api_key', '', fn($v) => encrypt($v), 'string');
    apRegisterSetting('visual_editor.ai.openai.model', 'gpt-4', fn($v) => sanitizeText($v), 'string');
    apRegisterSetting('visual_editor.ai.anthropic.api_key', '', fn($v) => encrypt($v), 'string');
    apRegisterSetting('visual_editor.ai.anthropic.model', 'claude-3-sonnet', fn($v) => sanitizeText($v), 'string');

    // Feature toggles
    apRegisterSetting('visual_editor.ai.features.content_suggestions', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.features.alt_text', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.features.layout_suggestions', false, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.features.seo_suggestions', false, fn($v) => (bool) $v, 'boolean');

    // Rate limits
    apRegisterSetting('visual_editor.ai.rate_limits.requests_per_minute', 10, fn($v) => (int) $v, 'integer');
    apRegisterSetting('visual_editor.ai.rate_limits.requests_per_day', 100, fn($v) => (int) $v, 'integer');
}
```

### AI Provider Interface

```php
interface AIProviderInterface
{
    /**
     * Get the provider's unique identifier.
     */
    public function getIdentifier(): string;

    /**
     * Get the provider's display name.
     */
    public function getName(): string;

    /**
     * Get available models for this provider.
     *
     * @return array<string, string> ['model-id' => 'Model Display Name']
     */
    public function getAvailableModels(): array;

    /**
     * Get the settings schema for admin configuration.
     *
     * @return array Schema definition for settings fields
     */
    public function getSettingsSchema(): array;

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Generate text from a prompt.
     */
    public function generateText(string $prompt, array $options = []): string;

    /**
     * Improve existing text based on instruction.
     */
    public function improveText(string $text, string $instruction): string;

    /**
     * Generate alt text for an image.
     */
    public function generateAltText(string $imageUrl): string;

    /**
     * Suggest headlines based on content.
     */
    public function suggestHeadlines(string $content, int $count = 5): array;

    /**
     * Analyze content for SEO or other purposes.
     */
    public function analyzeContent(string $content): array;
}
```

### AI Provider Registry

The provider registry allows developers to register custom AI providers using the hooks system:

```php
class AIProviderRegistry
{
    protected array $providers = [];

    public function __construct()
    {
        // Register default providers
        $this->register(new OpenAIProvider());
        $this->register(new AnthropicProvider());

        // Allow third-party providers via hook
        $this->providers = applyFilters('ap.visualEditor.aiProvidersRegister', $this->providers);
    }

    public function register(AIProviderInterface $provider): void
    {
        $this->providers[$provider->getIdentifier()] = $provider;

        // Register provider-specific settings
        $this->registerProviderSettings($provider);

        doAction('ap.visualEditor.aiProviderRegistered', $provider);
    }

    public function unregister(string $identifier): void
    {
        unset($this->providers[$identifier]);

        doAction('ap.visualEditor.aiProviderUnregistered', $identifier);
    }

    public function get(string $identifier): ?AIProviderInterface
    {
        return $this->providers[$identifier] ?? null;
    }

    public function all(): array
    {
        return $this->providers;
    }

    public function getAvailableProviders(): array
    {
        return collect($this->providers)
            ->map(fn($provider) => [
                'identifier' => $provider->getIdentifier(),
                'name' => $provider->getName(),
                'models' => $provider->getAvailableModels(),
                'configured' => $provider->isConfigured(),
            ])
            ->all();
    }

    protected function registerProviderSettings(AIProviderInterface $provider): void
    {
        $identifier = $provider->getIdentifier();
        $schema = $provider->getSettingsSchema();

        foreach ($schema as $key => $definition) {
            $settingKey = "visual_editor.ai.{$identifier}.{$key}";
            $default = $definition['default'] ?? null;
            $type = $definition['type'] ?? 'string';

            // Determine sanitization callback based on type
            $callback = match($type) {
                'boolean' => fn($v) => (bool) $v,
                'integer' => fn($v) => (int) $v,
                'encrypted' => fn($v) => $v ? encrypt($v) : '',
                default => fn($v) => sanitizeText($v),
            };

            apRegisterSetting($settingKey, $default, $callback, $type === 'encrypted' ? 'string' : $type);
        }
    }
}
```

### Registering Custom AI Providers

Developers can register custom AI providers via the hook system:

```php
// In a service provider or plugin

use ArtisanPackUI\VisualEditor\Contracts\AIProviderInterface;

class GeminiProvider implements AIProviderInterface
{
    public function getIdentifier(): string
    {
        return 'gemini';
    }

    public function getName(): string
    {
        return 'Google Gemini';
    }

    public function getAvailableModels(): array
    {
        return [
            'gemini-pro' => 'Gemini Pro',
            'gemini-pro-vision' => 'Gemini Pro Vision',
            'gemini-ultra' => 'Gemini Ultra',
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'encrypted',
                'label' => __('API Key'),
                'hint' => __('Your Google AI API key'),
                'default' => '',
            ],
            'model' => [
                'type' => 'string',
                'label' => __('Model'),
                'default' => 'gemini-pro',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        $encryptedKey = apGetSetting('visual_editor.ai.gemini.api_key', '');
        return !empty($encryptedKey);
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $apiKey = decrypt(apGetSetting('visual_editor.ai.gemini.api_key', ''));
        $model = apGetSetting('visual_editor.ai.gemini.model', 'gemini-pro');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}", [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
        ]);

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    public function improveText(string $text, string $instruction): string
    {
        return $this->generateText("{$instruction}:\n\n{$text}");
    }

    public function generateAltText(string $imageUrl): string
    {
        // Gemini Pro Vision can analyze images
        $apiKey = decrypt(apGetSetting('visual_editor.ai.gemini.api_key', ''));

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1/models/gemini-pro-vision:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Describe this image concisely for use as alt text for accessibility. Keep it under 125 characters.'],
                        ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64_encode(file_get_contents($imageUrl))]],
                    ],
                ],
            ],
        ]);

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    public function suggestHeadlines(string $content, int $count = 5): array
    {
        $response = $this->generateText("Generate {$count} headline suggestions for the following content. Return as a JSON array of strings:\n\n{$content}");
        return json_decode($response, true) ?? [];
    }

    public function analyzeContent(string $content): array
    {
        $response = $this->generateText("Analyze this content for SEO. Return a JSON object with 'score', 'suggestions', and 'issues':\n\n{$content}");
        return json_decode($response, true) ?? [];
    }
}

// Register the provider via hook
addFilter('ap.visualEditor.aiProvidersRegister', function (array $providers) {
    $providers['gemini'] = new GeminiProvider();
    return $providers;
});
```

### AI Assistant Service

```php
class AIAssistant
{
    protected AIProviderInterface $provider;
    protected AIProviderRegistry $registry;

    public function __construct(AIProviderRegistry $registry)
    {
        $this->registry = $registry;

        // Get provider from CMS Framework settings
        $providerName = apGetSetting('visual_editor.ai.provider', 'openai');

        $this->provider = $this->registry->get($providerName);

        if (!$this->provider) {
            throw new \Exception(__('Invalid AI provider: :provider', ['provider' => $providerName]));
        }

        if (!$this->provider->isConfigured()) {
            throw new AIConfigurationException(__('AI provider :provider is not configured', ['provider' => $this->provider->getName()]));
        }
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
        // Uses CMS Framework settings
        return apGetSetting('visual_editor.ai.enabled', false) &&
               apGetSetting("visual_editor.ai.features.{$feature}", false);
    }
}
```

### AI Provider Implementation

```php
class OpenAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        // Get API key from CMS Framework settings (decrypted)
        $encryptedKey = apGetSetting('visual_editor.ai.openai.api_key', '');
        $this->apiKey = $encryptedKey ? decrypt($encryptedKey) : '';
        $this->model = apGetSetting('visual_editor.ai.openai.model', 'gpt-4');

        if (empty($this->apiKey)) {
            throw new AIConfigurationException(__('OpenAI API key not configured'));
        }
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 1000,
        ]);

        return $response->json('choices.0.message.content');
    }

    // ... other methods
}

class AnthropicProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        // Get API key from CMS Framework settings (decrypted)
        $encryptedKey = apGetSetting('visual_editor.ai.anthropic.api_key', '');
        $this->apiKey = $encryptedKey ? decrypt($encryptedKey) : '';
        $this->model = apGetSetting('visual_editor.ai.anthropic.model', 'claude-3-sonnet');

        if (empty($this->apiKey)) {
            throw new AIConfigurationException(__('Anthropic API key not configured'));
        }
    }

    // ... implementation
}
```

### AI Settings Admin Component

The settings form dynamically renders fields for all registered providers:

```php
class AISettingsForm extends Component
{
    public bool $enabled = false;
    public string $provider = 'openai';
    public array $providerSettings = [];
    public bool $contentSuggestions = true;
    public bool $altText = true;
    public bool $layoutSuggestions = false;
    public bool $seoSuggestions = false;
    public int $requestsPerMinute = 10;
    public int $requestsPerDay = 100;

    protected AIProviderRegistry $registry;

    public function boot(AIProviderRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function mount(): void
    {
        // Check permission
        if (!auth()->user()->hasPermissionTo('visual_editor.manage')) {
            abort(403);
        }

        $this->enabled = apGetSetting('visual_editor.ai.enabled', false);
        $this->provider = apGetSetting('visual_editor.ai.provider', 'openai');
        $this->contentSuggestions = apGetSetting('visual_editor.ai.features.content_suggestions', true);
        $this->altText = apGetSetting('visual_editor.ai.features.alt_text', true);
        $this->layoutSuggestions = apGetSetting('visual_editor.ai.features.layout_suggestions', false);
        $this->seoSuggestions = apGetSetting('visual_editor.ai.features.seo_suggestions', false);
        $this->requestsPerMinute = apGetSetting('visual_editor.ai.rate_limits.requests_per_minute', 10);
        $this->requestsPerDay = apGetSetting('visual_editor.ai.rate_limits.requests_per_day', 100);

        // Load settings for all registered providers dynamically
        $this->loadProviderSettings();
    }

    protected function loadProviderSettings(): void
    {
        foreach ($this->registry->all() as $identifier => $provider) {
            $this->providerSettings[$identifier] = [];

            foreach ($provider->getSettingsSchema() as $key => $definition) {
                $settingKey = "visual_editor.ai.{$identifier}.{$key}";
                $value = apGetSetting($settingKey, $definition['default'] ?? '');

                // For encrypted fields (API keys), show placeholder if set
                if (($definition['type'] ?? 'string') === 'encrypted' && !empty($value)) {
                    $value = '••••••••';
                }

                $this->providerSettings[$identifier][$key] = $value;
            }
        }
    }

    public function getAvailableProvidersProperty(): array
    {
        return $this->registry->getAvailableProviders();
    }

    public function getProviderOptionsProperty(): array
    {
        return collect($this->registry->all())
            ->map(fn($provider) => [
                'value' => $provider->getIdentifier(),
                'label' => $provider->getName(),
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        if (!auth()->user()->hasPermissionTo('visual_editor.manage')) {
            abort(403);
        }

        // Validate that selected provider is registered
        $validProviders = array_keys($this->registry->all());
        $this->validate([
            'provider' => 'required|in:' . implode(',', $validProviders),
            'requestsPerMinute' => 'required|integer|min:1|max:100',
            'requestsPerDay' => 'required|integer|min:1|max:10000',
        ]);

        // Save core settings via CMS Framework
        apUpdateSetting('visual_editor.ai.enabled', $this->enabled);
        apUpdateSetting('visual_editor.ai.provider', $this->provider);

        // Save provider-specific settings dynamically
        foreach ($this->registry->all() as $identifier => $provider) {
            foreach ($provider->getSettingsSchema() as $key => $definition) {
                $value = $this->providerSettings[$identifier][$key] ?? '';
                $settingKey = "visual_editor.ai.{$identifier}.{$key}";

                // Skip encrypted fields if unchanged (placeholder)
                if (($definition['type'] ?? 'string') === 'encrypted' && $value === '••••••••') {
                    continue;
                }

                apUpdateSetting($settingKey, $value);
            }
        }

        // Save feature toggles
        apUpdateSetting('visual_editor.ai.features.content_suggestions', $this->contentSuggestions);
        apUpdateSetting('visual_editor.ai.features.alt_text', $this->altText);
        apUpdateSetting('visual_editor.ai.features.layout_suggestions', $this->layoutSuggestions);
        apUpdateSetting('visual_editor.ai.features.seo_suggestions', $this->seoSuggestions);
        apUpdateSetting('visual_editor.ai.rate_limits.requests_per_minute', $this->requestsPerMinute);
        apUpdateSetting('visual_editor.ai.rate_limits.requests_per_day', $this->requestsPerDay);

        $this->dispatch('toast', message: __('AI settings saved successfully'));
    }

    public function testConnection(): void
    {
        try {
            $assistant = app(AIAssistant::class);
            $assistant->improveText('Test connection', 'brief');
            $this->dispatch('toast', message: __('Connection successful!'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: __('Connection failed: :error', ['error' => $e->getMessage()]), type: 'error');
        }
    }
}
```

### AI Settings Admin View

The view dynamically renders settings fields for all registered providers:

```blade
<x-artisanpack-card>
    <x-slot:header>
        <h2 class="text-lg font-semibold">{{ __('AI Assistant Settings') }}</h2>
    </x-slot:header>

    <form wire:submit="save" class="space-y-6">
        {{-- Enable/Disable --}}
        <x-artisanpack-toggle
            wire:model="enabled"
            :label="__('Enable AI Assistant')"
            :hint="__('Allow AI-powered features in the visual editor')"
        />

        @if($enabled)
            {{-- Provider Selection (dynamically populated from registry) --}}
            <x-artisanpack-select
                wire:model.live="provider"
                :label="__('AI Provider')"
                :options="$this->providerOptions"
            />

            {{-- Dynamic Provider Settings --}}
            @foreach($this->availableProviders as $providerInfo)
                @if($provider === $providerInfo['identifier'])
                    <div class="p-4 bg-base-200 rounded-lg space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium">{{ $providerInfo['name'] }} {{ __('Configuration') }}</h3>
                            @if($providerInfo['configured'])
                                <x-artisanpack-badge color="success">{{ __('Configured') }}</x-artisanpack-badge>
                            @else
                                <x-artisanpack-badge color="warning">{{ __('Not Configured') }}</x-artisanpack-badge>
                            @endif
                        </div>

                        {{-- Render fields from provider's settings schema --}}
                        @php
                            $providerInstance = app(AIProviderRegistry::class)->get($providerInfo['identifier']);
                            $schema = $providerInstance->getSettingsSchema();
                        @endphp

                        @foreach($schema as $fieldKey => $fieldDef)
                            @php
                                $fieldType = $fieldDef['type'] ?? 'string';
                                $wireModel = "providerSettings.{$providerInfo['identifier']}.{$fieldKey}";
                            @endphp

                            @if($fieldType === 'encrypted')
                                <x-artisanpack-input
                                    wire:model="{{ $wireModel }}"
                                    type="password"
                                    :label="$fieldDef['label'] ?? $fieldKey"
                                    :hint="$fieldDef['hint'] ?? null"
                                />
                            @elseif($fieldKey === 'model' && !empty($providerInfo['models']))
                                <x-artisanpack-select
                                    wire:model="{{ $wireModel }}"
                                    :label="$fieldDef['label'] ?? __('Model')"
                                    :options="collect($providerInfo['models'])->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
                                />
                            @else
                                <x-artisanpack-input
                                    wire:model="{{ $wireModel }}"
                                    :label="$fieldDef['label'] ?? $fieldKey"
                                    :hint="$fieldDef['hint'] ?? null"
                                />
                            @endif
                        @endforeach
                    </div>
                @endif
            @endforeach

            {{-- Feature Toggles --}}
            <div class="space-y-3">
                <h3 class="font-medium">{{ __('Features') }}</h3>

                <x-artisanpack-toggle
                    wire:model="contentSuggestions"
                    :label="__('Content Suggestions')"
                    :hint="__('AI-powered headline and text improvements')"
                />

                <x-artisanpack-toggle
                    wire:model="altText"
                    :label="__('Alt Text Generation')"
                    :hint="__('Auto-generate image descriptions')"
                />

                <x-artisanpack-toggle
                    wire:model="layoutSuggestions"
                    :label="__('Layout Suggestions')"
                    :hint="__('AI-suggested page sections')"
                />

                <x-artisanpack-toggle
                    wire:model="seoSuggestions"
                    :label="__('SEO Suggestions')"
                    :hint="__('AI-powered SEO analysis')"
                />
            </div>

            {{-- Rate Limits --}}
            <div class="grid grid-cols-2 gap-4">
                <x-artisanpack-input
                    wire:model="requestsPerMinute"
                    type="number"
                    :label="__('Requests per Minute')"
                    min="1"
                    max="100"
                />

                <x-artisanpack-input
                    wire:model="requestsPerDay"
                    type="number"
                    :label="__('Requests per Day')"
                    min="1"
                    max="10000"
                />
            </div>
        @endif

        <div class="flex gap-3">
            <x-artisanpack-button type="submit" color="primary">
                {{ __('Save Settings') }}
            </x-artisanpack-button>

            @if($enabled)
                <x-artisanpack-button type="button" wire:click="testConnection">
                    {{ __('Test Connection') }}
                </x-artisanpack-button>
            @endif
        </div>
    </form>
</x-artisanpack-card>
```

### AI Settings Summary

#### Core Settings

| Setting | Key | Type | Admin Editable |
|---------|-----|------|----------------|
| Enabled | `visual_editor.ai.enabled` | boolean | Yes |
| Provider | `visual_editor.ai.provider` | string | Yes |
| Content Suggestions | `visual_editor.ai.features.content_suggestions` | boolean | Yes |
| Alt Text | `visual_editor.ai.features.alt_text` | boolean | Yes |
| Layout Suggestions | `visual_editor.ai.features.layout_suggestions` | boolean | Yes |
| SEO Suggestions | `visual_editor.ai.features.seo_suggestions` | boolean | Yes |
| Requests/Minute | `visual_editor.ai.rate_limits.requests_per_minute` | integer | Yes |
| Requests/Day | `visual_editor.ai.rate_limits.requests_per_day` | integer | Yes |

#### Default Provider Settings

| Setting | Key | Type | Admin Editable |
|---------|-----|------|----------------|
| OpenAI API Key | `visual_editor.ai.openai.api_key` | string (encrypted) | Yes |
| OpenAI Model | `visual_editor.ai.openai.model` | string | Yes |
| Anthropic API Key | `visual_editor.ai.anthropic.api_key` | string (encrypted) | Yes |
| Anthropic Model | `visual_editor.ai.anthropic.model` | string | Yes |

#### Custom Provider Settings

Custom providers register their own settings using the `getSettingsSchema()` method. Settings follow the pattern:

```
visual_editor.ai.{provider_identifier}.{setting_key}
```

For example, the Gemini provider registers:
- `visual_editor.ai.gemini.api_key` (encrypted)
- `visual_editor.ai.gemini.model` (string)

### AI Provider Extensibility Hooks

| Hook | Type | Description |
|------|------|-------------|
| `ap.visualEditor.aiProvidersRegister` | Filter | Add custom AI providers to the registry |
| `ap.visualEditor.aiProviderRegistered` | Action | Fired when a provider is registered |
| `ap.visualEditor.aiProviderUnregistered` | Action | Fired when a provider is unregistered |

#### Registering a Custom Provider

```php
// In your service provider boot() method
addFilter('ap.visualEditor.aiProvidersRegister', function (array $providers) {
    $providers['gemini'] = new GeminiProvider();
    return $providers;
});
```

See the `GeminiProvider` example above for a complete implementation reference

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
