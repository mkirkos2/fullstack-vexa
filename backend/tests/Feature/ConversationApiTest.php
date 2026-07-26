<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// AUTHENTICATION TESTS

test('an unauthenticated user cannot list conversations', function () {
    $response = $this->getJson('/api/conversations');

    $response->assertStatus(401);
});

test('an unauthenticated user cannot create a conversation', function () {
    $response = $this->postJson('/api/conversations', [
        'title' => 'Test Conversation',
    ]);

    $response->assertStatus(401);
});

test('an unauthenticated user cannot view a conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->getJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(401);
});

test('an unauthenticated user cannot update a conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->patchJson("/api/conversations/{$conversation->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(401);
});

test('an unauthenticated user cannot delete a conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(401);
});

// INDEX TESTS

test('an authenticated user sees only their own conversations', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation1 = Conversation::factory()->for($user1)->create(['title' => 'User 1 Conversation']);
    $conversation2 = Conversation::factory()->for($user2)->create(['title' => 'User 2 Conversation']);

    $response = $this->actingAs($user1, 'web')->getJson('/api/conversations');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJson([
        'data' => [
            [
                'id' => $conversation1->id,
                'title' => 'User 1 Conversation',
            ],
        ],
    ]);

    $responseData = $response->json()['data'];
    $conversationIds = collect($responseData)->pluck('id')->toArray();
    $this->assertNotContains($conversation2->id, $conversationIds);
});

test('conversations are ordered by updated_at descending', function () {
    $user = User::factory()->create();

    $conversation1 = Conversation::factory()->for($user)->create(['title' => 'Old Conversation']);
    sleep(1); // Ensure different timestamps
    $conversation2 = Conversation::factory()->for($user)->create(['title' => 'New Conversation']);

    // Update the first conversation to make it the most recently updated
    sleep(1); // Ensure different timestamps
    $conversation1->update(['title' => 'Updated Old Conversation']);

    $response = $this->actingAs($user, 'web')->getJson('/api/conversations');

    $response->assertStatus(200);
    $responseData = $response->json()['data'];

    // The first conversation should be the most recently updated one
    expect($responseData[0]['id'])->toBe($conversation1->id);
    expect($responseData[0]['title'])->toBe('Updated Old Conversation');

    // The second conversation should be the older one
    expect($responseData[1]['id'])->toBe($conversation2->id);
    expect($responseData[1]['title'])->toBe('New Conversation');
});

test('equal updated_at values use id descending as the secondary order', function () {
    $user = User::factory()->create();

    // Create conversations with the same timestamps
    $conversation1 = Conversation::factory()->for($user)->create([
        'title' => 'Conversation 1',
        'updated_at' => now(),
    ]);

    $conversation2 = Conversation::factory()->for($user)->create([
        'title' => 'Conversation 2',
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user, 'web')->getJson('/api/conversations');

    $response->assertStatus(200);
    // Since conversation2 was created after conversation1, it should come first
    $response->assertJson([
        'data' => [
            [
                'id' => $conversation2->id,
                'title' => 'Conversation 2',
            ],
            [
                'id' => $conversation1->id,
                'title' => 'Conversation 1',
            ],
        ],
    ]);
});

test('the index response does not expose user_id', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->getJson('/api/conversations');

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ],
    ]);

    $responseData = $response->json()['data'];
    expect($responseData[0])->not->toHaveKey('user_id');
});

test('the index response does not include messages', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->getJson('/api/conversations');

    $response->assertStatus(200);
    $responseData = $response->json()['data'];
    expect($responseData[0])->not->toHaveKey('messages');
});

// STORE TESTS

test('an authenticated user can create a conversation with a title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->postJson('/api/conversations', [
        'title' => 'New Conversation',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'data' => [
            'title' => 'New Conversation',
        ],
    ]);

    $this->assertDatabaseHas('conversations', [
        'user_id' => $user->id,
        'title' => 'New Conversation',
    ]);
});

test('an authenticated user can create a conversation with a null or omitted title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->postJson('/api/conversations', []);

    $response->assertStatus(201);
    $response->assertJson([
        'data' => [
            'title' => null,
        ],
    ]);

    $this->assertDatabaseHas('conversations', [
        'user_id' => $user->id,
        'title' => null,
    ]);
});

