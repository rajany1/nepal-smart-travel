<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Services\HtmlSanitizer;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

class LegalController extends Controller
{
    /**
     * Canonical display order of the seven legal documents.
     * Unknown/custom slugs are appended alphabetically after these.
     */
    public const ORDER = [
        'terms',
        'privacy',
        'community-guidelines',
        'coins',
        'sos',
        'ai',
        'business-advertising',
    ];

    /**
     * GET /legal — the Legal Center index (published documents only).
     */
    public function index()
    {
        $published = LegalDocument::published()->get();

        $bySlug = [];
        foreach ($published as $doc) {
            $key = $doc->slug ?? $doc->type;
            $existing = $bySlug[$key] ?? null;
            if (
                $existing === null
                || ($doc->published_at?->getTimestamp() ?? 0) > ($existing->published_at?->getTimestamp() ?? 0)
            ) {
                $bySlug[$key] = $doc;
            }
        }

        $documents = collect($bySlug)->sort(function ($a, $b) {
            $ka = $a->slug ?? $a->type;
            $kb = $b->slug ?? $b->type;
            $ia = array_search($ka, self::ORDER, true);
            $ib = array_search($kb, self::ORDER, true);
            $ia = $ia === false ? PHP_INT_MAX : $ia;
            $ib = $ib === false ? PHP_INT_MAX : $ib;
            if ($ia !== $ib) {
                return $ia <=> $ib;
            }

            return strcasecmp($a->title, $b->title);
        })->values();

        return view('web.legal-center', [
            'documents' => $documents,
        ]);
    }

    /**
     * GET /legal/{slug} — a single published document.
     *
     * Accepts the public slug (terms, privacy, ...) or the legacy type
     * identifier (terms_conditions, privacy_policy, ...) so existing links
     * keep working. Drafts, archived, and unknown documents return 404.
     */
    public function show(string $slug)
    {
        $document = LegalDocument::published()
            ->whereSlugOrType($slug)
            ->orderByDesc('published_at')
            ->first();

        abort_unless($document, 404);

        [$content, $toc] = $this->prepareContent($document->content);

        $metaDescription = $document->short_description
            ?: "Read the {$document->title} for the Oripori app.";

        return view('web.legal-page', [
            'document' => $document,
            'content' => $content,
            'toc' => $toc,
            'metaDescription' => $metaDescription,
        ]);
    }

    /**
     * Legacy route: /terms
     */
    public function legacyTerms()
    {
        return $this->show('terms_conditions');
    }

    /**
     * Legacy route: /privacy-policy
     */
    public function legacyPrivacy()
    {
        return $this->show('privacy_policy');
    }

    /**
     * Sanitize content, inject anchor ids into headings, and build a TOC.
     *
     * @return array{0: string, 1: array<int, array{id: string, text: string, level: int}>}
     */
    private function prepareContent(?string $html): array
    {
        $clean = HtmlSanitizer::clean($html);

        if (trim($clean) === '') {
            return ['', []];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $wrapped = '<div id="legal-content">'.$clean.'</div>';
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><meta charset="utf-8">'.$wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = null;
        if ($loaded) {
            foreach ($dom->childNodes as $node) {
                if ($node instanceof DOMElement && $node->tagName === 'div') {
                    $root = $node;
                    break;
                }
            }
        }

        if ($root === null) {
            return [$clean, []];
        }

        $toc = [];
        $used = [];

        foreach ($root->getElementsByTagName('*') as $el) {
            if (! in_array($el->tagName, ['h2', 'h3'], true)) {
                continue;
            }

            $text = trim($el->textContent);
            if ($text === '') {
                continue;
            }

            $base = Str::slug($text);
            if ($base === '') {
                $base = 'section';
            }

            $id = $base;
            $i = 1;
            while (isset($used[$id])) {
                $id = $base.'-'.$i;
                $i++;
            }
            $used[$id] = true;

            $el->setAttribute('id', $id);
            $toc[] = [
                'id' => $id,
                'text' => $text,
                'level' => (int) substr($el->tagName, 1),
            ];
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return [$output, $toc];
    }
}
