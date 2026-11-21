<x-layout title="履歴書取込">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-900">履歴書取込</h1>
        <a href="{{ route('resumes.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">履歴書をアップロード</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">ファイル</th>
                    <th class="px-4 py-2 font-medium">状態</th>
                    <th class="px-4 py-2 font-medium">候補者</th>
                    <th class="px-4 py-2 font-medium">アップロード</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('resumes.show', $document) }}" class="text-sky-700 hover:underline">{{ $document->original_name }}</a></td>
                        <td class="px-4 py-3"><x-badge :status="$document->status" /></td>
                        <td class="px-4 py-3">
                            @if ($document->candidate)
                                <a href="{{ route('candidates.show', $document->candidate) }}" class="text-sky-700 hover:underline">{{ $document->candidate->name }}</a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $document->uploader?->name }} ・ {{ $document->created_at->display() }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('resumes.show', $document) }}" class="text-sky-700 hover:underline">詳細</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">まだ履歴書がアップロードされていません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>
</x-layout>
