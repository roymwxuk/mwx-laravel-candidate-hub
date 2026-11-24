<x-layout title="ログイン">
    <div class="mx-auto mt-16 max-w-sm">
        <img src="{{ asset('logo-mwx.png') }}" alt="" class="mx-auto mb-3 h-14 w-14 rounded-lg">
        <h1 class="mb-1 text-center text-2xl font-semibold text-slate-900">Candidate Hub</h1>
        <p class="mb-8 text-center text-sm text-slate-500">AI履歴書取込・採用管理</p>

        <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">メールアドレス</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <x-field-error name="email" />
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">パスワード</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <x-field-error name="password" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> ログイン状態を保持する
            </label>
            <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">ログイン</button>
        </form>

        @if (app()->environment('local'))
            <div class="mt-6 rounded-md border border-dashed border-slate-300 p-4 text-xs text-slate-500">
                <p class="mb-1 font-medium">デモアカウント（パスワード: password）</p>
                <ul class="space-y-0.5">
                    <li>admin@example.com — 管理者</li>
                    <li>hr@example.com — 人事担当</li>
                    <li>interviewer@example.com — 面接官</li>
                </ul>
            </div>
        @endif

        <p class="mt-6 text-center text-xs text-slate-500">作者：roymwxuk</p>
    </div>
</x-layout>
