<?php

namespace App\Services;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;

class AiProviderService
{
    public function getActiveProvider(): ?AiSetting
    {
        return AiSetting::where('is_active', true)->first();
    }

    /**
     * Return the AiSetting for a specific provider name ('claude' or 'openai').
     * Returns null if that provider hasn't been configured yet.
     */
    public function getProviderByName(string $provider): ?AiSetting
    {
        return AiSetting::where('provider', $provider)->first();
    }

    public function complete(string $prompt, ?AiSetting $setting = null, array $options = []): array
    {
        $setting ??= $this->getActiveProvider();

        if (!$setting) {
            throw new \RuntimeException('No active AI provider configured');
        }

        // Guard: if the model name is incompatible with the resolved provider, use the
        // provider's own default (e.g. step saved with claude-sonnet-4-6 but routed to OpenAI)
        if (isset($options['model']) && $options['model'] !== '') {
            $model = $options['model'];
            $incompatible = match ($setting->provider) {
                'openai' => str_starts_with($model, 'claude'),
                'claude' => str_starts_with($model, 'gpt-') || str_starts_with($model, 'o1') || str_starts_with($model, 'o3'),
                default  => false,
            };
            if ($incompatible) {
                $options['model'] = $setting->default_model;
            }
        }

        return match ($setting->provider) {
            'claude' => $this->callClaude($prompt, $setting, $options),
            'openai' => $this->callOpenAI($prompt, $setting, $options),
            default => throw new \RuntimeException("Unsupported provider: {$setting->provider}"),
        };
    }

    private function callClaude(string $prompt, AiSetting $setting, array $options): array
    {
        $startTime = microtime(true);

        $response = Http::timeout(300)->withHeaders([
            'x-api-key' => $setting->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $options['model'] ?? $setting->default_model,
            'max_tokens' => $options['max_tokens'] ?? $setting->max_tokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            $this->throwApiError('Anthropic', $response->status(), $response->body());
        }

        $data = $response->json();

        // Anthropic occasionally wraps errors in a 200 body
        if (isset($data['type']) && $data['type'] === 'error') {
            $this->throwApiError('Anthropic', $response->status(), $response->body());
        }

        return [
            'content'     => $data['content'][0]['text'] ?? '',
            'model'       => $data['model'] ?? $setting->default_model,
            'tokens_used' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            'duration_ms' => $durationMs,
            'provider'    => 'claude',
        ];
    }

    private function callOpenAI(string $prompt, AiSetting $setting, array $options): array
    {
        $startTime = microtime(true);

        $messages = [];
        if (!empty($options['system_prompt'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system_prompt']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = Http::timeout(300)->withHeaders([
            'Authorization' => 'Bearer ' . $setting->api_key,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $options['model'] ?? $setting->default_model,
            'max_tokens' => $options['max_tokens'] ?? $setting->max_tokens,
            'temperature' => $options['temperature'] ?? $setting->temperature,
            'messages' => $messages,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            $this->throwApiError('OpenAI', $response->status(), $response->body());
        }

        $data = $response->json();

        // OpenAI occasionally returns an error object inside a 200 response
        if (isset($data['error'])) {
            $this->throwApiError('OpenAI', $response->status(), $response->body());
        }

        return [
            'content'     => $data['choices'][0]['message']['content'] ?? '',
            'model'       => $data['model'] ?? $setting->default_model,
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'duration_ms' => $durationMs,
            'provider'    => 'openai',
        ];
    }

    public function test(AiSetting $setting): array
    {
        try {
            $result = $this->complete('Say "Hello" and nothing else.', $setting);
            return ['success' => true, 'message' => 'Connection successful', 'response' => $result['content']];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Parse the API error body, extract a clean message, and throw.
     * Converts raw JSON responses (e.g. OpenAI quota errors) into readable text.
     */
    private function throwApiError(string $provider, int $status, string $body): never
    {
        $data    = @json_decode($body, true);
        $message = $data['error']['message']
            ?? $data['error']['type']
            ?? $data['message']
            ?? null;

        if ($message) {
            $code = strtolower($data['error']['code'] ?? $data['error']['type'] ?? '');
            $msg  = strtolower($message);

            if ($code === 'insufficient_quota'
                || str_contains($msg, 'exceeded your current quota')
                || str_contains($msg, 'credit balance')
                || str_contains($msg, 'billing')
                || str_contains($code, 'billing')
            ) {
                throw new \RuntimeException(
                    "Insufficient {$provider} API credits — please top up your account at "
                    . ($provider === 'OpenAI' ? 'platform.openai.com' : 'console.anthropic.com')
                    . ' and retry.'
                );
            }

            throw new \RuntimeException("{$provider} API error: {$message}");
        }

        // Fallback — body wasn't JSON or had no message field
        $snippet = substr(trim($body), 0, 200);
        throw new \RuntimeException("{$provider} API error (HTTP {$status}): {$snippet}");
    }
}
