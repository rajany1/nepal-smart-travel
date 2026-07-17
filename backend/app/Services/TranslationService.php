<?php

namespace App\Services;

use App\Models\ModelTranslation;
use Illuminate\Support\Str;

class TranslationService
{
    public static function attachToPlaces(array $items, ?string $idPrefix = null): array
    {
        $ids = collect($items)
            ->map(function ($item) use ($idPrefix) {
                $id = $item['id'] ?? null;
                if ($id === null) return null;
                if ($idPrefix !== null && Str::startsWith($id, $idPrefix)) {
                    return (int) substr($id, strlen($idPrefix));
                }
                if (is_numeric($id)) {
                    return (int) $id;
                }
                return null;
            })
            ->filter()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return $items;
        }

        $translations = ModelTranslation::where('translatable_type', 'place')
            ->whereIn('translatable_id', $ids)
            ->where('locale', 'ne')
            ->get()
            ->groupBy('translatable_id');

        foreach ($items as &$item) {
            $id = $item['id'] ?? null;
            if ($id === null) continue;
            $numericId = $idPrefix !== null && Str::startsWith($id, $idPrefix)
                ? (int) substr($id, strlen($idPrefix))
                : (is_numeric($id) ? (int) $id : null);

            if ($numericId && isset($translations[$numericId])) {
                foreach ($translations[$numericId] as $t) {
                    $item[$t->field . '_ne'] = $t->value;
                }
            }
        }

        return $items;
    }

    public static function attachToModel($model, string $type): array
    {
        $data = $model->toArray();

        $translations = ModelTranslation::where('translatable_type', $type)
            ->where('translatable_id', $model->id)
            ->where('locale', 'ne')
            ->get();

        foreach ($translations as $t) {
            $data[$t->field . '_ne'] = $t->value;
        }

        return $data;
    }

    public static function attachToItems(array $items, string $type, string $idKey = 'id'): array
    {
        $ids = collect($items)
            ->pluck($idKey)
            ->filter(fn($id) => $id !== null && is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();

        if (empty($ids)) {
            return $items;
        }

        $translations = ModelTranslation::where('translatable_type', $type)
            ->whereIn('translatable_id', $ids)
            ->where('locale', 'ne')
            ->get()
            ->groupBy('translatable_id');

        foreach ($items as &$item) {
            $id = $item[$idKey] ?? null;
            if ($id === null) continue;
            $numericId = is_numeric($id) ? (int) $id : null;
            if ($numericId && isset($translations[$numericId])) {
                foreach ($translations[$numericId] as $t) {
                    $item[$t->field . '_ne'] = $t->value;
                }
            }
        }

        return $items;
    }
}
