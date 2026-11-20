<x-layout :title="$position->title.' | 採用情報'">
    <div class="mb-6">
        <a href="{{ route('jobs.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← 募集中のポジション</a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-slate-900">{{ $position->title }}</h1>
            <a href="{{ route('jobs.apply', $position) }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">このポジションに応募する</a>
        </div>
    </div>

    <article class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-medium text-slate-500">募集要項</h2>
        @if (filled($position->description))
            <div class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $position->description }}</div>
        @else
            <p class="text-sm text-slate-500">募集要項は準備中です。</p>
        @endif
    </article>

    <div class="mt-6 text-center">
        <a href="{{ route('jobs.apply', $position) }}" class="inline-block rounded-md bg-slate-900 px-6 py-2.5 text-sm font-medium text-white hover:bg-slate-700">このポジションに応募する</a>
        <p class="mt-2 text-xs text-slate-500">履歴書（PDF）をアップロードすると、応募フォームに自動入力できます。</p>
    </div>
</x-layout>
