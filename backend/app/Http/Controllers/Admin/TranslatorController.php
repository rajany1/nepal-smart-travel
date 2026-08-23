<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranslationGlossary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TranslatorController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) {
            abort(403, 'Unauthorized');
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $search = trim((string) $request->get('search'));
        $query = TranslationGlossary::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('term', 'like', "%{$search}%")
                  ->orWhere('nepali', 'like', "%{$search}%")
                  ->orWhere('context', 'like', "%{$search}%");
            });
        }

        $translations = $query->orderBy('term')->paginate(50)->withQueryString();

        return view('admin.translator', compact('translations', 'search'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'term' => 'required|string|max:255',
            'nepali' => 'required|string|max:255',
            'context' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $existing = TranslationGlossary::where('term', $data['term'])->first();
        if ($existing) {
            $existing->update([
                'nepali' => $data['nepali'],
                'context' => $data['context'] ?? $existing->context,
                'is_active' => $data['is_active'] ?? $existing->is_active,
            ]);
            return redirect()->route('admin.translator')->with('info', "Word '{$data['term']}' already existed — updated instead.");
        }

        TranslationGlossary::create($data);

        return redirect()->route('admin.translator')->with('success', "Word '{$data['term']}' added.");
    }

    public function update(Request $request, TranslationGlossary $translation)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'term' => 'required|string|max:255',
            'nepali' => 'required|string|max:255',
            'context' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $translation->update($data);

        return redirect()->route('admin.translator')->with('success', "Word '{$data['term']}' updated.");
    }

    public function toggle(Request $request, TranslationGlossary $translation)
    {
        $this->requireAdmin($request);

        $translation->update(['is_active' => !$translation->is_active]);

        return redirect()->route('admin.translator')->with('success', $translation->is_active
            ? "Word '{$translation->term}' activated."
            : "Word '{$translation->term}' deactivated.");
    }

    public function destroy(Request $request, TranslationGlossary $translation)
    {
        $this->requireAdmin($request);

        $term = $translation->term;
        $translation->delete();
        \App\Support\LiveFeed::bump('translation_glossary', $translation->id);

        return redirect()->route('admin.translator')->with('success', "Word '{$term}' deleted.");
    }

    public function bulkImport(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate([
            'bulk_text' => 'required|string',
            'bulk_context' => 'nullable|string|max:100',
        ]);

        // One pair per line: English term = Nepali word
        $lines = preg_split('/\r\n|\r|\n/', $request->bulk_text);
        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                $skipped++;
                continue;
            }

            $existing = TranslationGlossary::where('term', $parts[0])->first();
            if ($existing) {
                $existing->update([
                    'nepali' => $parts[1],
                    'context' => $request->bulk_context ?: $existing->context,
                ]);
                $updated++;
            } else {
                TranslationGlossary::create([
                    'term' => $parts[0],
                    'nepali' => $parts[1],
                    'context' => $request->bulk_context,
                ]);
                $added++;
            }
        }

        return redirect()->route('admin.translator')->with('success',
            "Import done: {$added} added, {$updated} updated, {$skipped} skipped.");
    }
}
