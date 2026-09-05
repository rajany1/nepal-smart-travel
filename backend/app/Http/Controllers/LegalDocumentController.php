<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegalDocumentController extends Controller
{
    /**
     * List all legal documents (admin panel).
     */
    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $documents = LegalDocument::orderByDesc('updated_at')->get();

        $types = LegalDocument::types();

        return view('admin.legal-documents.index', compact('documents', 'types'));
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
     * Store a new legal document.
     */
    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(LegalDocument::types())),
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'version' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
        ]);

        $doc = LegalDocument::create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'version' => $validated['version'] ?? '1.0',
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.created',
            'resource_type' => 'legal_document',
            'resource_id' => $doc->id,
            'description' => "Created legal document: {$doc->title} ({$doc->type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Legal document created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);
        $types = LegalDocument::types();

        return view('admin.legal-documents.edit', compact('document', 'types'));
    }

    /**
     * Update an existing legal document.
     */
    public function update(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'version' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
        ]);

        $wasPublished = $document->is_published;
        $isNowPublished = $validated['is_published'] ?? false;

        $document->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'version' => $validated['version'] ?? $document->version,
            'is_published' => $isNowPublished,
            'published_at' => (!$wasPublished && $isNowPublished) ? now() : $document->published_at,
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.updated',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Updated legal document: {$document->title} ({$document->type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Legal document updated successfully.');
    }

    /**
     * Publish a document.
     */
    public function publish(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);
        $document->update([
            'is_published' => true,
            'published_at' => now(),
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.published',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Published legal document: {$document->title} ({$document->type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Document published successfully.');
    }

    /**
     * Unpublish a document.
     */
    public function unpublish(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);
        $document->update([
            'is_published' => false,
            'last_edited_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.unpublished',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Unpublished legal document: {$document->title} ({$document->type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Document unpublished.');
    }

    /**
     * Delete a legal document.
     */
    public function destroy(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $document = LegalDocument::findOrFail($id);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'legal_document.deleted',
            'resource_type' => 'legal_document',
            'resource_id' => $document->id,
            'description' => "Deleted legal document: {$document->title} ({$document->type})",
            'ip_address' => $request->ip(),
        ]);

        $document->delete();

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Legal document deleted.');
    }

    private function requireAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403, 'Unauthorized.');
    }
}
