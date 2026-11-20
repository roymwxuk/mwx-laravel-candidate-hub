@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    @auth
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="font-semibold text-slate-900">Candidate Hub</a>
                    <nav class="flex gap-4 text-sm">
                        <a href="{{ route('dashboard') }}" @class(['hover:text-slate-900', 'font-semibold text-slate-900' => request()->routeIs('dashboard'), 'text-slate-500' => ! request()->routeIs('dashboard')])>ダッシュボード</a>
                        @can('viewAny', App\Models\ResumeDocument::class)
                            <a href="{{ route('resumes.index') }}" @class(['hover:text-slate-900', 'font-semibold text-slate-900' => request()->routeIs('resumes.*'), 'text-slate-500' => ! request()->routeIs('resumes.*')])>履歴書取込</a>
                        @endcan
                        <a href="{{ route('candidates.index') }}" @class(['hover:text-slate-900', 'font-semibold text-slate-900' => request()->routeIs('candidates.*'), 'text-slate-500' => ! request()->routeIs('candidates.*')])>候補者</a>
                        @can('viewAny', App\Models\User::class)
                            <a href="{{ route('users.index') }}" @class(['hover:text-slate-900', 'font-semibold text-slate-900' => request()->routeIs('users.*'), 'text-slate-500' => ! request()->routeIs('users.*')])>ユーザー管理</a>
                        @endcan
                        <a href="{{ route('jobs.index') }}" @class(['hover:text-slate-900', 'font-semibold text-slate-900' => request()->routeIs('jobs.*'), 'text-slate-500' => ! request()->routeIs('jobs.*')])>採用ページ</a>
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ auth()->user()->role->label() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-500 hover:text-slate-900">ログアウト</button>
                    </form>
                </div>
            </div>
        </header>
    @else
        {{-- Visitors: the public careers pages (and the staff login). --}}
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('jobs.index') }}" class="font-semibold text-slate-900">Candidate Hub <span class="font-normal text-slate-500">採用情報</span></a>
                <a href="{{ route('login') }}" class="text-sm text-slate-500 hover:text-slate-900">社員ログイン</a>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
