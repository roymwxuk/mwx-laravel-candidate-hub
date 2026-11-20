<x-layout :title="$position->title">
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800">← ダッシュボード</a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-slate-900">{{ $position->title }}</h1>
            @if (auth()->user()->canManageCandidates())
                <a href="{{ route('positions.analytics', $position) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">応募者分析（{{ $applicants }}名）→</a>
            @endif
        </div>
    </div>

    <article class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-medium text-slate-500">募集要項（JD）</h2>
        @if (filled($position->description))
            <div class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $position->description }}</div>
        @else
            <p class="text-sm text-slate-500">募集要項はまだ登録されていません。</p>
        @endif
    </article>
</x-layout>
