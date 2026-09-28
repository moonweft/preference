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
        'text' => ['label' => '文本生成', 'config' => 'default', 'contract' => TextProvider::class],
        'image' => ['label' => '图像生成', 'config' => 'default_for_images', 'contract' => ImageProvider::class],
        'audio' => ['label' => '语音合成', 'config' => 'default_for_audio', 'contract' => AudioProvider::class],
        'transcription' => ['label' => '语音转文字', 'config' => 'default_for_transcription', 'contract' => TranscriptionProvider::class],
        'embeddings' => ['label' => '向量嵌入', 'config' => 'default_for_embeddings', 'contract' => EmbeddingProvider::class],
        'reranking' => ['label' => '搜索重排序', 'config' => 'default_for_reranking', 'contract' => RerankingProvider::class],
    ];

    private const DRIVERS = [
        'openai' => ['OpenAI', Providers\OpenAiProvider::class],
        'anthropic' => ['Anthropic', Providers\AnthropicProvider::class],
        'gemini' => ['Gemini', Providers\GeminiProvider::class],
        'deepseek' => ['DeepSeek', Providers\DeepSeekProvider::class],
        'openai-compatible' => ['OpenAI 兼容接口', Providers\OpenAiCompatibleProvider::class],
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

            $options[$name] = self::DRIVERS[$driver][0].($name === $driver ? '' : " ({$name})");
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
        $fields = ['key' => 'API 密钥'];

        if ($this->driver($name) === 'bedrock') {
            $fields['key'] = 'Bearer Token';
            $fields['region'] = 'AWS 区域';
        } else {
            $fields['url'] = '接口地址';
        }

        if ($this->driver($name) === 'azure') {
            $fields['api_version'] = 'API 版本';
        }

        foreach (self::CAPABILITIES as $capability => $definition) {
            if ($this->supports($name, $capability)) {
                $fields[$this->modelPath($name, $capability)] = $definition['label'].($this->driver($name) === 'azure' ? '部署名称' : '模型');
            }
        }

        if ($this->supports($name, 'embeddings')) {
            $fields['models.embeddings.dimensions'] = '向量维度';
        }

        return $fields;
    }
}
