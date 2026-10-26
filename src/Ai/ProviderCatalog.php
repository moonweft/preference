<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\RerankingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Providers;

final class ProviderCatalog
{
    public const CAPABILITIES = [
        'text' => ['config' => 'default', 'contract' => TextProvider::class],
        'image' => ['config' => 'default_for_images', 'contract' => ImageProvider::class],
        'audio' => ['config' => 'default_for_audio', 'contract' => AudioProvider::class],
        'transcription' => ['config' => 'default_for_transcription', 'contract' => TranscriptionProvider::class],
        'embeddings' => ['config' => 'default_for_embeddings', 'contract' => EmbeddingProvider::class],
        'reranking' => ['config' => 'default_for_reranking', 'contract' => RerankingProvider::class],
    ];

    private const DRIVERS = [
        'openai' => ['OpenAI', Providers\OpenAiProvider::class],
        'anthropic' => ['Anthropic', Providers\AnthropicProvider::class],
        'gemini' => ['Gemini', Providers\GeminiProvider::class],
        'deepseek' => ['DeepSeek', Providers\DeepSeekProvider::class],
        'openai-compatible' => ['moonweft-preference::ai.providers.openai_compatible', Providers\OpenAiCompatibleProvider::class],
        'ollama' => ['Ollama', Providers\OllamaProvider::class],
        'openrouter' => ['OpenRouter', Providers\OpenRouterProvider::class],
        'azure' => ['Azure OpenAI', Providers\AzureOpenAiProvider::class],
        'bedrock' => ['Amazon Bedrock', Providers\BedrockProvider::class],
        'cohere' => ['Cohere', Providers\CohereProvider::class],
        'eleven' => ['ElevenLabs', Providers\ElevenLabsProvider::class],
        'groq' => ['Groq', Providers\GroqProvider::class],
        'jina' => ['Jina', Providers\JinaProvider::class],
        'mistral' => ['Mistral', Providers\MistralProvider::class],
        'voyageai' => ['VoyageAI', Providers\VoyageAiProvider::class],
        'xai' => ['xAI', Providers\XaiProvider::class],
    ];

    /** @return array<string, string> */
    public function options(?string $capability = null): array
    {
        $options = [];

        foreach (config('ai.providers', []) as $name => $config) {
            $driver = $config['driver'] ?? '';

            if (! isset(self::DRIVERS[$driver]) || ($capability !== null && ! $this->supports($name, $capability))) {
                continue;
            }

            $options[$name] = __(self::DRIVERS[$driver][0]).($name === $driver ? '' : " ({$name})");
        }

        return $options;
    }

    public function supports(string $name, string $capability): bool
    {
        $class = self::DRIVERS[$this->driver($name)][1] ?? null;
        $contract = self::CAPABILITIES[$capability]['contract'] ?? null;

        return $class !== null && $contract !== null && is_a($class, $contract, true);
    }

    public function driver(string $name): string
    {
        return (string) config("ai.providers.{$name}.driver", '');
    }

    public function modelPath(string $name, string $capability): string
    {
        if ($this->driver($name) === 'azure') {
            return match ($capability) {
                'text' => 'deployment',
                'image' => 'image_deployment',
                'embeddings' => 'embedding_deployment',
            };
        }

        return "models.{$capability}.default";
    }

    /** @return array<string, string> */
    public function fields(string $name): array
    {
        $fields = ['key' => __('moonweft-preference::ai.fields.key')];

        if ($this->driver($name) === 'bedrock') {
            $fields['key'] = __('moonweft-preference::ai.fields.bearer_token');
            $fields['region'] = __('moonweft-preference::ai.fields.region');
        } else {
            $fields['url'] = __('moonweft-preference::ai.fields.url');
        }

        if ($this->driver($name) === 'azure') {
            $fields['api_version'] = __('moonweft-preference::ai.fields.api_version');
        }

        foreach (array_keys(self::CAPABILITIES) as $capability) {
            if ($this->supports($name, $capability)) {
                $fields[$this->modelPath($name, $capability)] = __(
                    $this->driver($name) === 'azure' ? 'moonweft-preference::ai.fields.deployment' : 'moonweft-preference::ai.fields.model',
                    ['capability' => __('moonweft-preference::ai.capabilities.'.$capability)],
                );
            }
        }

        if ($this->supports($name, 'text')) {
            $fields['models.text.simple'] = __('moonweft-preference::ai.fields.simple_model');
            $fields['models.text.complex'] = __('moonweft-preference::ai.fields.complex_model');
        }

        if ($this->driver($name) === 'deepseek') {
            $fields['reasoning_effort'] = __('moonweft-preference::ai.fields.reasoning_effort');
        }

        if ($this->supports($name, 'embeddings')) {
            $fields['models.embeddings.dimensions'] = __('moonweft-preference::ai.fields.dimensions');
        }

        return $fields;
    }
}
