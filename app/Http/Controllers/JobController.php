<?php

namespace App\Http\Controllers;

use App\Actions\SubmitApplication;
use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Http\Requests\ApplicationRequest;
use App\Http\Requests\ApplicationResumeRequest;
use App\Jobs\ExtractResumeJob;
use App\Models\Position;
use App\Models\ResumeDocument;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public careers pages: open roles, job descriptions, and the application
 * form that an applicant can auto-fill from their resume. No login.
 *
 * An applicant owns their uploaded resume only through their session: its id
 * is kept in the session, never in the URL, so nobody can open another
 * applicant's upload by guessing an id.
 */
class JobController extends Controller
{
    public function index(): View
    {
        return view('jobs.index', [
            'positions' => Position::query()->orderBy('id')->get(),
        ]);
    }

    public function show(Position $position): View
    {
        return view('jobs.show', ['position' => $position]);
    }

    public function apply(Request $request, Position $position): View
    {
        $document = $this->sessionResume($request, $position);

        return view('jobs.apply', [
            'position' => $position,
            'document' => $document,
            'draft' => $document?->status === ResumeStatus::Extracted ? ($document->extracted_data ?? []) : [],
        ]);
    }

    /**
     * Upload a resume for auto-fill. The AI extraction runs on the queue; the
     * page polls status() and reloads with the fields filled in.
     */
    public function uploadResume(ApplicationResumeRequest $request, Position $position): RedirectResponse
    {
        $file = $request->file('resume');

        $document = ResumeDocument::create([
            'uploaded_by' => null,
            'source' => ResumeSource::Application,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('resumes', 'local'),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'size' => $file->getSize(),
            'status' => ResumeStatus::Uploaded,
        ]);

        ExtractResumeJob::dispatch($document);

        $request->session()->put($this->sessionKey($position), $document->getKey());

        return redirect()->route('jobs.apply', $position);
    }

    /**
     * Polled by the apply page while the resume is being read.
     */
    public function status(Request $request, Position $position): JsonResponse
    {
        $document = $this->sessionResume($request, $position);
        abort_if($document === null, 404);

        return response()->json(['status' => $document->status->value]);
    }

    /**
     * Drop the uploaded resume and start over with an empty form.
     */
    public function discardResume(Request $request, Position $position): RedirectResponse
    {
        $request->session()->forget($this->sessionKey($position));

        return redirect()->route('jobs.apply', $position);
    }

    public function submit(ApplicationRequest $request, Position $position, SubmitApplication $action): RedirectResponse
    {
        try {
            $candidate = $action->handle($position, $request->validated(), $this->sessionResume($request, $position));
        } catch (DomainException $e) {
            return redirect()->route('jobs.apply', $position)->with('error', $e->getMessage());
        }

        $request->session()->forget($this->sessionKey($position));

        return redirect()->route('jobs.applied', $position)->with('applicant_name', $candidate->name);
    }

    public function applied(Position $position): View
    {
        return view('jobs.applied', ['position' => $position]);
    }

    private function sessionKey(Position $position): string
    {
        return "application.{$position->getKey()}.resume_document_id";
    }

    /**
     * The resume this browser uploaded for this role, if it can still be used.
     */
    private function sessionResume(Request $request, Position $position): ?ResumeDocument
    {
        $id = $request->session()->get($this->sessionKey($position));

        $document = $id === null ? null : ResumeDocument::query()
            ->whereKey($id)
            ->where('source', ResumeSource::Application->value)
            ->whereNull('candidate_id')
            ->first();

        if ($id !== null && $document === null) {
            $request->session()->forget($this->sessionKey($position));
        }

        return $document;
    }
}
