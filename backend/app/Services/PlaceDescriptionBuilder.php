<?php

namespace App\Services;

use App\Models\Place;

/**
 * Deterministic Nepali description writer (no LLM). Assembles 2-3 sentence
 * copy from a place's real attributes using per-category sentence templates.
 */
class PlaceDescriptionBuilder
{
    public function build(Place $place): string
    {
        $category = $place->category?->name ?? 'place';
        $catKey = $this->categoryKey($category);

        $name = $place->name ?: 'यो स्थान';
        $district = $place->district ?: 'नेपाल';
        $rating = $place->average_rating;
        $ratingLine = $rating > 0
            ? " हालसम्मको रेटिङ {$rating} रहेको छ।"
            : '';

        $address = $place->address ?: $district;

        $first = match ($catKey) {
            'hotel' => "{$name} {$district}को एक उत्कृष्ट बसाइस्थल हो। आरामदायी कोठा र सौहार्दपूर्ण सेवाका लागि यहाँ आउन सकिन्छ।",
            'restaurant' => "{$name} {$district}को लोकप्रिय खाना गन्तव्य हो। यहाँको स्वादिलो परिकारले पाहुनाको मन जितेको छ।",
            'cafe' => "{$name} {$district}को सुन्दर क्याफे हो। चिया, कफी र हल्का खाजाका लागि यहाँ रमाइलो वातावरण पाइन्छ।",
            'attraction' => "{$name} {$district}को प्रमुख आकर्षणस्थल हो। प्राकृतिक तथा सांस्कृतिक दृष्टिले यो स्थान भ्रमणयोग्य छ।",
            'temple' => "{$name} {$district}को धार्मिक तथा सांस्कृतिक सम्पदा हो। यहाँ भक्तजनहरूको बिहानैदेखि भीड लाग्ने गर्छ।",
            'nature' => "{$name} {$district}को रमणीय प्राकृतिक स्थल हो। हरियाली र शान्त वातावरणले मनै हल्का बनाउँछ।",
            'market' => "{$name} {$district}को हलचल भरिएको बजार हो। स्थानीय उत्पादन र खानपानका सामग्री यहाँ पाइन्छ।",
            default => "{$name} {$district}को एक चिनिने स्थान हो। यहाँको विशेषता जान्न भ्रमण गर्न सकिन्छ।",
        };

        $second = "यो स्थान {$address} मा अवस्थित छ।";

        return $first . ' ' . $second . $ratingLine;
    }

    protected function categoryKey(string $category): string
    {
        $c = mb_strtolower($category);

        if (preg_match('/hotel|lodge|resort|guesthouse|stay|homestay/', $c)) return 'hotel';
        if (preg_match('/restaurant|food|dining|kitchen|bhojan/', $c)) return 'restaurant';
        if (preg_match('/cafe|coffee|tea house/', $c)) return 'cafe';
        if (preg_match('/temple|monastery|gumba|stupa|shrine|church|mosque/', $c)) return 'temple';
        if (preg_match('/nature|park|garden|lake|river|mountain|hill|forest|viewpoint/', $c)) return 'nature';
        if (preg_match('/market|bazaar|haat|mall/', $c)) return 'market';
        if (preg_match('/attraction|landmark|monument|sight|museum|palace/', $c)) return 'attraction';

        return 'attraction';
    }
}
