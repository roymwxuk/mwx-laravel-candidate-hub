<x-layout title="ご応募ありがとうございました | 採用情報">
    <div class="mx-auto mt-8 max-w-xl rounded-lg border border-slate-200 bg-white p-8 text-center">
        <h1 class="text-xl font-semibold text-slate-900">ご応募ありがとうございました</h1>
        <p class="mt-4 text-sm leading-relaxed text-slate-700">
            @if (session('applicant_name'))
                {{ session('applicant_name') }} 様、<br>
            @endif
            「{{ $position->title }}」へのご応募を受け付けました。<br>
            書類選考の結果は、ご入力いただいたメールアドレス宛てにご連絡いたします。
        </p>
        <a href="{{ route('jobs.index') }}" class="mt-6 inline-block text-sm text-sky-700 hover:underline">他のポジションを見る</a>
    </div>
</x-layout>
