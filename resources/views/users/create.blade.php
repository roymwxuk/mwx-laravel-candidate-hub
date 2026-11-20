<x-layout title="ユーザーを追加">
    <h1 class="mb-6 text-xl font-semibold text-slate-900">ユーザーを追加</h1>
    @php($input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm')
    <form method="POST" action="{{ route('users.store') }}" class="max-w-md space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        <div>
            <label class="block text-sm font-medium">氏名</label>
            <input name="name" value="{{ old('name') }}" required class="{{ $input }}">
            <x-field-error name="name" />
        </div>
        <div>
            <label class="block text-sm font-medium">メールアドレス</label>
            <input name="email" type="email" value="{{ old('email') }}" required class="{{ $input }}">
            <x-field-error name="email" />
        </div>
        <div>
            <label class="block text-sm font-medium">権限</label>
            <select name="role" class="{{ $input }}">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <x-field-error name="role" />
        </div>
        <div>
            <label class="block text-sm font-medium">パスワード（8文字以上）</label>
            <input name="password" type="password" required class="{{ $input }}">
            <x-field-error name="password" />
        </div>
        <div>
            <label class="block text-sm font-medium">パスワード（確認）</label>
            <input name="password_confirmation" type="password" required class="{{ $input }}">
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">作成</button>
    </form>
</x-layout>
