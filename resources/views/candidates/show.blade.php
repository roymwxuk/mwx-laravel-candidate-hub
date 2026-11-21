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
                    <dt class="text-slate-500">応募職種</dt><dd>{{ $candidate->position?->title ?? '未設定' }}</dd>
                    <dt class="text-slate-500">応募経路</dt><dd>{{ $candidate->referral_source ?? '—' }}</dd>
                    <dt class="text-slate-500">メール</dt><dd>{{ $candidate->email ?? '—' }}</dd>
                    <dt class="text-slate-500">電話番号</dt><dd>{{ $candidate->phone ?? '—' }}</dd>
                    <dt class="text-slate-500">生年月日</dt>
                    <dd>{{ $candidate->birth_date ? $candidate->birth_date->format('Y-m-d').'（'.$candidate->birth_date->age.'歳）' : '—' }}</dd>
                    @can('viewSensitiveDetails', $candidate)
                        <dt class="text-slate-500">性別</dt><dd>{{ $candidate->gender?->label() ?? '未記入' }}</dd>
                    @endcan
                    <dt class="text-slate-500">最終学歴</dt>
                    <dd>
                        @php($education = array_filter([$candidate->education_level?->label(), $candidate->school_name, $candidate->major]))
                        {{ $education ? implode(' ／ ', $education) : '—' }}
                        @if ($candidate->graduated_on)<span class="text-slate-500">（{{ $candidate->graduated_on }} 卒業）</span>@endif
                    </dd>
                    <dt class="text-slate-500">希望年収</dt><dd>{{ $candidate->expected_salary ? number_format($candidate->expected_salary).'万円' : '—' }}</dd>
                    <dt class="text-slate-500">希望勤務地</dt><dd>{{ $candidate->desired_location ?? '—' }}</dd>
                    <dt class="text-slate-500">概要</dt><dd class="whitespace-pre-line">{{ $candidate->summary ?? '—' }}</dd>
                    <dt class="text-slate-500">スキル</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse ($candidate->skills ?? [] as $skill)
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $skill }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>
                    <dt class="text-slate-500">語学力</dt>
                    <dd>
                        @forelse ($candidate->languages ?? [] as $language)
                            <div>{{ $language }}</div>
                        @empty
                            —
                        @endforelse
                    </dd>
                </dl>
            </section>

            @can('editHrNote', $candidate)
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="mb-1 font-medium">備考</h2>
                    <p class="mb-3 text-xs text-slate-500">人事のみ閲覧・編集できます（面接官には表示されません）。配慮の申し出など、要配慮個人情報の取り扱いに注意してください。</p>
                    <form method="POST" action="{{ route('candidates.hr-note.update', $candidate) }}" class="space-y-2">
                        @csrf
                        @method('PATCH')
                        <textarea name="hr_note" rows="4" maxlength="2000" placeholder="例：面接時に配慮の申し出あり（段差のない会場を希望）" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">{{ old('hr_note', $candidate->hr_note) }}</textarea>
                        <x-field-error name="hr_note" />
                        <div class="text-right">
                            <button class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">保存</button>
                        </div>
                    </form>
                </section>
            @endcan

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
                            <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at->display() }}</span>
                            <span>
                                <span class="font-medium">{{ $log->user?->name ?? ($log->action === 'candidate.applied' ? '応募者' : 'システム') }}</span>：{{ $log->description() }}
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
            @can('changePosition', $candidate)
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 font-medium">応募職種</h2>
                    <form method="POST" action="{{ route('candidates.position.update', $candidate) }}" class="flex gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="position_id" aria-label="応募職種" class="flex-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">未設定</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected((string) old('position_id', $candidate->position_id) === (string) $position->id)>{{ $position->title }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">更新</button>
                    </form>
                    <x-field-error name="position_id" />
                </section>
            @endcan

            @can('changeStage', $candidate)
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="mb-3 font-medium">選考ステージ</h2>
                    @if ($candidate->stage->isTerminal())
                        <p class="text-sm text-slate-500">「{{ $candidate->stage->label() }}」は最終ステージのため変更できません。</p>
                    @else
                        <form method="POST" action="{{ route('candidates.stage.update', $candidate) }}" class="space-y-3" data-confirm-stage>
                            @csrf
                            @method('PATCH')
                            <select name="stage" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                @foreach ($candidate->stage->allowedTransitions() as $next)
                                    @if ($candidate->stage->isReopenedBy($next))
                                        <option value="{{ $next->value }}" data-confirm="「{{ $candidate->name }}」さんを不採用から「{{ $next->label() }}」に戻し、選考を最初からやり直します。よろしいですか？">{{ $next->label() }}に戻す（選考を再開）</option>
                                    @else
                                        <option value="{{ $next->value }}">{{ $next->label() }}へ進める</option>
                                    @endif
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
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($candidate->resumeDocument->source === \App\Enums\ResumeSource::Application)
                            応募者本人が応募フォームからアップロード
                        @else
                            登録者: {{ $candidate->creator?->name ?? '—' }}
                        @endif
                    </p>
                </section>
            @endif
        </aside>
    </div>
</x-layout>
