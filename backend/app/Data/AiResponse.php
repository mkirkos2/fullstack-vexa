<?php

namespace App\Data;

readonly class AiResponse
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public ?string $finishReason,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $totalTokens,
    ) {}
}
