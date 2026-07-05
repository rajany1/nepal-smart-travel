<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SponsorController extends Controller
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

        $sponsors = Sponsor::orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('admin.sponsors', compact('sponsors'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'website' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        if (blank($data['website']) && (blank($data['latitude']) || blank($data['longitude']))) {
            return back()->withErrors(['latitude' => 'Location (latitude & longitude) is required when no website is provided.'])->withInput();
        }

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|mimes:jpg,jpeg,png,webp|max:2048']);
            $data['logo'] = $request->file('logo')->store('sponsors', 'public');
        }

        Sponsor::create($data);

        return redirect()->route('admin.sponsors')
            ->with('success', 'Sponsor created successfully.');
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'website' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        if (blank($data['website']) && (blank($data['latitude']) || blank($data['longitude']))) {
            return back()->withErrors(['latitude' => 'Location (latitude & longitude) is required when no website is provided.'])->withInput();
        }

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|mimes:jpg,jpeg,png,webp|max:2048']);
            $data['logo'] = $request->file('logo')->store('sponsors', 'public');
        }

        $sponsor->update($data);

        return redirect()->route('admin.sponsors')
            ->with('success', 'Sponsor updated successfully.');
    }

    public function destroy(Request $request, Sponsor $sponsor)
    {
        $this->requireAdmin($request);

        if ($sponsor->shopItems()->exists()) {
            return redirect()->route('admin.sponsors')
                ->with('error', 'Cannot delete sponsor with active shop items. Remove or reassign items first.');
        }

        $sponsor->delete();

        return redirect()->route('admin.sponsors')
            ->with('success', 'Sponsor deleted.');
    }
}
