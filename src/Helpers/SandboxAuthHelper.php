<?php

namespace Cyclechain\Sandboxer\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Cyclechain\Sandboxer\Facades\Sandboxer;

class SandboxAuthHelper
{
    /**
     * Handle sandbox login if applicable.
     *
     * @param Request $request
     * @param string|null $redirectPath
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public static function handleSandboxLogin(Request $request, ?string $redirectPath = '/home')
    {
        // Check if sandbox is active
        if (!Sandboxer::isActive()) {
            return null;
        }

        $demoEmail = config('sandboxer.demo_credentials.email');
        $demoPassword = config('sandboxer.demo_credentials.password');

        // Check if credentials match
        if ($request->email === $demoEmail && $request->password === $demoPassword) {
            // Find the demo user
            $userClass = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userClass::where('email', $demoEmail)->first();

            if ($user) {
                // Log the user in
                Auth::login($user);

                return Redirect::intended($redirectPath);
            }
        }

        return null;
    }
}
