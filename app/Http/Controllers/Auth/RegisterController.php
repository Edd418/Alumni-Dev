<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\RegisterUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Show the registration view.
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterUserRequest $request)
    {
        // Retrieve the validated data from your Form Request
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Log the user in immediately after registering
        Auth::login($user);

        // Redirect them to the dashboard
        return redirect()->route('dashboard');
    }
}
