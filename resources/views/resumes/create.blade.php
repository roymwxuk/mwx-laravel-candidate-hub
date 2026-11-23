<x-layout title="履歴書をアップロード">
    <h1 class="mb-2 text-xl font-semibold text-slate-900">履歴書をアップロード</h1>
    <p class="mb-6 text-sm text-slate-600">
        履歴書・職務経歴書（PDF）から氏名・連絡先・職歴などをAIが抽出し、入力フォームに下書きとして反映します。<br>
        抽出結果はそのまま登録されません。必ず担当者が確認・修正してから登録します。
    </p>

    @if (config('services.extractor.mock_notice'))
        <p class="mb-6 max-w-lg rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            このデモでは、コストの都合により、LLM（Azure OpenAI）の代わりにルールベースの抽出（モック）を使用しています。
        </p>
    @endif

    <form method="POST" action="{{ route('resumes.store') }}" enctype="multipart/form-data" class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        <div>
            <label for="resume" class="block text-sm font-medium">履歴書ファイル（PDF・5MBまで）</label>
            <input id="resume" name="resume" type="file" accept="application/pdf" required
                   class="mt-2 block w-full text-sm file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm hover:file:bg-slate-200">
            <x-field-error name="resume" />
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">アップロードして解析</button>
    </form>
</x-layout>
