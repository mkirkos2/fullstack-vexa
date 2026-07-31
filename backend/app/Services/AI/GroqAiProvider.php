<?php

namespace App\Services\AI;

use App\Contracts\AiProvider;
use App\Data\AiResponse;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiConfigurationException;
use App\Exceptions\AI\AiConnectionException;
use App\Exceptions\AI\AiMalformedResponseException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GroqAiProvider implements AiProvider
{
    public function generateReply(array $messages): AiResponse
    {
        // Validate configuration
        $apiKey = config('ai.providers.groq.api_key');
        $baseUrl = config('ai.providers.groq.base_url');
        $model = config('ai.providers.groq.model');

        if (empty($apiKey)) {
            throw new AiConfigurationException('Groq API key is not configured.');
        }

        if (empty($baseUrl)) {
            throw new AiConfigurationException('Groq base URL is not configured.');
        }

        if (empty($model)) {
            throw new AiConfigurationException('Groq model is not configured.');
        }

        // Validate and normalize input messages
        $normalizedMessages = [];
        foreach ($messages as $message) {
            if (! isset($message['role']) || ! isset($message['content'])) {
                throw new InvalidArgumentException('Each message must have a role and content.');
            }

            if (! in_array($message['role'], ['user', 'assistant', 'system'])) {
                throw new InvalidArgumentException("Unsupported message role: {$message['role']}");
            }

            $content = trim($message['content']);
            if ($content === '') {
                throw new InvalidArgumentException('Message content cannot be empty or whitespace-only.');
            }

            $normalizedMessages[] = [
                'role' => $message['role'],
                'content' => $content,
            ];
        }

        // Prepare the request
        $payload = [
            'model' => $model,
            'messages' => $normalizedMessages,
            'temperature' => config('ai.temperature'),
            'max_tokens' => config('ai.max_tokens'),
        ];

        // Make the HTTP request
        $response = Http::asJson()
            ->withHeader('Authorization', "Bearer {$apiKey}")
            ->timeout(config('ai.timeout'))
            ->connectTimeout(config('ai.connect_timeout'))
            ->post("{$baseUrl}/chat/completions", $payload);

        // Handle response
        if ($response->failed()) {
            $statusCode = $response->status();

            // Map specific HTTP status codes to exceptions
            switch ($statusCode) {
                case 401:
                case 403:
                    throw new AiAuthenticationException('Authentication failed with Groq API.');
                case 429:
                    throw new AiRateLimitException('Rate limit exceeded with Groq API.');
                case 500:
                case 502:
                case 503:
                case 504:
                    throw new AiProviderException('Groq API server error.');
                default:
                    throw new AiProviderException("Groq API request failed with status code {$statusCode}.");
            }
        }

        // Check for network-level issues
        if ($response->clientError() || $response->serverError()) {
            throw new AiConnectionException('Connection error with Groq API.');
        }

        // Parse the response
        $responseData = $response->json();

        // Validate response structure
        if (! isset($responseData['choices'][0]['message']['content'])) {
            throw new AiMalformedResponseException('Malformed response from Groq API: missing message content.');
        }

        $choice = $responseData['choices'][0];
        $usage = $responseData['usage'] ?? [];

        return new AiResponse(
            content: $choice['message']['content'],
            provider: 'groq',
            model: $responseData['model'],
            finishReason: $choice['finish_reason'] ?? null,
            promptTokens: $usage['prompt_tokens'] ?? null,
            completionTokens: $usage['completion_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null,
        );
    }
}
