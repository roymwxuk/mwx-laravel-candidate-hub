{{-- Required consent before an applicant's data is processed (upload or submit). --}}
@props(['id'])

<div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
    <details>
        <summary class="cursor-pointer text-slate-600 hover:text-slate-900">個人情報の取り扱いについて</summary>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-relaxed text-slate-600">
            <li>ご提供いただいた個人情報は、本ポジションの選考および選考に関するご連絡のためにのみ利用します。</li>
            <li>アップロードされた履歴書は、フォームへの自動入力のためAIで読み取ります。読み取り結果は送信前にご自身で確認・修正できます。</li>
            <li>選考に関わる社内の担当者以外に、ご本人の同意なく提供することはありません。</li>
        </ul>
    </details>
    <label for="{{ $id }}" class="mt-2 flex items-start gap-2">
        <input id="{{ $id }}" type="checkbox" name="consent" value="1" @checked(old('consent')) class="mt-0.5" required>
        <span>個人情報の取り扱いに同意する <span class="text-rose-600">*</span></span>
    </label>
    <x-field-error name="consent" />
</div>
