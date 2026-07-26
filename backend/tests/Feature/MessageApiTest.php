<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// AUTHENTICATION TESTS

test('an unauthenticated user cannot list messages', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(401);
});

test('an unauthenticated user cannot create a message', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(401);
});

// OWNERSHIP TESTS

test('a user can list messages from their own conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $message = Message::factory()->for($conversation)->user()->create(['content' => 'Test message']);

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJson([
        'data' => [
            [
                'id' => $message->id,
                'role' => 'user',
                'content' => 'Test message',
            ],
        ],
    ]);
});

test('a user receives 404 when listing messages from another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();
    Message::factory()->for($conversation)->user()->create();

    $response = $this->actingAs($user1, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(404);
});

test('a user receives 404 when creating a message in another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();

    $response = $this->actingAs($user1, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(404);
});

test('foreign-owner failures do not create or modify messages', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();
    $initialMessageCount = Message::count();

    $response = $this->actingAs($user1, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(404);
    expect(Message::count())->toBe($initialMessageCount);
});

test('foreign-owner failures do not update the conversation timestamp', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create(['updated_at' => now()->subHour()]);
    $originalUpdatedAt = $conversation->updated_at->timestamp;

    $response = $this->actingAs($user1, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(404);
    $conversation->refresh();
    expect($conversation->updated_at->timestamp)->toBe($originalUpdatedAt);
});

// INDEX TESTS

test('index returns only messages belonging to the requested owned conversation', function () {
    $user = User::factory()->create();
    $conversation1 = Conversation::factory()->for($user)->create();
    $conversation2 = Conversation::factory()->for($user)->create();
    $message1 = Message::factory()->for($conversation1)->user()->create(['content' => 'Message in conversation 1']);
    $message2 = Message::factory()->for($conversation2)->user()->create(['content' => 'Message in conversation 2']);

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation1->id}/messages");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJson([
        'data' => [
            [
                'id' => $message1->id,
                'content' => 'Message in conversation 1',
            ],
        ],
    ]);
    $responseData = $response->json()['data'];
    expect($responseData[0]['id'])->not->toBe($message2->id);
});

test('messages are ordered by created_at ascending', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $message1 = Message::factory()->for($conversation)->user()->create([
        'content' => 'First message',
        'created_at' => now()->subMinutes(2),
    ]);
    $message2 = Message::factory()->for($conversation)->user()->create([
        'content' => 'Second message',
        'created_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $responseData = $response->json()['data'];
    expect($responseData[0]['id'])->toBe($message1->id);
    expect($responseData[1]['id'])->toBe($message2->id);
});

test('equal created_at values use id ascending', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    // Create messages with the same timestamps
    $message1 = Message::factory()->for($conversation)->user()->create([
        'content' => 'First message',
        'created_at' => now(),
    ]);
    $message2 = Message::factory()->for($conversation)->user()->create([
        'content' => 'Second message',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $responseData = $response->json()['data'];
    // Since message1 was created before message2, it should come first
    expect($responseData[0]['id'])->toBe($message1->id);
    expect($responseData[1]['id'])->toBe($message2->id);
});

test('resource does not expose conversation_id', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    Message::factory()->for($conversation)->user()->create();

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $responseData = $response->json()['data'][0];
    expect($responseData)->not->toHaveKey('conversation_id');
});

test('resource does not expose user information', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    Message::factory()->for($conversation)->user()->create();

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $responseData = $response->json()['data'][0];
    expect($responseData)->not->toHaveKey('user_id');
    expect($responseData)->not->toHaveKey('user');
});

test('empty conversation returns an empty data array', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}/messages");

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [],
    ]);
});

// STORE TESTS

test('an authenticated user can create a message in their own conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'data' => [
            'role' => 'user',
            'content' => 'Test message content',
        ],
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Test message content',
    ]);
});

test('store returns http 201', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
});

test('stored role is always user', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
        'role' => 'assistant', // This should be ignored
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    expect($responseData['role'])->toBe('user');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
    ]);
});

test('created message belongs to the route conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    $messageId = $responseData['id'];

    $this->assertDatabaseHas('messages', [
        'id' => $messageId,
        'conversation_id' => $conversation->id,
    ]);
});

test('request-provided role assistant cannot create an assistant message', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
        'role' => 'assistant',
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    expect($responseData['role'])->toBe('user'); // Should still be user

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
    ]);
    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});

test('request-provided role system cannot create a system message', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
        'role' => 'system',
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    expect($responseData['role'])->toBe('user'); // Should still be user

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
    ]);
    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'system',
    ]);
});

test('request-provided conversation_id cannot place the message in another conversation', function () {
    $user = User::factory()->create();
    $conversation1 = Conversation::factory()->for($user)->create();
    $conversation2 = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation1->id}/messages", [
        'content' => 'Test message content',
        'conversation_id' => $conversation2->id, // This should be ignored
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    $messageId = $responseData['id'];

    // The message should be in conversation1, not conversation2
    $this->assertDatabaseHas('messages', [
        'id' => $messageId,
        'conversation_id' => $conversation1->id,
    ]);
    $this->assertDatabaseMissing('messages', [
        'id' => $messageId,
        'conversation_id' => $conversation2->id,
    ]);
});

test('request-provided user_id has no ownership effect', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user1)->create();

    $response = $this->actingAs($user1, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
        'user_id' => $user2->id, // This should be ignored
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    $messageId = $responseData['id'];

    // The message should still be associated with conversation, not directly with user2
    $message = Message::find($messageId);
    expect($message->conversation->user_id)->toBe($user1->id);
    expect($message->conversation->user_id)->not->toBe($user2->id);
});

test('missing content returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('empty content returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('whitespace-only content returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => '   ',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('non-string content returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 123,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('content longer than 50000 characters returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $longContent = str_repeat('a', 50001);

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => $longContent,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);
});

test('valid long content within the limit persists correctly', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $longContent = str_repeat('a', 50000);

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => $longContent,
    ]);

    $response->assertStatus(201);
    $responseData = $response->json()['data'];
    expect($responseData['content'])->toBe($longContent);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'content' => $longContent,
    ]);
});

test('successful creation updates the conversation updated_at timestamp', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create(['updated_at' => now()->subHour()]);
    $originalUpdatedAt = $conversation->updated_at;

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
    $conversation->refresh();
    expect($conversation->updated_at)->toBeGreaterThan($originalUpdatedAt);
});

test('successful creation does not update unrelated conversations', function () {
    $user = User::factory()->create();
    $conversation1 = Conversation::factory()->for($user)->create(['updated_at' => now()->subHour()]);
    $conversation2 = Conversation::factory()->for($user)->create(['updated_at' => now()->subHour()]);
    $originalUpdatedAt1 = $conversation1->updated_at->timestamp;
    $originalUpdatedAt2 = $conversation2->updated_at->timestamp;

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation1->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
    $conversation1->refresh();
    $conversation2->refresh();
    expect($conversation1->updated_at->timestamp)->toBeGreaterThan($originalUpdatedAt1);
    expect($conversation2->updated_at->timestamp)->toBe($originalUpdatedAt2);
});

test('exactly one message is created per successful request', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $initialMessageCount = Message::count();

    $response = $this->actingAs($user, 'web')->postJson("/api/conversations/{$conversation->id}/messages", [
        'content' => 'Test message content',
    ]);

    $response->assertStatus(201);
    expect(Message::count())->toBe($initialMessageCount + 1);
});
