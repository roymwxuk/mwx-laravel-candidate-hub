<x-layout title="ダッシュボード">
    <h1 class="mb-6 text-xl font-semibold text-slate-900">ダッシュボード</h1>

    <section class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($stages as $stage)
            <a href="{{ route('candidates.index', ['stage' => $stage->value]) }}" class="rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400">
                <div class="text-xs text-slate-500">{{ $stage->label() }}</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $stageCounts[$stage->value] ?? 0 }}</div>
            </a>
        @endforeach
    </section>

    @if (auth()->user()->canManageCandidates())
        <section class="rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="font-medium">確認待ちの履歴書</h2>
                <a href="{{ route('resumes.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">履歴書をアップロード</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($pendingReviews as $document)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $document->extracted_data['name'] ?? $document->original_name }}</div>
                            <div class="text-xs text-slate-500">{{ $document->original_name }} ・ {{ $document->created_at->format('Y/m/d H:i') }}</div>
                        </div>
                        <a href="{{ route('resumes.show', $document) }}" class="text-sky-700 hover:underline">確認する →</a>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">確認待ちの履歴書はありません。</li>
                @endforelse
            </ul>
        </section>
    @else
        <section class="rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 font-medium">担当の候補者</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($myCandidates as $candidate)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <a href="{{ route('candidates.show', $candidate) }}" class="font-medium hover:underline">{{ $candidate->name }}</a>
                        <x-badge :status="$candidate->stage" />
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">アサインされた候補者はいません。</li>
                @endforelse
            </ul>
        </section>
    @endif
</x-layout>