test('a created conversation is automatically associated with the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->postJson('/api/conversations', [
        'title' => 'Test Conversation',
    ]);

    $response->assertStatus(201);
    $responseData = $response->json();
    $conversationId = $responseData['data']['id'];

    $this->assertDatabaseHas('conversations', [
        'id' => $conversationId,
        'user_id' => $user->id,
    ]);
});

test('a request-supplied user_id cannot assign the conversation to another user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $response = $this->actingAs($user1, 'web')->postJson('/api/conversations', [
        'title' => 'Test Conversation',
        'user_id' => $user2->id,
    ]);

    $response->assertStatus(201);
    $responseData = $response->json();
    $conversationId = $responseData['data']['id'];

    // The conversation should be associated with user1, not user2
    $this->assertDatabaseHas('conversations', [
        'id' => $conversationId,
        'user_id' => $user1->id,
    ]);

    $this->assertDatabaseMissing('conversations', [
        'id' => $conversationId,
        'user_id' => $user2->id,
    ]);
});

test('a title longer than 255 characters returns 422', function () {
    $user = User::factory()->create();
    $longTitle = str_repeat('a', 256);

    $response = $this->actingAs($user, 'web')->postJson('/api/conversations', [
        'title' => $longTitle,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title']);
});

test('store returns http 201', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->postJson('/api/conversations', [
        'title' => 'Test Conversation',
    ]);

    $response->assertStatus(201);
});

// SHOW TESTS

test('an authenticated user can view their own conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create(['title' => 'Test Conversation']);

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'id' => $conversation->id,
            'title' => 'Test Conversation',
        ],
    ]);
});

test('a user receives 404 when viewing another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();

    $response = $this->actingAs($user1, 'web')->getJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(404);
});

test('the response does not expose user_id or messages', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->getJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(200);
    $responseData = $response->json();
    expect($responseData['data'])->not->toHaveKey('user_id');
    expect($responseData['data'])->not->toHaveKey('messages');
});

// UPDATE TESTS

test('an authenticated user can update their own conversation title', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create(['title' => 'Original Title']);

    $response = $this->actingAs($user, 'web')->patchJson("/api/conversations/{$conversation->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'title' => 'Updated Title',
        ],
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $conversation->id,
        'title' => 'Updated Title',
    ]);
});

test('an authenticated user can set their own conversation title to null', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create(['title' => 'Original Title']);

    $response = $this->actingAs($user, 'web')->patchJson("/api/conversations/{$conversation->id}", [
        'title' => null,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'title' => null,
        ],
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $conversation->id,
        'title' => null,
    ]);
});

test('a user receives 404 when updating another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();

    $response = $this->actingAs($user1, 'web')->patchJson("/api/conversations/{$conversation->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(404);
});

test('a request-supplied user_id cannot transfer ownership', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user1)->create();

    $response = $this->actingAs($user1, 'web')->patchJson("/api/conversations/{$conversation->id}", [
        'title' => 'Updated Title',
        'user_id' => $user2->id,
    ]);

    $response->assertStatus(200);

    // The conversation should still be associated with user1
    $this->assertDatabaseHas('conversations', [
        'id' => $conversation->id,
        'user_id' => $user1->id,
    ]);

    $this->assertDatabaseMissing('conversations', [
        'id' => $conversation->id,
        'user_id' => $user2->id,
    ]);
});

test('an invalid title returns 422', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $longTitle = str_repeat('a', 256);

    $response = $this->actingAs($user, 'web')->patchJson("/api/conversations/{$conversation->id}", [
        'title' => $longTitle,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title']);
});

// DELETE TESTS

test('an authenticated user can delete their own conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
});

test('delete returns http 204 with an empty response', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    $response = $this->actingAs($user, 'web')->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(204);
    $response->assertNoContent();
});

test('deleting a conversation deletes its messages through the database cascade', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $message = Message::factory()->for($conversation)->create();

    $response = $this->actingAs($user, 'web')->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    $this->assertDatabaseMissing('messages', ['id' => $message->id]);
});

test('a user receives 404 when deleting another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();

    $response = $this->actingAs($user1, 'web')->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(404);
    // The conversation should still exist
    $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
});

test('a failed foreign-owner delete leaves the conversation and its messages intact', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->for($user2)->create();
    $message = Message::factory()->for($conversation)->create();

    $response = $this->actingAs($user1, 'web')->deleteJson("/api/conversations/{$conversation->id}");

    $response->assertStatus(404);
    // The conversation and message should still exist
    $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    $this->assertDatabaseHas('messages', ['id' => $message->id]);
});
