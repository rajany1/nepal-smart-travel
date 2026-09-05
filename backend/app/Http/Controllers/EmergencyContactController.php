<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmergencyContact;
use App\Models\User;

class EmergencyContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = EmergencyContact::where('user_id', $request->user()->id)
            ->with('contactUser:id,name,avatar,phone')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'contact_user_id' => 'nullable|exists:users,id',
            'relationship' => 'nullable|string|max:50',
        ]);

        $count = EmergencyContact::where('user_id', $request->user()->id)->count();
        if ($count >= 10) {
            return response()->json([
                'message' => 'Maximum 10 emergency contacts allowed.',
            ], 422);
        }

        // Auto-match: phone number halda user table ma match gara
        $contactUserId = $validated['contact_user_id'] ?? null;
        if (!$contactUserId && !empty($validated['phone_number'])) {
            $matchedUser = self::findUserByPhone($validated['phone_number']);
            if ($matchedUser) {
                $contactUserId = $matchedUser->id;
            }
        }

        $contact = EmergencyContact::create([
            'user_id' => $request->user()->id,
            'contact_user_id' => $contactUserId,
            'name' => $validated['name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'relationship' => $validated['relationship'] ?? null,
            'is_verified' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $contact->load('contactUser:id,name,avatar,phone'),
            'message' => 'Emergency contact added.',
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $contact = EmergencyContact::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'contact_user_id' => 'nullable|exists:users,id',
            'relationship' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        // Auto-match: phone number change vaye re-match gara
        if (array_key_exists('phone_number', $validated)) {
            $contactUserId = $validated['contact_user_id'] ?? null;
            if (!$contactUserId && !empty($validated['phone_number'])) {
                $matchedUser = self::findUserByPhone($validated['phone_number']);
                if ($matchedUser) {
                    $contactUserId = $matchedUser->id;
                }
            } else {
                $contactUserId = $validated['contact_user_id'] ?? $contact->contact_user_id;
            }
            $validated['contact_user_id'] = $contactUserId;
        }

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'data' => $contact->fresh()->load('contactUser:id,name,avatar,phone'),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $contact = EmergencyContact::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact removed.',
        ]);
    }

    /**
     * Check if a phone number belongs to a registered user.
     * GET /emergency-contacts/check-phone/{phone}
     */
    public function checkPhone(Request $request, string $phone)
    {
        $user = self::findUserByPhone($phone);

        if ($user) {
            return response()->json([
                'success' => true,
                'found' => true,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);
        }

        return response()->json([
            'success' => true,
            'found' => false,
        ]);
    }

    /**
     * Find a user by phone number (handles multiple formats).
     */
    private static function findUserByPhone(string $phone): ?User
    {
        // Normalize: remove spaces, dashes, parentheses
        $clean = preg_replace('/[\s\-\(\)]/', '', trim($phone));

        // Try exact match first
        $user = User::where('phone', $clean)->first();
        if ($user) return $user;

        // Try with country code variations
        $variations = [$clean];
        if (str_starts_with($clean, '977')) {
            $variations[] = '+' . $clean;
            $variations[] = substr($clean, 3);
        } elseif (strlen($clean) === 10 && str_starts_with($clean, '9')) {
            $variations[] = '+977' . $clean;
            $variations[] = '977' . $clean;
        } elseif (str_starts_with($clean, '+977')) {
            $variations[] = substr($clean, 4);
            $variations[] = '977' . substr($clean, 4);
        }

        return User::whereIn('phone', $variations)->first();
    }
}
