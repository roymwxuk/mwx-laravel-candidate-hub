{{--
    The candidate's details as editable fields, pre-filled from an AI draft.
    Shared by HR's review form and the public application form, so both
    send exactly what ConfirmResumeRequest / ApplicationRequest validate.
--}}
@props(['draft' => []])

@php
    $histories = old('work_histories', $draft['work_histories'] ?? []);
    $histories[] = ['company' => '', 'title' => '', 'started_on' => '', 'ended_on' => '', 'description' => ''];
    $input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="field-name" class="block text-sm font-medium">氏名 <span class="text-rose-600">*</span></label>
        <input id="field-name" name="name" value="{{ old('name', $draft['name'] ?? '') }}" class="{{ $input }}" required autocomplete="name">
        <x-field-error name="name" />
    </div>
    <div>
        <label for="field-name-kana" class="block text-sm font-medium">ふりがな</label>
        <input id="field-name-kana" name="name_kana" value="{{ old('name_kana', $draft['name_kana'] ?? '') }}" class="{{ $input }}">
        <x-field-error name="name_kana" />
    </div>
    <div>
        <label for="field-email" class="block text-sm font-medium">メールアドレス</label>
        <input id="field-email" name="email" type="email" value="{{ old('email', $draft['email'] ?? '') }}" class="{{ $input }}" autocomplete="email">
        <x-field-error name="email" />
    </div>
    <div>
        <label for="field-phone" class="block text-sm font-medium">電話番号</label>
        <input id="field-phone" name="phone" value="{{ old('phone', $draft['phone'] ?? '') }}" class="{{ $input }}" autocomplete="tel">
        <x-field-error name="phone" />
    </div>
</div>

<div>
    <label for="field-summary" class="block text-sm font-medium">概要</label>
    <textarea id="field-summary" name="summary" rows="3" class="{{ $input }}">{{ old('summary', $draft['summary'] ?? '') }}</textarea>
    <x-field-error name="summary" />
</div>

<div>
    <label for="field-skills" class="block text-sm font-medium">スキル <span class="text-xs font-normal text-slate-500">（カンマまたは改行区切り）</span></label>
    <textarea id="field-skills" name="skills_text" rows="2" class="{{ $input }}">{{ old('skills_text', implode(', ', $draft['skills'] ?? [])) }}</textarea>
    <x-field-error name="skills" />
</div>

<fieldset>
    <legend class="text-sm font-medium">職歴 <span class="text-xs font-normal text-slate-500">（空欄の行は無視されます）</span></legend>
    <div class="mt-2 space-y-3" data-history-rows>
        @foreach ($histories as $i => $history)
            <div class="rounded-md border border-slate-200 p-3" data-history-row>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input name="work_histories[{{ $i }}][company]" value="{{ $history['company'] ?? '' }}" placeholder="会社名" class="{{ $input }}" aria-label="会社名">
                    <input name="work_histories[{{ $i }}][title]" value="{{ $history['title'] ?? '' }}" placeholder="役職" class="{{ $input }}" aria-label="役職">
                    <input name="work_histories[{{ $i }}][started_on]" value="{{ $history['started_on'] ?? '' }}" type="month" class="{{ $input }}" aria-label="開始年月">
                    <input name="work_histories[{{ $i }}][ended_on]" value="{{ $history['ended_on'] ?? '' }}" type="month" class="{{ $input }}" aria-label="終了年月（空欄=現在）">
                </div>
                <textarea name="work_histories[{{ $i }}][description]" rows="2" placeholder="業務内容" class="{{ $input }}" aria-label="業務内容">{{ $history['description'] ?? '' }}</textarea>
                @foreach (['company', 'started_on', 'ended_on', 'description'] as $key)
                    <x-field-error :name="'work_histories.'.$i.'.'.$key" />
                @endforeach
            </div>
        @endforeach
    </div>
    <button type="button" data-add-history class="mt-2 text-sm text-sky-700 hover:underline">＋ 職歴を追加</button>
</fieldset>
