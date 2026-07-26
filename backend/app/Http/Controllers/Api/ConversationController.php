<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $conversations = $request->user()
            ->conversations()
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return ConversationResource::collection($conversations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConversationRequest $request)
    {
        $conversation = $request->user()
            ->conversations()
            ->create($request->validated());

        return new ConversationResource($conversation);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string|int $conversation)
    {
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        return new ConversationResource($conversation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationRequest $request, string|int $conversation)
    {
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        $conversation->update($request->validated());

        return new ConversationResource($conversation);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string|int $conversation)
    {
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversation);

        $conversation->delete();

        return response()->noContent();
    }
}
