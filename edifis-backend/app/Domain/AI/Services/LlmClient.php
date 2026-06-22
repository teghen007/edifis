<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Provider-agnostic chat client. Supports OpenAI-compatible APIs
 * (DeepSeek, GLM/Zhipu, OpenAI) and Anthropic. Configured via services.ai.
 *
 * The caller is responsible for ALL data scoping — this class only relays
 * the already-scoped messages to the provider. It never queries the database.
 */
class LlmClient
{
    public function configured(): bool
    {
        return !empty(config('services.ai.api_key'));
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     */
    public function chat(array $messages, float $temperature = 0.3, int $maxTokens = 700): string
    {
        if (!$this->configured()) {
            throw new RuntimeException('AI is not configured. Set AI_API_KEY.');
        }

        $provider = config('services.ai.provider', 'deepseek');

        return $provider === 'anthropic'
            ? $this->anthropic($messages, $temperature, $maxTokens)
            : $this->openAiCompatible($messages, $temperature, $maxTokens);
    }

    /** DeepSeek / GLM / OpenAI all speak POST {base}/chat/completions. */
    private function openAiCompatible(array $messages, float $temperature, int $maxTokens): string
    {
        $base = rtrim((string) config('services.ai.base_url'), '/');

        $response = Http::withToken((string) config('services.ai.api_key'))
            ->timeout((int) config('services.ai.timeout', 30))
            ->acceptJson()
            ->post("{$base}/chat/completions", [
                'model' => config('services.ai.model'),
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stream' => false,
            ]);

        if (!$response->successful()) {
            Log::warning('LLM call failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('The assistant is unavailable right now. Please try again shortly.');
        }

        return trim((string) data_get($response->json(), 'choices.0.message.content', ''));
    }

    /** Anthropic Messages API — system prompt is a top-level field. */
    private function anthropic(array $messages, float $temperature, int $maxTokens): string
    {
        $base = rtrim((string) config('services.ai.base_url'), '/');

        $system = collect($messages)->firstWhere('role', 'system')['content'] ?? '';
        $turns = collect($messages)
            ->filter(fn ($m) => $m['role'] !== 'system')
            ->map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']])
            ->values()
            ->all();

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.ai.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout((int) config('services.ai.timeout', 30))
            ->acceptJson()
            ->post("{$base}/v1/messages", [
                'model' => config('services.ai.model'),
                'system' => $system,
                'messages' => $turns,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if (!$response->successful()) {
            Log::warning('LLM (anthropic) call failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('The assistant is unavailable right now. Please try again shortly.');
        }

        return trim((string) data_get($response->json(), 'content.0.text', ''));
    }
}
