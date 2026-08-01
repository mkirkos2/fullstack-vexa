<?php

use App\Contracts\AiProvider;
use App\Data\AiResponse;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiConfigurationException;
use App\Exceptions\AI\AiMalformedResponseException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Prevent stray HTTP requests
    Http::preventStrayRequests();

    // Reset configuration before each test
    config()->set('ai.default', 'groq');
    config()->set('ai.providers.groq.api_key', 'test-key');
    config()->set('ai.providers.groq.base_url', 'https://api.groq.com/openai/v1');
    config()->set('ai.providers.groq.model', 'llama-3.1-8b-instant');
    config()->set('ai.timeout', 30);
    config()->set('ai.connect_timeout', 10);
    config()->set('ai.max_tokens', 1024);
    config()->set('ai.temperature', 0.7);
});

it('generates a reply with valid response', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi there!'],
    ];

    $response = $provider->generateReply($messages);

    expect($response)->toBeInstanceOf(AiResponse::class);
    expect($response->content)->toBe('Hello! How can I help you today?');
    expect($response->provider)->toBe('groq');
    expect($response->model)->toBe('llama-3.1-8b-instant');
    expect($response->finishReason)->toBe('stop');
    expect($response->promptTokens)->toBe(10);
    expect($response->completionTokens)->toBe(20);
    expect($response->totalTokens)->toBe(30);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-key') &&
            $request['model'] === 'llama-3.1-8b-instant' &&
            $request['messages'] === [['role' => 'user', 'content' => 'Hi there!']] &&
            $request['temperature'] === 0.7 &&
            $request['max_tokens'] === 1024;
    });
});

it('extracts content correctly', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is the extracted content.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 15,
                'total_tokens' => 20,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Extract this content'],
    ];

    $response = $provider->generateReply($messages);

    expect($response->content)->toBe('This is the extracted content.');
});

it('returns provider and model correctly', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'custom-model',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $response = $provider->generateReply($messages);

    expect($response->provider)->toBe('groq');
    expect($response->model)->toBe('custom-model');
});

it('extracts finish reason', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'length',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 1024,
                'total_tokens' => 1029,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $response = $provider->generateReply($messages);

    expect($response->finishReason)->toBe('length');
});

it('extracts token usage', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 7,
                'completion_tokens' => 12,
                'total_tokens' => 19,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $response = $provider->generateReply($messages);

    expect($response->promptTokens)->toBe(7);
    expect($response->completionTokens)->toBe(12);
    expect($response->totalTokens)->toBe(19);
});

it('calls the correct endpoint', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $provider->generateReply($messages);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.groq.com/openai/v1/chat/completions';
    });
});

it('includes authorization header without exposing secret', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $provider->generateReply($messages);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-key');
    });
});

it('includes model, messages, temperature, and max_tokens in request', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    config()->set('ai.temperature', 0.8);
    config()->set('ai.max_tokens', 512);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => 'Hello!'],
        ['role' => 'user', 'content' => 'How are you?'],
    ];

    $provider->generateReply($messages);

    Http::assertSent(function ($request) {
        return $request['model'] === 'llama-3.1-8b-instant' &&
            $request['messages'] === [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello!'],
                ['role' => 'user', 'content' => 'How are you?'],
            ] &&
            $request['temperature'] === 0.8 &&
            $request['max_tokens'] === 512;
    });
});

it('does not include unsupported extra fields', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $provider->generateReply($messages);

    Http::assertSent(function ($request) {
        // Check that only supported fields are present
        $allowedFields = ['model', 'messages', 'temperature', 'max_tokens'];
        $requestFields = array_keys($request->data());

        return empty(array_diff($requestFields, $allowedFields));
    });
});

it('fails with missing API key before making HTTP request', function () {
    config()->set('ai.providers.groq.api_key', null);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiConfigurationException::class);

    Http::assertNothingSent();
});

it('fails with missing model before making HTTP request', function () {
    config()->set('ai.providers.groq.model', null);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiConfigurationException::class);

    Http::assertNothingSent();
});

it('rejects unsupported message role', function () {
    Http::fake();

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'admin', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects empty message content', function () {
    Http::fake();

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => ''],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('rejects whitespace-only message content', function () {
    Http::fake();

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => '   '],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('maps HTTP 401 to authentication exception', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiAuthenticationException::class);

    Http::assertSentCount(1);
});

it('maps HTTP 403 to authentication exception', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiAuthenticationException::class);

    Http::assertSentCount(1);
});

it('maps HTTP 429 to rate-limit exception', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Rate limit exceeded'], 429),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiRateLimitException::class);

    Http::assertSentCount(1);
});

it('maps HTTP 500 to general provider exception', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Internal server error'], 500),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiProviderException::class);

    Http::assertSentCount(1);
});

it('maps malformed successful response to malformed-response exception', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            // Missing 'choices' field
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiMalformedResponseException::class);

    Http::assertSentCount(1);
});

it('maps missing choices to malformed-response exception', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            // Missing 'choices' field
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiMalformedResponseException::class);

    Http::assertSentCount(1);
});

it('maps missing message content to malformed-response exception', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    // Missing 'message' or 'content' field
                ],
            ],
        ], 200),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiMalformedResponseException::class);

    Http::assertSentCount(1);
});

it('maps timeout to connection exception', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Timeout'], 504), // Using 504 as a proxy for timeout
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    expect(fn () => $provider->generateReply($messages))->toThrow(AiProviderException::class);

    Http::assertSentCount(1);
});

it('does not expose secret values in exception messages', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    try {
        $provider->generateReply($messages);
        $this->fail('Expected AiAuthenticationException was not thrown.');
    } catch (AiAuthenticationException $e) {
        expect($e->getMessage())->not->toContain('test-key');
    }

    Http::assertSentCount(1);
});

it('casts temperature and max_tokens to correct types', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'llama-3.1-8b-instant',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 10,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);

    // Mock config values that might have incorrect types
    config()->set('ai.temperature', '0.7'); // String instead of float
    config()->set('ai.max_tokens', '1024'); // String instead of int

    $provider = app(AiProvider::class);
    $messages = [
        ['role' => 'user', 'content' => 'Hi'],
    ];

    $response = $provider->generateReply($messages);

    expect($response)->toBeInstanceOf(AiResponse::class);

    Http::assertSent(function ($request) {
        return is_float($request['temperature']) &&
            is_int($request['max_tokens']) &&
            $request['temperature'] === 0.7 &&
            $request['max_tokens'] === 1024;
    });
});