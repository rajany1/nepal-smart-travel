<?php

namespace Database\Seeders;

use App\Models\TranslationGlossary;
use Illuminate\Database\Seeder;

class TranslationGlossarySeeder extends Seeder
{
    /**
     * Curated common tourism/community terms used by the rules-based
     * GlossaryTranslator. Admins can add more rows over time.
     */
    public function run(): void
    {
        $terms = [
            // travel & places
            ['hotel', 'होटल', 'place'],
            ['lodge', 'लज', 'place'],
            ['resort', 'रिसोर्ट', 'place'],
            ['guesthouse', 'गेष्टहाउस', 'place'],
            ['restaurant', 'रेस्टुरेन्ट', 'food'],
            ['cafe', 'क्याफे', 'food'],
            ['coffee shop', 'कफी पसल', 'food'],
            ['temple', 'मन्दिर', 'place'],
            ['monastery', 'गुम्बा', 'place'],
            ['stupa', 'स्तूप', 'place'],
            ['museum', 'सङ्ग्रहालय', 'place'],
            ['park', 'पार्क', 'place'],
            ['garden', 'बगैंचा', 'place'],
            ['lake', 'ताल', 'nature'],
            ['river', 'नदी', 'nature'],
            ['waterfall', 'झरना', 'nature'],
            ['mountain', 'पहाड', 'nature'],
            ['hill', 'डाँडा', 'nature'],
            ['trek', 'पदयात्रा', 'activity'],
            ['trail', 'पदमार्ग', 'activity'],
            ['adventure', 'साहसिक', 'activity'],
            ['market', 'बजार', 'place'],
            ['city', 'सहर', 'general'],
            ['village', 'गाउँ', 'general'],
            ['district', 'जिल्ला', 'general'],
            ['airport', 'विमानस्थल', 'transport'],
            ['bus stop', 'बस स्टप', 'transport'],
            ['station', 'स्टेसन', 'transport'],
            ['road', 'सडक', 'general'],
            ['bridge', 'पुल', 'general'],

            // reports & community
            ['landslide', 'पहिरो', 'report'],
            ['flood', 'बाढी', 'report'],
            ['fire', 'आगलागी', 'report'],
            ['accident', 'दुर्घटना', 'report'],
            ['road damage', 'सडक क्षति', 'report'],
            ['garbage', 'फोहोर', 'report'],
            ['waste', 'फोहोर', 'report'],
            ['electricity', 'बिजुली', 'report'],
            ['power cut', 'लोडसेडिङ', 'report'],
            ['water', 'पानी', 'report'],
            ['outage', 'कटौती', 'report'],
            ['emergency', 'आपतकालीन', 'report'],
            ['hospital', 'अस्पताल', 'report'],
            ['police', 'प्रहरी', 'report'],
            ['missing person', 'हराएको व्यक्ति', 'report'],
            ['alert', 'सूचना', 'report'],

            // food & services
            ['food', 'खाना', 'food'],
            ['breakfast', 'खाजा', 'food'],
            ['lunch', 'खाना', 'food'],
            ['dinner', 'बेलुकाको खाना', 'food'],
            ['tea', 'चिया', 'food'],
            ['coffee', 'कफी', 'food'],
            ['booking', 'बुकिङ', 'service'],
            ['reservation', 'आरक्षण', 'service'],
            ['price', 'मूल्य', 'service'],
            ['cost', 'लागत', 'service'],
            ['open', 'खुला', 'service'],
            ['closed', 'बन्द', 'service'],
            ['entry fee', 'प्रवेश शुल्क', 'service'],
            ['ticket', 'टिकट', 'service'],
            ['guide', 'गाइड', 'service'],
            ['tour', 'भ्रमण', 'activity'],
            ['hiking', 'पदयात्रा', 'activity'],
            ['rafting', 'र्याफ्टिङ', 'activity'],
            ['paragliding', 'प्याराग्लाइडिङ', 'activity'],
            ['bungee', 'बन्जी', 'activity'],

            // cities, districts & famous places
            ['kathmandu', 'काठमाडौं', 'place'],
            ['pokhara', 'पोखरा', 'place'],
            ['lalitpur', 'ललितपुर', 'place'],
            ['patan', 'पाटन', 'place'],
            ['bhaktapur', 'भक्तपुर', 'place'],
            ['kirtipur', 'कीर्तिपुर', 'place'],
            ['thamel', 'ठमेल', 'place'],
            ['chitwan', 'चितवन', 'place'],
            ['dhading', 'धादिङ', 'place'],
            ['kaski', 'कास्की', 'place'],
            ['gorkha', 'गोरखा', 'place'],
            ['lamjung', 'लमजुङ', 'place'],
            ['tanahun', 'तनहुँ', 'place'],
            ['syangja', 'स्याङ्जा', 'place'],
            ['myagdi', 'म्याग्दी', 'place'],
            ['baglung', 'बागलुङ', 'place'],
            ['mustang', 'मुस्ताङ', 'place'],
            ['manang', 'मनाङ', 'place'],
            ['solukhumbu', 'सोलुखुम्बु', 'place'],
            ['sankhuwasabha', 'सङ्खुवासभा', 'place'],
            ['dolakha', 'दोलखा', 'place'],
            ['sindhupalchok', 'सिन्धुपाल्चोक', 'place'],
            ['kavre', 'काभ्रेपलान्चोक', 'place'],
            ['makwanpur', 'मकवानपुर', 'place'],
            ['lumbini', 'लुम्बिनी', 'place'],
            ['janakpur', 'जनकपुर', 'place'],
            ['rupandehi', 'रूपन्देही', 'place'],
            ['nawalparasi', 'नवलपरासी', 'place'],
            ['banke', 'बाँके', 'place'],
            ['bardiya', 'बर्दिया', 'place'],
            ['kailali', 'कैलाली', 'place'],
            ['kanchanpur', 'कञ्चनपुर', 'place'],
            ['surkhet', 'सुर्खेत', 'place'],
            ['dang', 'दाङ', 'place'],
            ['palpa', 'पाल्पा', 'place'],
            ['gulmi', 'गुल्मी', 'place'],
            ['jhapa', 'झापा', 'place'],
            ['morang', 'मोरङ', 'place'],
            ['sunsari', 'सुनसरी', 'place'],
            ['ilam', 'इलाम', 'place'],
            ['dhankuta', 'धनकुटा', 'place'],
            ['namche', 'नाम्चे', 'place'],
            ['lukla', 'लुक्ला', 'place'],
            ['jomsom', 'जोमसोम', 'place'],
            ['muktinath', 'मुक्तिनाथ', 'place'],
            ['nagarkot', 'नागार्कोट', 'place'],
            ['dhulikhel', 'धुलिखेल', 'place'],
            ['bandipur', 'बन्दीपुर', 'place'],
            ['rara', 'रारा', 'place'],
            ['phewa', 'फेवा', 'place'],
            ['tilicho', 'तिलिचो', 'place'],
            ['gosaikunda', 'गोसाइँकुण्डा', 'place'],
            ['langtang', 'लाङ्टाङ', 'place'],
            ['annapurna', 'अन्नपूर्ण', 'place'],
            ['everest', 'सगरमाथा', 'place'],
            ['himalaya', 'हिमालय', 'place'],
            ['pasupatinath', 'पशुपतिनाथ', 'place'],
            ['pashupatinath', 'पशुपतिनाथ', 'place'],
            ['swayambhu', 'स्वयम्भू', 'place'],
            ['bouddha', 'बौद्ध', 'place'],
            ['budhanilkantha', 'बुढानीलकण्ठ', 'place'],
            ['changu narayan', 'चाँगुनारायण', 'place'],
        ];

        foreach ($terms as [$term, $nepali, $context]) {
            TranslationGlossary::updateOrCreate(
                ['term' => $term],
                ['nepali' => $nepali, 'context' => $context, 'is_active' => true]
            );
        }
    }
}
