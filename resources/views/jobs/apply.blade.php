@php
    use App\Enums\ResumeStatus;

    $status = $document?->status;
    $reading = in_array($status, [ResumeStatus::Uploaded, ResumeStatus::Processing], true);
@endphp

<x-layout :title="$position->title.' に応募 | 採用情報'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('jobs.show', $position) }}" class="text-sm text-slate-500 hover:text-slate-800">← 募集要項に戻る</a>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ $position->title }} に応募する</h1>

        @if (config('app.demo_mode'))
            <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                これはデモサイトです。<strong>実在の個人情報や履歴書はアップロードしないでください。</strong>架空のデータでお試しください。
            </p>
        @endif

        {{-- Step 1 (optional): auto-fill from a resume --}}
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-medium text-slate-900">1. 履歴書から自動入力 <span class="text-sm font-normal text-slate-500">（任意）</span></h2>

            @if ($document === null)
                <p class="mt-1 text-sm text-slate-600">履歴書（PDF）をアップロードすると、AIが内容を読み取って下のフォームに入力します。送信前にご自身で確認・修正できます。</p>
                <form method="POST" action="{{ route('jobs.apply.resume', $position) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="resume" class="sr-only">履歴書ファイル（PDF、5MBまで）</label>
                        <input id="resume" type="file" name="resume" accept="application/pdf" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:text-slate-700 hover:file:bg-slate-200">
                        <p class="mt-1 text-xs text-slate-500">PDF、5MBまで</p>
                        <x-field-error name="resume" />
                    </div>
                    <x-privacy-consent id="consent-upload" />
                    <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">アップロードして自動入力</button>
                </form>
            @elseif ($reading)
                <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                     data-poll-url="{{ route('jobs.apply.status', $position) }}" data-poll-status="{{ $status->value }}">
                    <p class="font-medium">「{{ $document->original_name }}」を読み取っています…</p>
                    <p class="mt-1">数秒〜数十秒で完了し、自動でフォームに入力されます。</p>
                </div>
            @elseif ($status === ResumeStatus::Extracted)
                <div class="mt-3 rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                    <p>「{{ $document->original_name }}」から下のフォームに入力しました。<strong>内容に誤りがないか確認し、必要に応じて修正してください。</strong></p>
                    @if (! empty($document->warnings))
                        <p class="mt-2 font-medium">特にご確認ください：</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($document->warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    <p class="font-medium">履歴書を読み取れませんでした。</p>
                    <p class="mt-1">お手数ですが、下のフォームに直接ご入力ください。アップロードした履歴書は応募と一緒に担当者へ届きます。</p>
                </div>
            @endif

            @if ($document !== null)
                <form method="POST" action="{{ route('jobs.apply.discard', $position) }}" class="mt-3">
                    @csrf
                    <button class="text-sm text-slate-500 underline hover:text-slate-800">
                        {{ $reading ? '自動入力をやめて手入力する' : '別の履歴書でやり直す' }}
                    </button>
                </form>
            @endif
        </section>

        {{-- Step 2: review and submit. Hidden while reading, because the page
             reloads when reading finishes and anything typed would be lost. --}}
        @unless ($reading)
            <form method="POST" action="{{ route('jobs.apply.submit', $position) }}" class="mt-6 space-y-5 rounded-lg border border-slate-200 bg-white p-5">
                @csrf
                <h2 class="font-medium text-slate-900">2. 応募内容の確認・入力</h2>
                <p class="-mt-3 text-xs text-slate-500">メールアドレスは選考のご連絡に使用するため必須です。</p>

                <x-candidate-fields :draft="$draft" />

                <x-privacy-consent id="consent-submit" />

                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <button class="rounded-md bg-slate-900 px-6 py-2 text-sm font-medium text-white hover:bg-slate-700">応募する</button>
                </div>
            </form>
        @endunless
    </div>
</x-layout>
