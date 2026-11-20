<x-layout :title="$candidate->name">
    <div class="mb-6">
        <a href="{{ route('candidates.index') }}" class="text-sm text-slate-500 hover:underline">← 候補者一覧</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold text-slate-900">{{ $candidate->name }}</h1>
            @if ($candidate->name_kana)<span class="text-sm text-slate-500">{{ $candidate->name_kana }}</span>@endif
            <x-badge :status="$candidate->stage" />
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 font-medium">基本情報</h2>
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
                    <dt class="text-slate-500">メール</dt><dd>{{ $candidate->email ?? '—' }}</dd>
                    <dt class="text-slate-500">電話番号</dt><dd>{{ $candidate->phone ?? '—' }}</dd>
                    <dt class="text-slate-500">概要</dt><dd class="whitespace-pre-line">{{ $candidate->summary ?? '—' }}</dd>
                    <dt class="text-slate-500">スキル</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse ($candidate->skills ?? [] as $skill)
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $skill }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 font-medium">職歴</h2>
                <ol class="space-y-4 border-l border-slate-200 pl-4">
                    @forelse ($candidate->workHistories as $history)
                        <li>
                            <div class="text-xs text-slate-500">{{ $history->period() }}</div>
                            <div class="font-medium">{{ $history->company }} @if ($history->title)<span class="font-normal text-slate-600">／ {{ $history->title }}</span>@endif</div>
                            @if ($history->description)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $history->description }}</p>@endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">職歴は登録されていません。</li>
                    @endforelse
                </ol>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 font-medium">操作履歴</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($candidate->auditLogs as $log)
                        <li class="flex gap-3">
                            <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at->format('Y/m/d H:i') }}</span>
                            <span>
                                <span class="font-medium">{{ $log->user?->name ?? 'システム' }}</span>：{{ $log->description() }}
                                @if (! empty($log->properties['note']))<span class="text-slate-500">（{{ $log->properties['note'] }}）</span>@endif
                                @if (array_key_exists('corrected_fields', $log->properties ?? []))
                                    <span class="text-xs text-slate-500">
                                        — AI抽出からの修正: {{ $log->properties['corrected_fields'] ? implode(', ', $log->properties['corrected_fields']) : 'なし' }}
                                    </span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-slate-500">操作履歴はありません。</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            @can('changeStage', $candidate)
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 font-medium">選考ステージ</h2>
                    @if ($candidate->stage->isTerminal())
                        <p class="text-sm text-slate-500">「{{ $candidate->stage->label() }}」は最終ステージのため変更できません。</p>
                    @else
                        <form method="POST" action="{{ route('candidates.stage.update', $candidate) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <select name="stage" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                @foreach ($candidate->stage->allowedTransitions() as $next)
                                    <option value="{{ $next->value }}">{{ $next->label() }}へ進める</option>
                                @endforeach
                            </select>
                            <x-field-error name="stage" />
                            <textarea name="note" rows="2" placeholder="メモ（任意）" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">{{ old('note') }}</textarea>
                            <button class="w-full rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">更新</button>
                        </form>
                    @endif
                </section>
            @endcan

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-3 font-medium">面接官</h2>
                <ul class="mb-3 space-y-1 text-sm">
                    @forelse ($candidate->interviewers as $interviewer)
                        <li class="flex items-center justify-between">
                            <span>{{ $interviewer->name }}</span>
                            @can('assignInterviewers', $candidate)
                                <form method="POST" action="{{ route('candidates.interviewers.destroy', [$candidate, $interviewer]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-slate-400 hover:text-rose-600">解除</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-slate-500">未アサイン</li>
                    @endforelse
                </ul>
                @can('assignInterviewers', $candidate)
                    @if ($availableInterviewers->isNotEmpty())
                        <form method="POST" action="{{ route('candidates.interviewers.store', $candidate) }}" class="flex gap-2">
                            @csrf
                            <select name="user_id" class="flex-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                @foreach ($availableInterviewers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">追加</button>
                        </form>
                        <x-field-error name="user_id" />
                    @endif
                @endcan
            </section>

            @if ($candidate->resumeDocument && auth()->user()->can('view', $candidate->resumeDocument))
                <section class="rounded-lg border border-slate-200 bg-white p-5 text-sm">
                    <h2 class="mb-2 font-medium">応募書類</h2>
                    <a href="{{ route('resumes.file', $candidate->resumeDocument) }}" target="_blank" class="text-sky-700 hover:underline">{{ $candidate->resumeDocument->original_name }}</a>
                    <p class="mt-1 text-xs text-slate-500">登録者: {{ $candidate->creator?->name ?? '—' }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layout>
