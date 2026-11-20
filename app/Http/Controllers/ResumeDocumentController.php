<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmResume;
use App\Enums\ResumeStatus;
use App\Http\Requests\ConfirmResumeRequest;
use App\Http\Requests\StoreResumeRequest;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeDocumentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ResumeDocument::class);

        return view('resumes.index', [
            'documents' => ResumeDocument::query()->with('uploader', 'candidate')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ResumeDocument::class);

        return view('resumes.create');
    }

    public function store(StoreResumeRequest $request): RedirectResponse
    {
        $file = $request->file('resume');

        // Private disk: resumes are never publicly reachable, only via the download route.
        $path = $file->store('resumes', 'local');

        $document = ResumeDocument::create([
            'uploaded_by' => $request->user()->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'size' => $file->getSize(),
            'status' => ResumeStatus::Uploaded,
        ]);

        ExtractResumeJob::dispatch($document);

        return redirect()
            ->route('resumes.show', $document)
            ->with('status', '履歴書をアップロードしました。解析が完了すると確認画面が表示されます。');
    }

    public function show(ResumeDocument $resume): View
    {
        Gate::authorize('view', $resume);

        return view('resumes.show', ['document' => $resume->load('uploader', 'confirmer', 'candidate')]);
    }

    /**
     * Streams the original PDF inline (used by the side-by-side review screen).
     */
    public function file(ResumeDocument $resume): StreamedResponse
    {
        Gate::authorize('view', $resume);

        return Storage::disk('local')->response($resume->path, $resume->original_name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function retry(ResumeDocument $resume): RedirectResponse
    {
        Gate::authorize('retry', $resume);

        $resume->update(['status' => ResumeStatus::Uploaded, 'error_message' => null]);
        ExtractResumeJob::dispatch($resume);

        return redirect()->route('resumes.show', $resume)->with('status', '再解析を開始しました。');
    }

    public function confirm(ConfirmResumeRequest $request, ResumeDocument $resume, ConfirmResume $action): RedirectResponse
    {
        try {
            $candidate = $action->handle($resume, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return redirect()->route('resumes.show', $resume)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('candidates.show', $candidate)
            ->with('status', '候補者を登録しました。');
    }

    /**
     * Lightweight polling endpoint so the review page can refresh itself
     * while the extraction job is still running.
     */
    public function status(Request $request, ResumeDocument $resume): array
    {
        Gate::authorize('view', $resume);

        return ['status' => $resume->status->value, 'label' => $resume->status->label()];
    }
}
