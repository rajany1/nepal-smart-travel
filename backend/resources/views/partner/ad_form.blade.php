@extends('partner.layout')
@section('title', ($adCampaign->exists ?? false) ? 'Edit Ad Campaign' : 'New Ad Campaign')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('partner.ads') }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 grid place-items-center text-slate-500 hover:bg-slate-50"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ $adCampaign->exists ? 'Edit Ad Campaign' : 'New Ad Campaign' }}</h2>
<p class="text-sm text-slate-500">Submit for admin approval - you pay a budget via eSewa/Khalti once approved (billed per view &amp; click from the budget).</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3"><div class="bg-white rounded-2xl shadow border border-slate-100 p-6">
            <form method="POST" action="{{ $adCampaign->exists ? route('partner.ads.update', $adCampaign) : route('partner.ads.store') }}" class="space-y-5">
            @csrf
            @if($adCampaign->exists) @method('PUT') @endif

            @if(!empty($adCampaign) && $adCampaign->status === 'rejected' && $adCampaign->rejection_reason)
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-times-circle"></i> Rejected: {{ $adCampaign->rejection_reason }}
                </div>
            @endif

<div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Company</label>
                <input type="text" value="{{ $partner->name ?? 'Your business' }}" disabled class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-500">
                <p class="text-xs text-slate-400 mt-1">The campaign is published under your verified business.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Campaign Name</label>
                <input type="text" name="name" required maxlength="255" value="{{ old('name', $adCampaign->name ?? '') }}" placeholder="e.g. Monsoon Trekking Gear Sale" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Ad Type</label>
                <select name="ad_type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="banner" @selected(old('ad_type', $adCampaign->ad_type ?? 'banner') === 'banner')>Banner</option>
                    <option value="promoted_place" @selected(old('ad_type', $adCampaign->ad_type ?? '') === 'promoted_place')>Promoted Place</option>
                    <option value="sponsored_card" @selected(old('ad_type', $adCampaign->ad_type ?? '') === 'sponsored_card')>Sponsored Card</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Ad message</label>
                <textarea name="content" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Short, catchy message shown to travelers...">{{ old('content', $adCampaign->content ?? '') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Image URL (optional)</label>
                <input type="text" name="image" value="{{ old('image', $adCampaign->image ?? '') }}" placeholder="https://..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Link when tapped (optional)</label>
                <input type="text" name="target_url" value="{{ old('target_url', $adCampaign->target_url ?? '') }}" placeholder="https://your-site.com" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target District (optional)</label>
                    <input type="text" name="target_district" value="{{ old('target_district', $adCampaign->target_district ?? '') }}" placeholder="e.g. Kathmandu" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Category (optional)</label>
                    <select name="target_category" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">Any category</option>
                        @foreach(['restaurant', 'hotel', 'trekking', 'tour', 'transport', 'shop', 'cafe', 'activity', 'attraction'] as $cat)
                            <option value="{{ $cat }}" @selected(old('target_category', $adCampaign->target_category ?? '') === $cat)>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Show on screens</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @php $checked = old('contexts', $adCampaign->contexts ?? []); @endphp
                    @foreach(['home' => 'Home', 'explore' => 'Explore & Map', 'nearby' => 'Nearby Places', 'place_detail' => 'Place Details', 'report' => 'Report Screen', 'hotels' => 'Hotels', 'restaurants' => 'Restaurants', 'attractions' => 'Attractions', 'cafes' => 'Cafes', 'activities' => 'Activities'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-600 bg-slate-50 rounded-lg px-3 py-2 cursor-pointer">
                            <input type="checkbox" name="contexts[]" value="{{ $key }}" @checked(in_array($key, (array) $checked)) class="rounded accent-teal-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-1">Leave unchecked to show everywhere.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
<div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Budget (Rs.) <span class="text-red-500">*</span></label>
                    <input type="number" name="budget" min="100" step="0.01" required value="{{ old('budget', $adCampaign->budget ?? 100) }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-slate-400 mt-1">You pay this via eSewa/Khalti after approval. Campaign auto-pauses when the budget is spent (Rs. 50 per 1,000 views + Rs. 10 per click). Minimum Rs. 100.</p>
                    @error('budget')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Max Impressions (0 = unlimited)</label>
                    <input type="number" name="max_impressions" min="0" value="{{ old('max_impressions', $adCampaign->max_impressions ?? 0) }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at', $adCampaign->starts_at ? $adCampaign->starts_at->format('Y-m-d') : '') }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">End Date (optional)</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $adCampaign->ends_at ? $adCampaign->ends_at->format('Y-m-d') : '') }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('partner.ads') }}" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</a>
                <button type="submit" class="px-5 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition">Submit for Approval</button>
            </div>
        </form>
        </div>
        </div>

