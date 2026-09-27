<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'legal_document_id',
        'document_type',
        'document_version',
        'document_hash',
        'accepted_at',
        'app_version',
        'platform',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /**
     * Check if a user has accepted a specific document type at a given version.
     */
    public static function hasAccepted(User $user, string $documentType, ?string $version = null): bool
    {
        $query = static::where('user_id', $user->id)
            ->where('document_type', $documentType);

        if ($version !== null) {
            $query->where('document_version', $version);
        }

        return $query->exists();
    }

    /**
     * Check if a user needs to re-accept because a newer version exists.
     */
    public static function needsReAcceptance(User $user, string $documentType): bool
    {
        $latest = LegalDocument::where('type', $documentType)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->first();

        if (!$latest) {
            return false;
        }

        return !static::where('user_id', $user->id)
            ->where('document_type', $documentType)
            ->where('document_version', $latest->version)
            ->exists();
    }
}
