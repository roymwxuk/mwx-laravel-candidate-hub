<x-layout title="ユーザー管理">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-900">ユーザー管理</h1>
        <a href="{{ route('users.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">ユーザーを追加</a>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr><th class="px-4 py-2 font-medium">氏名</th><th class="px-4 py-2 font-medium">メール</th><th class="px-4 py-2 font-medium">権限</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
