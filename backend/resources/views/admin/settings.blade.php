@extends('admin.layout')
@section('title', 'Settings')

@section('content')
    <div class="space-y-6">
        <!-- XP Settings -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 max-w-3xl">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">XP Settings</h2>

            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700">Report Approval XP</label>
                    <input type="number" name="report_approval_xp" value="{{ old('report_approval_xp', $settings['report_approval_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                    @error('report_approval_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Alert Post XP</label>
                    <input type="number" name="alert_post_xp" value="{{ old('alert_post_xp', $settings['alert_post_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                    @error('alert_post_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Review XP</label>
                    <input type="number" name="review_xp" value="{{ old('review_xp', $settings['review_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                    @error('review_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Place Submit XP</label>
                    <input type="number" name="place_submit_xp" value="{{ old('place_submit_xp', $settings['place_submit_xp']) }}" min="0" max="1000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                    @error('place_submit_xp')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="border-t border-slate-200 pt-5">
                    <h3 class="text-base font-semibold text-slate-900 mb-1">Rewards & Ads</h3>
                    <p class="text-sm text-slate-500 mb-4">Offer XP price = discount value x ratio. Ad spend = impressions x CPM / 1000 + clicks x CPC (paid from partner budgets). Offer commission % is taken from each redemption value.</p>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">XP per NPR ratio (offer price)</label>
                            <input type="number" name="xp_per_npr_ratio" value="{{ old('xp_per_npr_ratio', $settings['xp_per_npr_ratio']) }}" step="0.01" min="0.01" max="100" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('xp_per_npr_ratio')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">CPM (Rs. per 1,000 impressions)</label>
                            <input type="number" name="ad_cpm" value="{{ old('ad_cpm', $settings['ad_cpm']) }}" min="0" max="100000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('ad_cpm')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">CPC (Rs. per click)</label>
                            <input type="number" name="ad_cpc" value="{{ old('ad_cpc', $settings['ad_cpc']) }}" step="0.01" min="0" max="100000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('ad_cpc')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
<div>
                            <label class="block text-sm font-medium text-slate-700">Offer Commission (%)</label>
                            <input type="number" name="offer_commission_percent" value="{{ old('offer_commission_percent', $settings['offer_commission_percent']) }}" step="0.1" min="0" max="100" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('offer_commission_percent')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-5">
                    <h3 class="text-base font-semibold text-slate-900 mb-1">Payout Minimums (Rs.)</h3>
                    <p class="text-sm text-slate-500 mb-4">Partners can only request payouts of at least the minimum for the chosen payment method.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">eSewa Minimum</label>
                            <input type="number" name="payout_min_esewa" value="{{ old('payout_min_esewa', $settings['payout_min_esewa']) }}" min="0" max="1000000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('payout_min_esewa')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Khalti Minimum</label>
                            <input type="number" name="payout_min_khalti" value="{{ old('payout_min_khalti', $settings['payout_min_khalti']) }}" min="0" max="1000000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('payout_min_khalti')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Bank Transfer Minimum</label>
                            <input type="number" name="payout_min_bank" value="{{ old('payout_min_bank', $settings['payout_min_bank']) }}" min="0" max="1000000" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('payout_min_bank')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-5">
                    <h3 class="text-base font-semibold text-slate-900 mb-1">Payment Gateways</h3>
                    <p class="text-sm text-slate-500 mb-4">Configure gateway credentials here — switch sandbox/live or change keys without touching code.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="border border-slate-200 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-slate-800 mb-3"><i class="fas fa-credit-card text-slate-500 mr-1"></i> eSewa</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Merchant Code</label>
                                    <input type="text" name="gateway_esewa_merchant_code" value="{{ old('gateway_esewa_merchant_code', $settings['gateway_esewa_merchant_code']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                                    @error('gateway_esewa_merchant_code')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Secret Key</label>
                                    <input type="password" name="gateway_esewa_secret_key" value="{{ old('gateway_esewa_secret_key', $settings['gateway_esewa_secret_key']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                                    @error('gateway_esewa_secret_key')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Environment</label>
                                    <select name="gateway_esewa_sandbox" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                        <option value="1" @selected((int) old('gateway_esewa_sandbox', $settings['gateway_esewa_sandbox']) === 1)>Sandbox (test)</option>
                                        <option value="0" @selected((int) old('gateway_esewa_sandbox', $settings['gateway_esewa_sandbox']) === 0)>Live</option>
                                    </select>
                                    @error('gateway_esewa_sandbox')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="border border-slate-200 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-slate-800 mb-3"><i class="fas fa-credit-card text-slate-500 mr-1"></i> Khalti</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Secret Key</label>
                                    <input type="password" name="gateway_khalti_secret_key" value="{{ old('gateway_khalti_secret_key', $settings['gateway_khalti_secret_key']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                                    @error('gateway_khalti_secret_key')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Public Key</label>
                                    <input type="text" name="gateway_khalti_public_key" value="{{ old('gateway_khalti_public_key', $settings['gateway_khalti_public_key']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                                    @error('gateway_khalti_public_key')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Environment</label>
                                    <select name="gateway_khalti_sandbox" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                        <option value="1" @selected((int) old('gateway_khalti_sandbox', $settings['gateway_khalti_sandbox']) === 1)>Sandbox (test)</option>
                                        <option value="0" @selected((int) old('gateway_khalti_sandbox', $settings['gateway_khalti_sandbox']) === 0)>Live</option>
                                    </select>
                                    @error('gateway_khalti_sandbox')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-bold text-slate-800 mb-1"><i class="fas fa-shield-halved text-red-500 mr-2"></i>Content Safety (Review AI Agent)</h3>
                    <p class="text-sm text-slate-500 mb-4">How the 24/7 agent escalates repeat offenders: offense #N within the window → warning / suspension / permanent block.</p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Warning after offense #</label>
                            <input type="number" min="1" max="10" name="safety_warn_at_strikes" value="{{ old('safety_warn_at_strikes', $settings['safety_warn_at_strikes']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('safety_warn_at_strikes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Suspend after # offenses</label>
                            <input type="number" min="2" max="10" name="safety_suspend_at_strikes" value="{{ old('safety_suspend_at_strikes', $settings['safety_suspend_at_strikes']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('safety_suspend_at_strikes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Block after # offenses</label>
                            <input type="number" min="3" max="10" name="safety_block_at_strikes" value="{{ old('safety_block_at_strikes', $settings['safety_block_at_strikes']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('safety_block_at_strikes')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Suspension duration (hours)</label>
                            <input type="number" min="1" max="720" name="safety_suspend_hours" value="{{ old('safety_suspend_hours', $settings['safety_suspend_hours']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('safety_suspend_hours')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Strike window (days)</label>
                            <input type="number" min="7" max="365" name="safety_strike_window_days" value="{{ old('safety_strike_window_days', $settings['safety_strike_window_days']) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('safety_strike_window_days')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">AI sweep</label>
                            <select name="safety_ai_enabled" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                <option value="1" @selected((int) old('safety_ai_enabled', $settings['safety_ai_enabled']) === 1)>Enabled (24/7 sweep)</option>
                                <option value="0" @selected((int) old('safety_ai_enabled', $settings['safety_ai_enabled']) === 0)>Disabled</option>
                            </select>
                            @error('safety_ai_enabled')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-xl text-slate-700 bg-white hover:bg-slate-50">Cancel</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-sm font-medium text-white rounded-xl hover:bg-primary-700">Save settings</button>
                </div>
            </form>
        </div>
    </div>
@endsection
