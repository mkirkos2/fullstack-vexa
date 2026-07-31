<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Services\AI\GroqAiProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, function ($app) {
            $provider = config('ai.default');

            return match ($provider) {
                'groq' => new GroqAiProvider,
                default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
