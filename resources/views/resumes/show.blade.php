@php
    $draft = $document->extracted_data ?? [];
    $histories = old('work_histories', $draft['work_histories'] ?? []);
    $histories[] = ['company' => '', 'title' => '', 'started_on' => '', 'ended_on' => '', 'description' => ''];
    $input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none';
@endphp

<x-layout :title="$document->original_name">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('resumes.index') }}" class="text-sm text-slate-500 hover:underline">← 履歴書取込</a>
            <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $document->original_name }}</h1>
            <p class="text-xs text-slate-500">
                {{ $document->uploader?->name }} が {{ $document->created_at->format('Y/m/d H:i') }} にアップロード
                @if ($document->extractor) ・ 抽出エンジン: <code>{{ $document->extractor }}</code> @endif
            </p>
        </div>
        <x-badge :status="$document->status" class="text-sm" />
    </div>

    @switch($document->status)
        @case(\App\Enums\ResumeStatus::Uploaded)
        @case(\App\Enums\ResumeStatus::Processing)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
                 data-poll-url="{{ route('resumes.status', $document) }}" data-poll-status="{{ $document->status->value }}">
                <p class="font-medium">AIが履歴書を解析しています…</p>
                <p class="mt-1">完了すると自動で画面が更新されます。キューワーカー（<code>php artisan queue:work</code>）が起動していることを確認してください。</p>
            </div>
            @break

        @case(\App\Enums\ResumeStatus::Failed)
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-sm text-rose-900">
                <p class="font-medium">解析に失敗しました</p>
                <p class="mt-1">{{ $document->error_message }}</p>
                @can('retry', $document)
                    <form method="POST" action="{{ route('resumes.retry', $document) }}" class="mt-4">
                        @csrf
                        <button class="rounded-md bg-rose-700 px-3 py-1.5 text-white hover:bg-rose-600">再解析する</button>
                    </form>
                @endcan
            </div>
            @break

        @case(\App\Enums\ResumeStatus::Confirmed)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-6 text-sm text-emerald-900">
                {{ $document->confirmer?->name }} が {{ $document->confirmed_at?->format('Y/m/d H:i') }} に確認し、候補者として登録しました。
                @if ($document->candidate)
                    <a href="{{ route('candidates.show', $document->candidate) }}" class="ml-2 font-medium underline">{{ $document->candidate->name }} さんの詳細へ →</a>
                @endif
            </div>
            @break

        @case(\App\Enums\ResumeStatus::Extracted)
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                以下はAIが抽出した<strong>下書き</strong>です。左の原本と見比べて内容を確認・修正し、「候補者として登録」を押してください。
            </div>

            @if (! empty($document->warnings))
                <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-medium">要確認（{{ count($document->warnings) }}件）</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($document->warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <iframe src="{{ route('resumes.file', $document) }}" title="履歴書原本" class="h-[80vh] w-full rounded-lg border border-slate-200 bg-white"></iframe>

                <form method="POST" action="{{ route('resumes.confirm', $document) }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium">氏名 <span class="text-rose-600">*</span></label>
                            <input name="name" value="{{ old('name', $draft['name'] ?? '') }}" class="{{ $input }}" required>
                            <x-field-error name="name" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">ふりがな</label>
                            <input name="name_kana" value="{{ old('name_kana', $draft['name_kana'] ?? '') }}" class="{{ $input }}">
                            <x-field-error name="name_kana" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">メールアドレス</label>
                            <input name="email" type="email" value="{{ old('email', $draft['email'] ?? '') }}" class="{{ $input }}">
                            <x-field-error name="email" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">電話番号</label>
                            <input name="phone" value="{{ old('phone', $draft['phone'] ?? '') }}" class="{{ $input }}">
                            <x-field-error name="phone" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">概要</label>
                        <textarea name="summary" rows="3" class="{{ $input }}">{{ old('summary', $draft['summary'] ?? '') }}</textarea>
                        <x-field-error name="summary" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">スキル <span class="text-xs font-normal text-slate-500">（カンマまたは改行区切り）</span></label>
                        <textarea name="skills_text" rows="2" class="{{ $input }}">{{ old('skills_text', implode(', ', $draft['skills'] ?? [])) }}</textarea>
                        <x-field-error name="skills" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium">職歴 <span class="text-xs font-normal text-slate-500">（空欄の行は無視されます）</span></legend>
                        <div class="mt-2 space-y-3" data-history-rows>
                            @foreach ($histories as $i => $history)
                                <div class="rounded-md border border-slate-200 p-3" data-history-row>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input name="work_histories[{{ $i }}][company]" value="{{ $history['company'] ?? '' }}" placeholder="会社名" class="{{ $input }}">
                                        <input name="work_histories[{{ $i }}][title]" value="{{ $history['title'] ?? '' }}" placeholder="役職" class="{{ $input }}">
                                        <input name="work_histories[{{ $i }}][started_on]" value="{{ $history['started_on'] ?? '' }}" type="month" class="{{ $input }}" aria-label="開始年月">
                                        <input name="work_histories[{{ $i }}][ended_on]" value="{{ $history['ended_on'] ?? '' }}" type="month" class="{{ $input }}" aria-label="終了年月（空欄=現在）">
                                    </div>
                                    <textarea name="work_histories[{{ $i }}][description]" rows="2" placeholder="業務内容" class="{{ $input }}">{{ $history['description'] ?? '' }}</textarea>
                                    @foreach (['company', 'started_on', 'ended_on', 'description'] as $key)
                                        <x-field-error :name="'work_histories.'.$i.'.'.$key" />
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <button type="button" data-add-history class="mt-2 text-sm text-sky-700 hover:underline">＋ 職歴を追加</button>
                    </fieldset>

                    <div class="flex justify-end border-t border-slate-100 pt-4">
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">候補者として登録</button>
                    </div>
                </form>
            </div>
            @break
    @endswitch
</x-layout>
