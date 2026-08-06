<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{assertDatabaseHas, actingAs, assertAuthenticated, assertAuthenticatedAs, assertGuest};

uses(RefreshDatabase::class);

describe('Web Authentication Logic', function () {

    describe('Registration (POST)', function () {

        it('registers a user with valid data, logs them in, and redirects to dashboard', function () {
            $data = [
                'name' => 'Alumni User',
                'email' => 'alumni@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_selection' => 'alumni',
            ];

            // $this->from() tells Laravel where to redirect back to if it fails
            $this->from('/register')
                ->post('/register', $data)
                ->assertRedirect(route('dashboard'));

            assertAuthenticated();
            assertDatabaseHas('users', ['email' => 'alumni@example.com']);
        });

        it('prevents registration with invalid data and flashes session errors', function () {
            // Submitting an empty array forces all 'required' validation rules to fail
            $this->from('/register')
                ->post('/register', [])
                ->assertSessionHasErrors(['name', 'email', 'password']);

            assertGuest();
        });
    });

    describe('Login (POST)', function () {

        it('authenticates a user with correct credentials and redirects', function () {
            // In Laravel, the default factory creates a user with the password 'password'
            // We use the default so Laravel handles the hashing correctly.
            $user = User::factory()->create();

            $this->from('/login')
                ->post('/login', [
                    'email' => $user->email,
                    'password' => 'password', // Default factory password
                ])
                ->assertRedirect(route('dashboard'));

            assertAuthenticatedAs($user);
        });

        it('rejects login attempts with bad credentials', function () {
            $user = User::factory()->create();

            $this->from('/login')
                ->post('/login', [
                    'email' => $user->email,
                    'password' => 'wrongpassword',
                ])
                ->assertSessionHasErrors('email') // Checks for your custom login error
                ->assertRedirect('/login'); // Verifies it correctly redirected back to the login page

            assertGuest();
        });
    });

    describe('Logout (POST)', function () {

        it('allows an authenticated user to logout and redirects to home', function () {
            $user = User::factory()->create();

            actingAs($user)
                ->post('/logout')
                ->assertRedirect('/');

            assertGuest();
        });

        it('prevents unauthenticated users from accessing the logout route', function () {
            $this->post('/logout')
                ->assertRedirect(route('login'));
        });
    });
});
