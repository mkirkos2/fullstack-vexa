<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string|int $conversation)
    {
        // Resolve the conversation only through the authenticated user's conversations
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        // Retrieve messages belonging to the owned conversation
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return MessageResource::collection($messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request, string|int $conversation)
    {
        // Resolve the conversation only through the authenticated user's conversations
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        // Create the message and touch the conversation inside a database transaction
        $message = DB::transaction(function () use ($conversation, $request) {
            $message = $conversation->messages()->create([
                'role' => 'user',
                'content' => $request->validated()['content'],
            ]);

            // Update the owning conversation's updated_at timestamp
            $conversation->touch();

            return $message;
        });

        return new MessageResource($message);
    }
}
