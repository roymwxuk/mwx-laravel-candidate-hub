@php
    $draft = $document->extracted_data ?? [];
@endphp

<x-layout :title="$document->original_name">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('resumes.index') }}" class="text-sm text-slate-500 hover:underline">← 履歴書取込</a>
            <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $document->original_name }}</h1>
            <p class="text-xs text-slate-500">
                {{ $document->uploader?->name }} が {{ $document->created_at->display() }} にアップロード
                @if ($document->extractor) ・ 抽出エンジン: <code>{{ $document->extractor }}</code> @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <x-badge :status="$document->status" class="text-sm" />
            @can('delete', $document)
                <form method="POST" action="{{ route('resumes.destroy', $document) }}"
                      data-confirm="「{{ $document->original_name }}」を削除します。アップロードしたPDFと抽出結果は元に戻せません。よろしいですか？">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md border border-rose-300 bg-white px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50">削除</button>
                </form>
            @endcan
        </div>
    </div>

    @switch($document->status)
        @case(\App\Enums\ResumeStatus::Uploaded)
        @case(\App\Enums\ResumeStatus::Processing)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
                 data-poll-url="{{ route('resumes.status', $document) }}" data-poll-status="{{ $document->status->value }}">
                <p class="font-medium">AIが履歴書を解析しています…</p>
                <p class="mt-1">通常10〜60秒ほどで完了し、自動で画面が更新されます。</p>
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
                {{ $document->confirmer?->name }} が {{ $document->confirmed_at?->display() }} に確認し、候補者として登録しました。
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
                {{-- Sized from the width so a typical 2-page A4 resume shows in full: each page
                     is 1:√2, plus the viewer's toolbar and page gaps. #view=FitH fits pages to the width. --}}
                <iframe src="{{ route('resumes.file', $document) }}#view=FitH" title="履歴書原本" class="aspect-[1/3] min-h-[80vh] w-full rounded-lg border border-slate-200 bg-white"></iframe>

                <form method="POST" action="{{ route('resumes.confirm', $document) }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    {{-- Same submit as the bottom button, so a reviewer who has checked the
                         draft doesn't have to scroll past every field to register. --}}
                    <div class="flex justify-end border-b border-slate-100 pb-4">
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">候補者として登録</button>
                    </div>

                    <div>
                        <label for="field-position" class="block text-sm font-medium">応募職種</label>
                        <select id="field-position" name="position_id" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                            <option value="">未設定</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected((string) old('position_id') === (string) $position->id)>{{ $position->title }}</option>
                            @endforeach
                        </select>
                        <x-field-error name="position_id" />
                    </div>

                    <div>
                        <label for="field-referral-source" class="block text-sm font-medium">応募経路（経由・紹介）</label>
                        <input id="field-referral-source" name="referral_source" list="referral-source-options" maxlength="100"
                               value="{{ old('referral_source') }}" placeholder="例：LinkedIn、doda、社員紹介（山田）"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                        {{-- Suggestions only: HR can type anything. Picking from the list keeps spellings consistent. --}}
                        <datalist id="referral-source-options">
                            @foreach (['LinkedIn', 'doda', 'リクルートエージェント', 'ビズリーチ', 'Green', 'Wantedly', 'Findy', '社員紹介', '自社採用サイト'] as $option)
                                <option value="{{ $option }}"></option>
                            @endforeach
                        </datalist>
                        <x-field-error name="referral_source" />
                    </div>

                    <x-candidate-fields :draft="$draft" />

                    <div class="flex justify-end border-t border-slate-100 pt-4">
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">候補者として登録</button>
                    </div>
                </form>
            </div>
            @break
    @endswitch
</x-layout>
