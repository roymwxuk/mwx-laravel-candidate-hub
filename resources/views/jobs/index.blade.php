<x-layout title="採用情報">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">募集中のポジション</h1>
        <p class="mt-1 text-sm text-slate-500">ご興味のあるポジションの詳細をご覧のうえ、応募フォームからご応募ください。履歴書（PDF）をアップロードすると、フォームに自動入力できます。</p>
    </div>

    @if ($positions->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">現在募集中のポジションはありません。</p>
    @else
        <ul class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($positions as $position)
                <li>
                    <a href="{{ route('jobs.show', $position) }}" class="flex h-full flex-col rounded-lg border border-slate-200 bg-white p-5 transition hover:border-slate-400 hover:shadow-sm">
                        <h2 class="font-semibold text-slate-900">{{ $position->title }}</h2>
                        @if ($headline = $position->sectionHeadline('業務内容'))
                            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $headline }}</p>
                        @endif
                        <dl class="mt-4 space-y-1 text-xs text-slate-500">
                            @if ($salary = $position->sectionHeadline('想定年収'))
                                <div class="flex gap-2"><dt class="shrink-0 font-medium text-slate-600">想定年収</dt><dd>{{ $salary }}</dd></div>
                            @endif
                            @if ($location = $position->sectionHeadline('勤務地'))
                                <div class="flex gap-2"><dt class="shrink-0 font-medium text-slate-600">勤務地</dt><dd>{{ $location }}</dd></div>
                            @endif
                        </dl>
                        <span class="mt-auto pt-4 text-sm font-medium text-sky-700">詳細を見る →</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layout>
