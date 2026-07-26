<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can have multiple conversations', function () {
    $user = User::factory()->create();
    Conversation::factory()->for($user)->create();
    Conversation::factory()->for($user)->create();

    expect($user->conversations)->toHaveCount(2);
});

test('a conversation belongs to its user', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();

    expect($conversation->user->id)->toBe($user->id);
});

test('a conversation can have multiple messages', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->for($conversation)->create();
    Message::factory()->for($conversation)->create();

    expect($conversation->messages)->toHaveCount(2);
});

test('a message belongs to its conversation', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();

    expect($message->conversation->id)->toBe($conversation->id);
});

test('deleting a conversation deletes its messages', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();

    $conversation->delete();

    expect(Message::count())->toBe(0);
});

test('deleting a user deletes that users conversations and their messages', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create();
    $message = Message::factory()->for($conversation)->create();

    $user->delete();

    expect(Conversation::count())->toBe(0);
    expect(Message::count())->toBe(0);
});

test('different users conversations remain isolated at the relationship data level', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation1 = Conversation::factory()->for($user1)->create();
    $conversation2 = Conversation::factory()->for($user2)->create();

    expect($user1->conversations)->toHaveCount(1);
    expect($user1->conversations->first()->id)->toBe($conversation1->id);
    expect($user2->conversations)->toHaveCount(1);
    expect($user2->conversations->first()->id)->toBe($conversation2->id);
});

test('message roles can store user assistant system', function () {
    $conversation = Conversation::factory()->create();

    $userMessage = Message::factory()->for($conversation)->user()->create();
    $assistantMessage = Message::factory()->for($conversation)->assistant()->create();
    $systemMessage = Message::factory()->for($conversation)->system()->create();

    expect($userMessage->role)->toBe('user');
    expect($assistantMessage->role)->toBe('assistant');
    expect($systemMessage->role)->toBe('system');
});

test('long text content can be persisted', function () {
    $conversation = Conversation::factory()->create();
    $longContent = str_repeat('This is a long message content. ', 1000); // 30,000+ characters

    $message = Message::factory()->for($conversation)->create([
        'content' => $longContent,
    ]);

    expect($message->content)->toBe($longContent);
});

test('conversations can have a null title', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->for($user)->create([
        'title' => null,
    ]);

    expect($conversation->title)->toBeNull();
});