<div class="lg:col-span-2">
            <div class="lg:sticky lg:top-6 space-y-4">
                <div class="bg-primary-900 text-white rounded-2xl shadow p-5">
                    <h3 class="font-semibold text-sm flex items-center gap-2"><i class="fas fa-mobile-alt text-accent-400"></i> Live Preview</h3>
                    <p class="text-xs text-teal-100 mt-1">How your ad will look in the app. Shows demo content first, then your real details as you type.</p>
                </div>

                <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
                    <div class="mx-auto max-w-[290px]">
                        <div class="rounded-[2rem] border-[6px] border-slate-800 overflow-hidden bg-slate-50 shadow-xl">
                            <div class="bg-slate-800 h-5 flex items-center justify-center"><span class="w-16 h-1.5 bg-slate-600 rounded-full"></span></div>
                            <div class="p-3 space-y-3">
                                <div class="flex items-center justify-between text-[9px] text-slate-400"><span>9:41</span><span class="flex items-center gap-1"><i class="fas fa-signal"></i><i class="fas fa-wifi"></i><i class="fas fa-battery-full"></i></span></div>
                                <div class="bg-slate-100 rounded-lg h-16 flex items-center justify-center"><i class="fas fa-map-marked-alt text-slate-300 text-xl"></i></div>

                                <div class="flex items-center gap-1.5 pt-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                                    <span class="text-[7px] font-bold uppercase tracking-widest text-slate-400">Home screen - Banner</span>
                                </div>

                                <div id="pv-banner" class="ad-variant relative overflow-hidden rounded-xl bg-gradient-to-r from-teal-700 to-primary-700 text-white">
                                    <div class="absolute top-1.5 right-1.5 text-[7px] font-bold uppercase tracking-wider bg-black/30 px-1.5 py-0.5 rounded">Sponsored</div>
                                    <div class="px-3.5 py-4">
                                        <p id="pv-banner-company" class="text-[8px] uppercase tracking-widest text-teal-200">Himalayan Treks &amp; Tours</p>
                                        <p id="pv-banner-name" class="font-bold text-[13px] mt-0.5 leading-snug">Monsoon Trekking Gear Sale</p>
                                        <p id="pv-banner-content" class="text-[9px] text-teal-50 mt-1 leading-snug overflow-hidden" style="max-height: 2.6em;">Up to 30% off trekking gear this monsoon. Visit our Thamel store today!</p>
                                    </div>
                                </div>
                                <p id="pv-banner-contexts" class="text-[8px] text-slate-400 px-1 -mt-1">Shown on: Everywhere</p>

                                <div class="flex items-center gap-1.5 pt-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                                    <span class="text-[7px] font-bold uppercase tracking-widest text-slate-400">Nearby &amp; Explore - Promoted Place</span>
                                </div>

                                <div id="pv-place" class="ad-variant bg-white rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="relative">
                                        <div id="pv-place-imgph" class="h-24 bg-gradient-to-br from-teal-600 to-primary-800 grid place-items-center"><i class="fas fa-store text-white/70 text-2xl"></i></div>
                                        <img id="pv-place-img" class="hidden h-24 w-full object-cover" src="" alt="">
                                        <span class="absolute top-1.5 right-1.5 text-[7px] font-bold uppercase tracking-wider bg-black/50 text-white px-1.5 py-0.5 rounded">Promoted</span>
                                    </div>
                                    <div class="p-3">
                                        <div class="flex items-center justify-between">
                                            <p id="pv-place-name" class="font-bold text-[12px] text-slate-800">Monsoon Trekking Gear Sale</p>
                                            <span class="text-[8px] text-amber-500"><i class="fas fa-star"></i> 4.6</span>
                                        </div>
                                        <p id="pv-place-content" class="text-[9px] text-slate-500 mt-1 leading-snug overflow-hidden" style="max-height: 3.6em;">Up to 30% off trekking gear this monsoon. Visit our Thamel store today!</p>
                                    </div>
                                </div>
                                <p id="pv-place-contexts" class="text-[8px] text-slate-400 px-1 -mt-1">Shown on: Everywhere</p>

                                <div class="flex items-center gap-1.5 pt-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                                    <span class="text-[7px] font-bold uppercase tracking-widest text-slate-400">Hotels, Restaurants &amp; More - Sponsored Card</span>
                                </div>

                                <div id="pv-card" class="ad-variant bg-white rounded-xl border border-slate-200 p-3">
                                    <div class="relative">
                                        <div id="pv-card-imgph" class="h-20 rounded-lg bg-gradient-to-br from-teal-600 to-primary-800 grid place-items-center"><i class="fas fa-store text-white/70 text-xl"></i></div>
                                        <img id="pv-card-img" class="hidden h-20 w-full rounded-lg object-cover" src="" alt="">
                                        <span class="absolute top-1 right-1 text-[7px] font-bold uppercase tracking-wider bg-black/50 text-white px-1.5 py-0.5 rounded">Sponsored</span>
                                    </div>
                                    <p id="pv-card-company" class="text-[8px] uppercase tracking-widest text-teal-700 mt-2">Himalayan Treks &amp; Tours</p>
                                    <p id="pv-card-name" class="font-bold text-[12px] text-slate-800 mt-0.5">Monsoon Trekking Gear Sale</p>
                                    <p id="pv-card-content" class="text-[9px] text-slate-500 mt-1 leading-snug overflow-hidden" style="max-height: 3em;">Up to 30% off trekking gear this monsoon. Visit our Thamel store today!</p>
                                </div>
                                <p id="pv-card-contexts" class="text-[8px] text-slate-400 px-1 -mt-1">Shown on: Everywhere</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var demoCompany = @json($partner->name ?? 'Your Business');
    var demoName = "Monsoon Trekking Gear Sale";
    var demoContent = "Up to 30% off trekking gear this monsoon. Visit our Thamel store today!";

    var screenMap = {
        banner: ['home'],
        promoted_place: ['explore', 'nearby', 'place_detail', 'report'],
        sponsored_card: ['hotels', 'restaurants', 'attractions', 'cafes', 'activities']
    };
    var screenLabels = {
        home: 'Home', explore: 'Explore & Map', nearby: 'Nearby Places',
        place_detail: 'Place Details', report: 'Report', hotels: 'Hotels',
        restaurants: 'Restaurants', attractions: 'Attractions',
        cafes: 'Cafes', activities: 'Activities'
    };

    var fName = document.querySelector('[name="name"]');
    var fContent = document.querySelector('[name="content"]');
    var fImage = document.querySelector('[name="image"]');
    var fType = document.querySelector('[name="ad_type"]');
    var fContexts = Array.prototype.slice.call(document.querySelectorAll('[name="contexts[]"]'));

    function val(input, fallback) {
        return input && input.value.trim() !== '' ? input.value.trim() : fallback;
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function applyImage(variant) {
        var img = document.getElementById(variant === 'promoted_place' ? 'pv-place-img' : 'pv-card-img');
        var ph = document.getElementById(variant === 'promoted_place' ? 'pv-place-imgph' : 'pv-card-imgph');
        var url = fImage ? fImage.value.trim() : '';
        if (url) {
            img.src = url;
            img.classList.remove('hidden');
            ph.classList.add('hidden');
            img.onerror = function () {
                img.classList.add('hidden');
                ph.classList.remove('hidden');
            };
        } else {
            img.classList.add('hidden');
            ph.classList.remove('hidden');
        }
    }

    function updateContexts() {
        var checked = fContexts.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        var any = checked.length === 0;
        Object.keys(screenMap).forEach(function (variant) {
            var el = document.getElementById('pv-' + variant + '-contexts');
            if (!el) return;
            var shown = any ? screenMap[variant].slice() : screenMap[variant].filter(function (s) { return checked.indexOf(s) !== -1; });
            el.textContent = shown.length
                ? 'Shown on: ' + shown.map(function (s) { return screenLabels[s]; }).join(', ')
                : 'Not shown on any selected screen';
        });
    }

    function render() {
        var company = demoCompany;
        var name = val(fName, demoName);
        var content = val(fContent, demoContent);
        var type = fType ? fType.value : 'banner';

        setText('pv-banner-company', company);
        setText('pv-banner-name', name);
        setText('pv-banner-content', content);

        setText('pv-place-name', name);
        setText('pv-place-content', content);

        setText('pv-card-company', company);
        setText('pv-card-name', name);
        setText('pv-card-content', content);

        applyImage('promoted_place');
        applyImage('card');

        ['banner', 'promoted_place', 'sponsored_card'].forEach(function (v) {
            var el = document.getElementById('pv-' + v);
            if (!el) return;
            if (v === type) {
                el.classList.add('ring-2', 'ring-teal-500', 'shadow-md');
            } else {
                el.classList.remove('ring-2', 'ring-teal-500', 'shadow-md');
            }
        });

        updateContexts();
    }

    [fName, fContent, fImage, fType].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', render);
        if (el.tagName === 'SELECT') el.addEventListener('change', render);
    });
    fContexts.forEach(function (el) { el.addEventListener('change', updateContexts); });

    render();
});
</script>
@endsection
