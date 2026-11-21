<x-layout title="候補者">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-slate-900">候補者</h1>
        <form method="GET" class="flex gap-2 text-sm">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="氏名・ふりがな・メール" class="rounded-md border border-slate-300 px-3 py-1.5">
            <select name="stage" class="rounded-md border border-slate-300 px-2 py-1.5">
                <option value="">すべてのステージ</option>
                @foreach ($stages as $stage)
                    <option value="{{ $stage->value }}" @selected(($filters['stage'] ?? null) === $stage->value)>{{ $stage->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">絞り込み</button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">氏名</th>
                    <th class="px-4 py-2 font-medium">応募職種</th>
                    <th class="px-4 py-2 font-medium">選考ステージ</th>
                    <th class="px-4 py-2 font-medium">スキル</th>
                    <th class="px-4 py-2 font-medium">面接官</th>
                    <th class="px-4 py-2 font-medium">更新日時</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($candidates as $candidate)
                    <tr>
                        {{-- The link fills the whole cell (padding is on the <a>), so name, furigana and the gaps around them are all clickable. --}}
                        <td class="p-0">
                            <a href="{{ route('candidates.show', $candidate) }}" class="group block px-4 py-3 hover:bg-slate-50">
                                <span class="font-medium text-slate-900 group-hover:text-orange-600 group-hover:underline">{{ $candidate->name }}</span>
                                @if ($candidate->name_kana)<span class="block text-xs text-slate-500">{{ $candidate->name_kana }}</span>@endif
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $candidate->position?->title ?? '未設定' }}</td>
                        <td class="px-4 py-3"><x-badge :status="$candidate->stage" /></td>
                        <td class="px-4 py-3 text-slate-600">{{ collect($candidate->skills)->take(4)->implode(', ') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $candidate->interviewers_count }}名</td>
                        <td class="px-4 py-3 text-slate-500">{{ $candidate->updated_at->display() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">該当する候補者はいません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $candidates->links() }}</div>
</x-layout>
