<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\OfferRedemption;

class DashboardController extends Controller
{
    public function index()
    {
        $partner = auth()->user()->business;
        $offers = $partner->offers()->orderBy('created_at', 'desc')->limit(5)->get();

        $stats = [
            'total_offers' => $partner->offers()->count(),
            'active_offers' => $partner->offers()->where('status', 'approved')->count(),
            'pending_offers' => $partner->offers()->where('status', 'pending')->count(),
            'total_claims' => OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->count(),
            'used_claims' => OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->where('status', 'used')->count(),
            'ads_impressions' => (int) $partner->adCampaigns()->sum('current_impressions'),
            'ads_clicks' => (int) $partner->adCampaigns()->sum('current_clicks'),
            'ads_spent' => (float) $partner->adCampaigns()->sum('spent_amount'),
            'ads_paid' => (float) $partner->adCampaigns()->sum('paid_amount'),
            'offer_claims' => OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->count(),
            'offer_used' => OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->where('status', 'used')->count(),
            'offer_earned' => (float) OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->where('status', 'used')->sum('partner_earnings'),
            'offer_commission' => (float) OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->where('status', 'used')->sum('admin_commission'),
            'payout_balance' => $partner->payoutBalance(),
            'payout_paid' => (float) $partner->payouts()->where('status', 'paid')->sum('amount'),
            'payout_pending' => (float) $partner->payouts()->where('status', 'pending')->sum('amount'),
        ];
        $stats['ads_spent'] = round($stats['ads_spent'], 2);
        $stats['ads_paid'] = round($stats['ads_paid'], 2);
        $stats['offer_earned'] = round($stats['offer_earned'], 2);
        $stats['offer_commission'] = round($stats['offer_commission'], 2);
        $stats['payout_balance'] = round($stats['payout_balance'], 2);
        $stats['payout_paid'] = round($stats['payout_paid'], 2);
        $stats['payout_pending'] = round($stats['payout_pending'], 2);

        return view('partner.dashboard', compact('partner', 'offers', 'stats'));
    }
}
