<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can register', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'user' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ],
        ],
        'message',
    ]);
    
    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

test('registration validates required fields', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'john@example.com']);
    
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('password confirmation is required', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'user' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ],
        ],
        'message',
    ]);
    
    $this->assertAuthenticatedAs($user);
});

test('login fails with invalid credentials', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Invalid credentials',
    ]);
});

test('an authenticated user can access api user', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'user' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
});

test('an unauthenticated user cannot access api user', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->postJson('/api/logout');

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'User logged out successfully',
    ]);
    
    $this->assertGuest();
});

test('an unauthenticated user cannot log out', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});
