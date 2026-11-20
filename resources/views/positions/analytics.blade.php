<x-layout :title="$position->title.' の応募者分析'">
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800">← ダッシュボード</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $position->title }} の応募者分析</h1>
        <a href="{{ route('positions.show', $position) }}" class="text-sm text-sky-700 hover:underline">募集要項（JD）を見る</a>
        <p class="mt-1 text-sm text-slate-500">
            応募者 {{ $applicants }}名（不採用を含む）の分布です。個人の評価ではなく、応募者層の把握に使います。
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- One panel per skill; JS shows the chosen one without reloading the page. --}}
        <div data-skill-panels>
            @forelse ($skillExperiences as $panelSkill => $distribution)
                <div data-skill-panel="{{ $panelSkill }}" @if ($panelSkill !== $skill) hidden @endif>
                    <x-histogram
                        :id="'chart-skill-years-'.$loop->index"
                        :title="$panelSkill.' の経験年数'"
                        :distribution="$distribution"
                        :median-label="$distribution->median !== null ? rtrim(rtrim(number_format($distribution->median, 1), '0'), '.').'年' : '-'"
                        axis-label="経験年数"
                        :min-sample="$minSample"
                    >
                        <form method="GET" action="{{ route('positions.analytics', $position) }}">
                            <label for="skill-{{ $loop->index }}" class="sr-only">スキル</label>
                            <select id="skill-{{ $loop->index }}" name="skill" data-skill-select class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm">
                                @foreach ($skills as $option)
                                    <option value="{{ $option }}" @selected($option === $panelSkill)>{{ $option }}</option>
                                @endforeach
                            </select>
                            <noscript><button class="ml-1 text-sm text-sky-700">表示</button></noscript>
                        </form>
                    </x-histogram>
                </div>
            @empty
                <x-histogram id="chart-skill-years" title="スキル別の経験年数" :distribution="null" median-label="-" axis-label="経験年数" :min-sample="$minSample" />
            @endforelse
        </div>

        <x-histogram
            id="chart-expected-salary"
            title="希望年収"
            :distribution="$expectedSalary"
            :median-label="$expectedSalary->median !== null ? number_format($expectedSalary->median).'万円' : '-'"
            axis-label="希望年収"
            :min-sample="$minSample"
        />

        <x-histogram
            id="chart-education-level"
            title="最終学歴"
            :distribution="$educationLevel"
            :median-label="$educationLevel->median !== null ? \App\Enums\EducationLevel::fromRank((int) $educationLevel->median)->label() : '-'"
            axis-label="最終学歴"
            :min-sample="$minSample"
        />
    </div>
</x-layout>
