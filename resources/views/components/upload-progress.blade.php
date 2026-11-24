{{-- Shown by app.js while a form[data-loading] is being submitted. --}}
<div data-loading-panel role="status" hidden class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <x-spinner class="mt-0.5" />
    <div>
        <p class="font-medium">AIが履歴書を解析しています…</p>
        <p class="mt-1">10〜30秒ほどかかります。このページを閉じずにお待ちください。</p>
    </div>
</div>
