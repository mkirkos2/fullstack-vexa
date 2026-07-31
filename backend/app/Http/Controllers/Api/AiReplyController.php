<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AiProvider;
use App\Data\AiResponse;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiConfigurationException;
use App\Exceptions\AI\AiConnectionException;
use App\Exceptions\AI\AiMalformedResponseException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiReplyController extends Controller
{
    public function __construct(private readonly AiProvider $aiProvider)
    {
    }

    /**
     * Generate and persist an AI assistant reply for an owned conversation.
     */
    public function __invoke(Request $request, int|string $conversation)
    {
        // Resolve the conversation only through the authenticated user's conversations
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        // Load messages in chronological order
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Normalize messages for the AI provider
        $normalizedMessages = $messages->map(fn ($message) => [
            'role' => $message->role,
            'content' => $message->content,
        ])->all();

        // Precondition checks
        if (empty($normalizedMessages)) {
            return response()->json([
                'message' => 'Add a message before requesting an AI reply.',
            ], 422);
        }

        $latestMessage = $messages->last();
        if ($latestMessage->role === 'assistant') {
            return response()->json([
                'message' => 'The conversation is already waiting for a new user message.',
            ], 422);
        }

        try {
            // Generate AI response outside of database transaction
            $aiResponse = $this->aiProvider->generateReply($normalizedMessages);

            // Validate that the returned content is non-empty
            if (trim($aiResponse->content) === '') {
                throw new AiMalformedResponseException('AI response content is empty.');
            }

            // Persist the assistant message inside a database transaction
            $assistantMessage = DB::transaction(function () use ($conversation, $latestMessage, $aiResponse) {
                // Re-check that the latest message is still the same
                $currentLatestMessage = $conversation->messages()
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($currentLatestMessage?->id !== $latestMessage->id) {
                    // The conversation has changed while the AI request was pending
                    return null;
                }

                // Create the assistant message
                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $aiResponse->content,
                ]);

                // Touch the conversation
                $conversation->touch();

                return $assistantMessage;
            });

            // Handle stale response case
            if ($assistantMessage === null) {
                return response()->json([
                    'message' => 'The conversation has changed. Please try again.',
                ], 409);
            }

            return new MessageResource($assistantMessage);
        } catch (AiConfigurationException $e) {
            return response()->json([
                'message' => 'AI service is not configured.',
            ], 503);
        } catch (AiAuthenticationException $e) {
            return response()->json([
                'message' => 'AI service authentication failed.',
            ], 502);
        } catch (AiRateLimitException $e) {
            return response()->json([
                'message' => 'AI service rate limit reached. Please try again shortly.',
            ], 429);
        } catch (AiConnectionException $e) {
            return response()->json([
                'message' => 'AI service is temporarily unavailable.',
            ], 503);
        } catch (AiMalformedResponseException $e) {
            return response()->json([
                'message' => 'AI service returned an invalid response.',
            ], 502);
        } catch (AiProviderException|InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Unable to generate an AI response.',
            ], 502);
        }
    }
}