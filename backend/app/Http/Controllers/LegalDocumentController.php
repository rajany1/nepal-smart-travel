<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegalDocumentController extends Controller
{
    /**
     * List legal documents (admin panel), grouped to one row per document.
     */
    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'draft', 'published', 'archived'], true)) {
            $status = 'all';
        }

        $types = LegalDocument::types();

        $query = LegalDocument::query();

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('type', 'like', $like);
            });
        }

        $all = $query->orderByDesc('updated_at')->get();

        $counts = [
            'all' => $all->count(),
            'draft' => $all->where('status', 'draft')->count(),
            'published' => $all->where('status', 'published')->count(),
            'archived' => $all->where('status', 'archived')->count(),
        ];

        $rows = $status === 'all' ? $all : $all->where('status', $status);

        $groups = $rows->groupBy(fn ($doc) => $doc->slug ?? $doc->type);

        $cards = $groups->map(function ($versions) {
            $published = $versions->where('status', LegalDocument::STATUS_PUBLISHED)
                ->sortByDesc('published_at')->first();
            $draft = $versions->where('status', LegalDocument::STATUS_DRAFT)
                ->sortByDesc('updated_at')->first();
            $archived = $versions->where('status', LegalDocument::STATUS_ARCHIVED)
                ->sortByDesc('updated_at')->first();

            $display = $published ?? $draft ?? $archived ?? $versions->first();

            return [
                'document' => $display,
                'draft' => $draft,
                'hasDraft' => $draft !== null && $draft->id !== $display->id,
                'versionsCount' => $versions->count(),
            ];
        })->sort(fn ($a, $b) => $b['document']->updated_at <=> $a['document']->updated_at)->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $cardsPage = $cards->forPage($page, $perPage);

        $documents = new LengthAwarePaginator(
            $cardsPage,
            $cards->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.legal-documents.index', compact('documents', 'counts', 'search', 'status', 'types'));
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $this->requireAdmin($request);

        $types = LegalDocument::types();

        return view('admin.legal-documents.create', compact('types'));
    }

    /**
     * Store a new legal document as a draft (first version).
     */
    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $types = LegalDocument::types();

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys($types))],
            'slug' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('legal_documents', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'reference_url' => ['nullable', 'string', 'max:500', 'regex:'.HtmlSanitizer::safeUrlRegex()],
            'content' => ['required', 'string', 'max:500000'],
            'version' => ['nullable', 'string', 'max:50'],
            'effective_date' => ['nullable', 'date'],
        ], [
            'reference_url.regex' => 'The reference URL must be an https:// or http:// link, a mailto: address, or a site-relative path starting with a single / (e.g. /legal/privacy).',
        ]);

        // One document per type — versions are added by editing/forking, not re-creating.
        if (LegalDocument::where('type', $validated['type'])->exists()) {
            throw ValidationException::withMessages([
                'type' => 'A document for this type already exists. Edit the existing document instead.',
            ]);
        }

        $doc = LegalDocument::create([
            'type' => $validated['type'],
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'short_description' => $validated['short_description'] ?? null,
            'reference_url' => $validated['reference_url'] ?? null,
            'content' => HtmlSanitizer::clean($validated['content']),
            'version' => $validated['version'] ?? '1.0',
            'status' => LegalDocument::STATUS_DRAFT,
            'effective_date' => $validated['effective_date'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.created',
            'resource_type' => 'legal_document',
            'resource_id' => $doc->id,
            'description' => "Created legal document draft: {$doc->title} ({$doc->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.legal-documents.edit', $doc->id)
            ->with('success', 'Draft created. Edit the content, then publish when ready.');
    }

    /**
     * Show edit form. Published/archived versions are never edited in place —
     * editing forks (or reuses) a draft version.
     */
    public function edit(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if (! $document->isDraft()) {
            $draft = LegalDocument::query()
                ->where('slug', $document->slug)
                ->where('status', LegalDocument::STATUS_DRAFT)
                ->orderByDesc('updated_at')
                ->first();

            $draft ??= $this->forkDraft($document, $request);

            return redirect()->route('admin.legal-documents.edit', $draft->id);
        }

        $types = LegalDocument::types();
        $published = LegalDocument::query()
            ->where('slug', $document->slug)
            ->where('status', LegalDocument::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();
        $versionsCount = LegalDocument::where('slug', $document->slug)->count();

        return view('admin.legal-documents.edit', compact('document', 'types', 'published', 'versionsCount'));
    }

    /**
     * Update an existing draft version. Published/archived rows are immutable.
     */
    public function update(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if (! $document->isDraft()) {
            abort(403, 'Published and archived versions cannot be edited directly. Edit action creates a draft fork instead.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'reference_url' => ['nullable', 'string', 'max:500', 'regex:'.HtmlSanitizer::safeUrlRegex()],
            'content' => ['required', 'string', 'max:500000'],
            'version' => [
                'nullable', 'string', 'max:50',
                Rule::unique('legal_documents', 'version')
                    ->where('type', $document->type)
                    ->ignore($document->id),
            ],
            'effective_date' => ['nullable', 'date'],
        ], [
            'reference_url.regex' => 'The reference URL must be an https:// or http:// link, a mailto: address, or a site-relative path starting with a single / (e.g. /legal/privacy).',
        ]);

        $data = [
            'title' => $validated['title'],
            'short_description' => $validated['short_description'] ?? null,
            'reference_url' => $validated['reference_url'] ?? null,
            'content' => HtmlSanitizer::clean($validated['content']),
            'version' => $validated['version'] ?? $document->version,
            'updated_by' => Auth::id(),
            'last_edited_by' => Auth::id(),
        ];

        if (array_key_exists('effective_date', $validated)) {
            $data['effective_date'] = $validated['effective_date'];
        }

        $document->update($data);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.updated',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Updated legal document draft: {$document->title} ({$document->slug})",
            'ip_address' => $request->ip(),
        ]);

        // "Publish" button on the edit form submits the same form with
        // action=publish — save first (above), then publish in one step.
        if ($request->input('action') === 'publish') {
            if ($this->isPlaceholderOnly($document->content)) {
                return redirect()->route('admin.legal-documents.edit', $document->id)
                    ->with('error', 'Draft saved — but it cannot be published yet. Replace the placeholder with real legal content first.');
            }

            $this->applyPublish($document);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'legal_document.published',
                'resource_type' => 'legal_document',
                'resource_id' => $document->id,
                'description' => "Published legal document: {$document->title} v{$document->version} ({$document->slug})",
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.legal-documents.index')
                ->with('success', "Draft saved. Version {$document->version} is now live.");
        }

        return redirect()->route('admin.legal-documents.edit', $document->id)
            ->with('success', 'Draft saved. The published version is unchanged until you publish.');
    }

    /**
     * Publish a draft (or re-publish an archived version).
     * The previously published version is archived, never overwritten.
     */
    public function publish(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if ($document->isPublishedStatus()) {
            return redirect()->back()->with('error', 'This version is already published.');
        }

        if ($this->isPlaceholderOnly($document->content)) {
            return redirect()->back()->with('error', 'Add the real legal content before publishing. The admin placeholder cannot be published.');
        }

        $this->applyPublish($document);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.published',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Published legal document: {$document->title} v{$document->version} ({$document->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', "Version {$document->version} is now live.");
    }

    /**
     * Atomically publish a version: archive the previously published sibling
     * (same slug/type), then mark this document as the live one.
     */
    private function applyPublish(LegalDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $previous = LegalDocument::query()
                ->where('status', LegalDocument::STATUS_PUBLISHED)
                ->where('id', '!=', $document->id);

            if ($document->slug !== null) {
                $previous->where('slug', $document->slug);
            } else {
                $previous->where('type', $document->type);
            }

            $previous->update([
                'status' => LegalDocument::STATUS_ARCHIVED,
                'is_published' => false,
                'updated_by' => Auth::id(),
            ]);

            $document->update([
                'status' => LegalDocument::STATUS_PUBLISHED,
                'published_at' => now(),
                'effective_date' => $document->effective_date ?? now()->toDateString(),
                'updated_by' => Auth::id(),
                'last_edited_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Unpublish: revert a published version to draft (hidden from the public).
     */
    public function unpublish(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if (! $document->isPublishedStatus()) {
            return redirect()->back()->with('error', 'Only published documents can be unpublished.');
        }

        $document->update([
            'status' => LegalDocument::STATUS_DRAFT,
            'updated_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.unpublished',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Unpublished legal document: {$document->title} ({$document->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Document unpublished.');
    }

    /**
     * Archive a published version (retired, hidden from the public, kept as history).
     */
    public function archive(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if (! $document->isPublishedStatus()) {
            return redirect()->back()->with('error', 'Only published documents can be archived.');
        }

        $document->update([
            'status' => LegalDocument::STATUS_ARCHIVED,
            'updated_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.archived',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Archived legal document: {$document->title} ({$document->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Document archived. It is no longer publicly visible.');
    }

    /**
     * Admin-only preview of any version (draft, published, or archived).
     */
    public function preview(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);
        $types = LegalDocument::types();

        return view('admin.legal-documents.preview', compact('document', 'types'));
    }

    /**
     * Version history for a document (all versions of the same slug).
     */
    public function versions(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        $versions = LegalDocument::query()
            ->where('slug', $document->slug)
            ->orderByDesc('id')
            ->with(['creator', 'editor'])
            ->get();

        $types = LegalDocument::types();

        return view('admin.legal-documents.versions', compact('document', 'versions', 'types'));
    }

    /**
     * Delete a draft version. Published/archived history is protected.
     */
    public function destroy(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        if (! $document->isDraft()) {
            return redirect()->back()->with('error', 'Only draft versions can be deleted. Archive published documents instead — history is preserved.');
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.deleted',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Deleted legal document draft: {$document->title} ({$document->slug})",
            'ip_address' => $request->ip(),
        ]);

        $document->delete();

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Draft deleted.');
    }

    /**
     * Fork a copy of a published/archived version into a new editable draft.
     */
    private function forkDraft(LegalDocument $source, Request $request): LegalDocument
    {
        $draft = LegalDocument::create([
            'type' => $source->type,
            'slug' => $source->slug,
            'title' => $source->title,
            'short_description' => $source->short_description,
            'reference_url' => $source->reference_url,
            'content' => $source->content,
            'version' => $this->nextVersion($source->type, $source->version),
            'status' => LegalDocument::STATUS_DRAFT,
            'effective_date' => $source->effective_date,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.forked',
            'resource_type' => 'legal_document',
            'resource_id' => $draft->id,
            'description' => "Forked draft v{$draft->version} from {$source->title} ({$source->slug})",
            'ip_address' => $request->ip(),
        ]);

        return $draft;
    }

    /**
     * Produce an unused version string for the next draft.
     */
    private function nextVersion(string $type, ?string $current): string
    {
        $current = $current ?: '1.0';

        $candidate = $current;
        if (preg_match('/^(\d+)\.(\d+)$/', $current, $m)) {
            $major = (int) $m[1];
            $minor = (int) $m[2];
            $candidate = $major.'.'.($minor + 1);
            $guard = 0;
            while (LegalDocument::where('type', $type)->where('version', $candidate)->exists() && $guard < 500) {
                $minor++;
                $candidate = $major.'.'.($minor + 1);
                $guard++;
            }

            return substr($candidate, 0, 50);
        }

        $suffix = 1;
        $base = substr($current, 0, 45);
        $candidate = $base.'-r'.$suffix;
        while (LegalDocument::where('type', $type)->where('version', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-r'.$suffix;
        }

        return substr($candidate, 0, 50);
    }

    private function isPlaceholderOnly(?string $content): bool
    {
        $text = trim(strip_tags((string) $content));

        return $text === '' || $text === LegalDocument::PLACEHOLDER;
    }

    private function requireAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403, 'Unauthorized.');
    }
}
