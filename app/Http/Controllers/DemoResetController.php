<?php

namespace App\Http\Controllers;

use App\Actions\ResetDemoData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoResetController extends Controller
{
    public function __invoke(Request $request, ResetDemoData $reset): RedirectResponse
    {
        abort_unless(config('app.demo_mode'), 404);
        abort_unless($request->user()->canManageCandidates(), 403);

        $email = $request->user()->email;

        $reset->handle();

        // Users are re-created by the seeder, so sign the visitor back in as the
        // same demo account. Accounts added during the demo no longer exist.
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'デモデータを初期状態に戻しました。デモアカウントでログインしてください。');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'デモデータを初期状態に戻しました。');
    }
}
