<?php

namespace App\Http\Controllers;

use App\Models\LegalDocumentType;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class LegalDocumentTypeController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $types = LegalDocumentType::orderBy('sort_order')->get();

        return view('admin.legal-document-types.index', compact('types'));
    }

    public function create(Request $request)
    {
        $this->requireAdmin($request);

        return view('admin.legal-document-types.create');
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'slug' => 'required|string|max:100|regex:/^[a-z_]+$/|unique:legal_document_types,slug',
            'label' => 'required|string|max:150',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $type = LegalDocumentType::create([
            'slug' => $validated['slug'],
            'label' => $validated['label'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'legal_document_type.created',
            'resource_type' => 'legal_document_type',
            'resource_id' => $type->id,
            'description' => "Created document type: {$type->label} ({$type->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.legal-document-types.index')
            ->with('success', 'Document type created successfully.');
    }

    public function edit(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $type = LegalDocumentType::findOrFail($id);

        return view('admin.legal-document-types.edit', compact('type'));
    }

    public function update(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $type = LegalDocumentType::findOrFail($id);

        $validated = $request->validate([
            'label' => 'required|string|max:150',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $type->update([
            'label' => $validated['label'],
            'is_active' => $validated['is_active'] ?? $type->is_active,
            'sort_order' => $validated['sort_order'] ?? $type->sort_order,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'legal_document_type.updated',
            'resource_type' => 'legal_document_type',
            'resource_id' => $type->id,
            'description' => "Updated document type: {$type->label} ({$type->slug})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.legal-document-types.index')
            ->with('success', 'Document type updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $type = LegalDocumentType::findOrFail($id);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'legal_document_type.deleted',
            'resource_type' => 'legal_document_type',
            'resource_id' => $type->id,
            'description' => "Deleted document type: {$type->label} ({$type->slug})",
            'ip_address' => $request->ip(),
        ]);

        $type->delete();

        return redirect()->route('admin.legal-document-types.index')
            ->with('success', 'Document type deleted.');
    }

    private function requireAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403, 'Unauthorized.');
    }
}
