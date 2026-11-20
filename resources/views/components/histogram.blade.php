@props(['id', 'title', 'distribution', 'medianLabel', 'axisLabel', 'minSample'])

<section class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-medium text-slate-900">{{ $title }}</h2>
            @if ($distribution && ! $distribution->isTooSmall())
                <p class="mt-0.5 text-xs text-slate-500">
                    対象 {{ $distribution->sample }}名 ・ 中央値 {{ $medianLabel }}
                    @if ($distribution->notApplicable > 0)
                        ・ 該当なし／未記入 {{ $distribution->notApplicable }}名
                    @endif
                </p>
            @endif
        </div>
        {{ $slot }}
    </div>

    @if (! $distribution || $distribution->isTooSmall())
        <p class="py-12 text-center text-sm text-slate-500">
            対象者が{{ $minSample }}名未満のため表示していません（個人の特定を防ぐため）。
        </p>
    @else
        <div class="relative h-64">
            <canvas
                id="{{ $id }}"
                role="img"
                aria-label="{{ $title }}のヒストグラム"
                data-histogram='@json(['labels' => $distribution->labels, 'counts' => $distribution->counts, 'axisLabel' => $axisLabel])'
            ></canvas>
        </div>

        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-slate-500 hover:text-slate-800">表で見る</summary>
            <table class="mt-2 w-full text-left">
                <thead class="text-xs text-slate-500">
                    <tr><th class="py-1 font-normal">{{ $axisLabel }}</th><th class="py-1 text-right font-normal">人数</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($distribution->labels as $i => $label)
                        <tr><td class="py-1">{{ $label }}</td><td class="py-1 text-right tabular-nums">{{ $distribution->counts[$i] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</section>
