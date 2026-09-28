<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Audit Statement — Oripori Nepal</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.5;
        }

        .audit-container { max-width: 210mm; margin: 0 auto; padding: 15mm; }

        /* ── Header ── */
        .audit-header {
            text-align: center;
            border-bottom: 3px solid #009688;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .audit-header h1 {
            font-size: 20px;
            font-weight: 800;
            color: #004D40;
            letter-spacing: -0.5px;
        }
        .audit-header .subtitle {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        .audit-header .nepal-text {
            font-size: 11px;
            color: #009688;
            font-weight: 600;
        }
        .audit-meta {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #888;
            margin-top: 8px;
            border-top: 1px solid #eee;
            padding-top: 6px;
        }

        /* ── Sections ── */
        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #004D40;
            background: linear-gradient(135deg, #E0F2F1, #f0fdfa);
            padding: 6px 10px;
            border-left: 4px solid #009688;
            margin-bottom: 8px;
            border-radius: 0 4px 4px 0;
        }
        .section-title .nepali { color: #00796B; font-weight: 500; }

        /* ── Data Grid ── */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
        }
        .data-grid.three-col { grid-template-columns: repeat(3, 1fr); }
        .data-grid.four-col { grid-template-columns: repeat(4, 1fr); }

        .data-card {
            background: #fafafa;
            border: 1px solid #e8e8e8;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .data-card.highlight { border-color: #009688; background: #f0fdfa; }
        .data-card.warning { border-color: #f59e0b; background: #fffbeb; }
        .data-card.danger { border-color: #ef4444; background: #fef2f2; }

        .data-label {
            font-size: 9px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .data-value {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin-top: 2px;
        }
        .data-value.green { color: #16a34a; }
        .data-value.red { color: #dc2626; }
        .data-value.blue { color: #2563eb; }
        .data-value.purple { color: #9333ea; }
        .data-value.teal { color: #0d9488; }
        .data-sub {
            font-size: 9px;
            color: #999;
            margin-top: 1px;
        }

        /* ── Table ── */
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .audit-table th {
            background: #f1f5f9;
            font-weight: 600;
            text-align: left;
            padding: 5px 8px;
            border-bottom: 2px solid #ddd;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #555;
        }
        .audit-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #eee;
        }
        .audit-table tr:nth-child(even) { background: #fafafa; }
        .audit-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .audit-table .total-row { font-weight: 700; background: #E0F2F1 !important; }

        /* ── Status Badges ── */
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }

        /* ── Summary Box ── */
        .summary-box {
            border: 2px solid #009688;
            border-radius: 8px;
            padding: 12px;
            background: linear-gradient(135deg, #f0fdfa, #fff);
            margin-top: 12px;
        }
        .summary-box h3 {
            font-size: 12px;
            color: #004D40;
            margin-bottom: 8px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        /* ── Reconciliation ── */
        .recon-pass { color: #16a34a; font-weight: 700; }
        .recon-fail { color: #dc2626; font-weight: 700; }
        .recon-unknown { color: #d97706; font-weight: 700; }

        /* ── Footer ── */
        .audit-footer {
            margin-top: 20px;
            border-top: 2px solid #009688;
            padding-top: 10px;
            text-align: center;
            font-size: 9px;
            color: #888;
        }
        .audit-footer .signature-line {
            display: inline-block;
            width: 200px;
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 4px;
            font-size: 10px;
            color: #333;
        }

        /* ── Print Controls ── */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        .print-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .print-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .print-btn-primary { background: #009688; color: white; }
        .print-btn-secondary { background: #fff; color: #333; border: 1px solid #ddd; }

        /* ── Page Break ── */
        .page-break { page-break-before: always; }

        /* ── Print Styles ── */
        @media print {
            .print-controls { display: none !important; }
            body { background: white; font-size: 10px; }
            .audit-container { padding: 0; max-width: 100%; }
            .section { page-break-inside: avoid; }
            .no-print { display: none !important; }
        }

        @page {
            size: A4;
            margin: 12mm;
        }
    </style>
</head>
<body>

<div class="print-controls no-print">
    <button class="print-btn print-btn-secondary" onclick="window.close()">
        <i class="fas fa-times"></i> Close
    </button>
    <button class="print-btn print-btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print / PDF
    </button>
</div>

<div class="audit-container">

    {{-- ═══════════ HEADER ═══════════ --}}
    <div class="audit-header">
        <div class="nepal-text">नेपाल स्मार्ट ट्राभल एण्ड लोकल इन्टेलिजेन्स प्लाटफर्म</div>
        <h1>ORIPORI FINANCIAL AUDIT STATEMENT</h1>
        <div class="subtitle">Comprehensive Financial Overview & Reconciliation Report</div>
        <div class="audit-meta">
            <span>Generated: {{ $now->format('F d, Y — h:i A NPT') }}</span>
            <span>BS Date: {{ $now->format('Y-m-d') }} | Report ID: AUDIT-{{ $now->format('Ymd-His') }}</span>
            <span>Coin-to-NPR Rate: {{ number_format($coinToNpr, 2) }}</span>
        </div>
    </div>

    {{-- ═══════════ 1. PLATFORM OVERVIEW ═══════════ --}}
    <div class="section">
        <div class="section-title">1. Platform Overview <span class="nepali">/ प्लाटफर्म अवलोकन</span></div>
        <div class="data-grid four-col">
            <div class="data-card">
                <div class="data-label">Total Users</div>
                <div class="data-value">{{ number_format($totalUsers) }}</div>
                <div class="data-sub">{{ number_format($activeUsers) }} active</div>
            </div>
            <div class="data-card">
                <div class="data-label">Travel Partners</div>
                <div class="data-value teal">{{ number_format($totalPartners) }}</div>
                <div class="data-sub">{{ number_format($activePartners) }} active</div>
            </div>
            <div class="data-card">
                <div class="data-label">Reports</div>
                <div class="data-value blue">{{ number_format($totalReports) }}</div>
                <div class="data-sub">{{ number_format($approvedReports) }} approved</div>
            </div>
            <div class="data-card">
                <div class="data-label">Places</div>
                <div class="data-value purple">{{ number_format($totalPlaces) }}</div>
            </div>
        </div>
    </div>

    {{-- ═══════════ 2. USER COIN SYSTEM ═══════════ --}}
    <div class="section">
        <div class="section-title">2. User Coin System <span class="nepali">/ कोइन प्रणाली</span></div>
        <div class="data-grid">
            <div class="data-card highlight">
                <div class="data-label">Total Coins Outstanding</div>
                <div class="data-value green">{{ number_format($totalCoinsIssued, 2) }} Coins</div>
                <div class="data-sub">NPR Equivalent: Rs. {{ number_format($totalCoinNprValue, 2) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Total Lifetime Earned</div>
                <div class="data-value">{{ number_format($totalCoinsEarned, 2) }} Coins</div>
                <div class="data-sub">Across {{ number_format($totalCoinWallets) }} wallets</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th class="num">Coins</th>
                    <th class="num">NPR Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Impression Earnings</td>
                    <td>{{ number_format($coinTransactionsCount) }} total txns</td>
                    <td class="num">{{ number_format($impressionEarnings, 2) }}</td>
                    <td class="num">Rs. {{ number_format($impressionEarnings * $coinToNpr, 2) }}</td>
                </tr>
                <tr>
                    <td>Click Earnings</td>
                    <td>—</td>
                    <td class="num">{{ number_format($clickEarnings, 2) }}</td>
                    <td class="num">Rs. {{ number_format($clickEarnings * $coinToNpr, 2) }}</td>
                </tr>
                <tr>
                    <td>Admin Adjustments</td>
                    <td>—</td>
                    <td class="num {{ $adminAdjustments >= 0 ? '' : 'text-red-600' }}">{{ number_format($adminAdjustments, 2) }}</td>
                    <td class="num">Rs. {{ number_format($adminAdjustments * $coinToNpr, 2) }}</td>
                </tr>
                <tr>
                    <td>Automatic Reversals (invalid rewards)</td>
                    <td>{{ number_format($reversalCount) }} reversals</td>
                    <td class="num {{ $reversalTotal >= 0 ? '' : 'text-red-600' }}">{{ number_format($reversalTotal, 2) }}</td>
                    <td class="num">Rs. {{ number_format($reversalTotal * $coinToNpr, 2) }}</td>
                </tr>
                <tr>
                    <td>Offer Redemptions (deducted)</td>
                    <td>—</td>
                    <td class="num">{{ number_format($redemptionDeductions, 2) }}</td>
                    <td class="num">Rs. {{ number_format($redemptionDeductions * $coinToNpr, 2) }}</td>
                </tr>
                <tr>
                    <td>Withdrawals (deducted)</td>
                    <td>{{ number_format($completedWithdrawals) }} completed</td>
                    <td class="num">{{ number_format($totalCoinsWithdrawn, 2) }}</td>
                    <td class="num">Rs. {{ number_format($totalCoinsWithdrawn * $coinToNpr, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Net Outstanding Balance</td>
                    <td></td>
                    <td class="num">{{ number_format($totalCoinsIssued, 2) }}</td>
                    <td class="num">Rs. {{ number_format($totalCoinNprValue, 2) }}</td>
                </tr>
            </tbody>
        </table>
        @if($recentReversals->isNotEmpty())
            <div style="margin-top:12px">
                <div style="font-weight:600; margin-bottom:4px">Recent Automatic Reversals</div>
                <div style="font-size:12px; opacity:.75; margin-bottom:6px">
                    Compensating transactions for invalid rewards. Original transactions are preserved;
                    reversals are automatic and distinct from admin adjustments.
                </div>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Reversal</th>
                            <th>User</th>
                            <th class="num">Amount</th>
                            <th>Reason</th>
                            <th>Original Reward</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentReversals as $reversal)
                            <tr>
                                <td>#{{ $reversal->id }}</td>
                                <td>{{ $reversal->user?->name ?? '—' }}</td>
                                <td class="num text-red-600">{{ number_format($reversal->amount, 2) }}</td>
                                <td>{{ $reversal->type }}</td>
                                <td>
                                    @if($reversal->reverses)
                                        #{{ $reversal->reverses->id }} &mdash; {{ $reversal->reverses->type }}
                                        ({{ number_format($reversal->reverses->amount, 2) }})
                                        @if(!empty($reversal->metadata['original_reward_event_id']))
                                            <br><small>reward event #{{ $reversal->metadata['original_reward_event_id'] }}</small>
                                        @endif
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>{{ $reversal->created_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══════════ 3. PARTNER WALLET ═══════════ --}}
    <div class="section">
        <div class="section-title">3. Partner Wallet System <span class="nepali">/ पार्टनर वालेट</span></div>
        <div class="data-grid">
            <div class="data-card highlight">
                <div class="data-label">Total Partner Balance</div>
                <div class="data-value teal">Rs. {{ number_format($totalPartnerBalance, 2) }}</div>
                <div class="data-sub">Across {{ number_format($totalPartnerWallets) }} wallets</div>
            </div>
            <div class="data-card">
                <div class="data-label">Total Partner Earned</div>
                <div class="data-value">Rs. {{ number_format($totalPartnerEarned, 2) }}</div>
                <div class="data-sub">Total Withdrawn: Rs. {{ number_format($totalPartnerWithdrawn, 2) }}</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead>
                <tr><th>Source</th><th class="num">Count</th><th class="num">Revenue</th><th class="num">Commission</th><th class="num">Net to Partner</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>QR Payments</td>
                    <td class="num">{{ number_format($partnerPaymentsCount) }}</td>
                    <td class="num">Rs. {{ number_format($partnerPaymentsRevenue, 2) }}</td>
                    <td class="num">Rs. {{ number_format($partnerPaymentsCommission, 2) }}</td>
                    <td class="num">Rs. {{ number_format($partnerPaymentsNet, 2) }}</td>
                </tr>
                <tr>
                    <td>Offer Redemptions</td>
                    <td class="num">{{ number_format($usedRedemptions) }}</td>
                    <td class="num">Rs. {{ number_format($totalOfferValue, 2) }}</td>
                    <td class="num">Rs. {{ number_format($totalAdminCommission, 2) }}</td>
                    <td class="num">Rs. {{ number_format($totalPartnerEarnings, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Partner Wallet Total</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="num">Rs. {{ number_format($totalPartnerBalance, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ PAGE BREAK ═══════════ --}}
    <div class="page-break"></div>

    {{-- ═══════════ 4. WITHDRAWALS ═══════════ --}}
    <div class="section">
        <div class="section-title">4. User Withdrawals <span class="nepali">/ कोइन निकासी</span></div>
        <div class="data-grid four-col">
            <div class="data-card">
                <div class="data-label">Total Requests</div>
                <div class="data-value">{{ number_format($totalWithdrawals) }}</div>
            </div>
            <div class="data-card" style="border-color:#f59e0b">
                <div class="data-label">Pending</div>
                <div class="data-value" style="color:#d97706">{{ number_format($pendingWithdrawals) }}</div>
                <div class="data-sub">Rs. {{ number_format($pendingWithdrawalAmount, 2) }}</div>
            </div>
            <div class="data-card" style="border-color:#2563eb">
                <div class="data-label">Processing</div>
                <div class="data-value blue">{{ number_format($processingWithdrawals) }}</div>
            </div>
            <div class="data-card" style="border-color:#16a34a">
                <div class="data-label">Completed</div>
                <div class="data-value green">{{ number_format($completedWithdrawals) }}</div>
                <div class="data-sub">Rs. {{ number_format($completedWithdrawalAmount, 2) }}</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead><tr><th>Status</th><th class="num">Count</th><th class="num">Amount (NPR)</th><th class="num">Amount (Coins)</th></tr></thead>
            <tbody>
                <tr><td><span class="badge badge-yellow">Pending</span></td><td class="num">{{ number_format($pendingWithdrawals) }}</td><td class="num">Rs. {{ number_format($pendingWithdrawalAmount, 2) }}</td><td class="num">{{ number_format($pendingWithdrawalAmount / max($coinToNpr, 0.01), 2) }}</td></tr>
                <tr><td><span class="badge badge-blue">Processing</span></td><td class="num">{{ number_format($processingWithdrawals) }}</td><td class="num">Rs. {{ number_format($processingWithdrawalAmount, 2) }}</td><td class="num">—</td></tr>
                <tr><td><span class="badge badge-green">Completed</span></td><td class="num">{{ number_format($completedWithdrawals) }}</td><td class="num">Rs. {{ number_format($completedWithdrawalAmount, 2) }}</td><td class="num">{{ number_format($completedWithdrawalAmount / max($coinToNpr, 0.01), 2) }}</td></tr>
                <tr><td><span class="badge badge-red">Rejected</span></td><td class="num">{{ number_format($rejectedWithdrawals) }}</td><td class="num">Rs. {{ number_format($rejectedWithdrawalAmount, 2) }}</td><td class="num">—</td></tr>
                <tr class="total-row"><td>TOTAL</td><td class="num">{{ number_format($totalWithdrawals) }}</td><td class="num">Rs. {{ number_format($totalWithdrawalAmount, 2) }}</td><td class="num">{{ number_format($totalWithdrawalAmount / max($coinToNpr, 0.01), 2) }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 5. PARTNER WITHDRAWALS ═══════════ --}}
    <div class="section">
        <div class="section-title">5. Partner Withdrawals <span class="nepali">/ पार्टनर निकासी</span></div>
        <div class="data-grid four-col">
            <div class="data-card">
                <div class="data-label">Total Requests</div>
                <div class="data-value">{{ number_format($totalPartnerWithdrawals) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Pending</div>
                <div class="data-value" style="color:#d97706">{{ number_format($pendingPartnerWithdrawals) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Paid</div>
                <div class="data-value green">{{ number_format($paidPartnerWithdrawals) }}</div>
                <div class="data-sub">Rs. {{ number_format($paidPartnerWithdrawalAmount, 2) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Rejected</div>
                <div class="data-value red">{{ number_format($rejectedPartnerWithdrawals) }}</div>
            </div>
        </div>
    </div>

    {{-- ═══════════ 6. AD REVENUE ═══════════ --}}
    <div class="section">
        <div class="section-title">6. Advertising Revenue <span class="nepali">/ विज्ञापन राजस्व</span></div>
        <div class="data-grid">
            <div class="data-card highlight">
                <div class="data-label">Total Ad Revenue (Gross)</div>
                <div class="data-value green">Rs. {{ number_format($adRevenueGross, 2) }}</div>
                <div class="data-sub">Admin: Rs. {{ number_format($adRevenueAdminShare, 2) }} | User: Rs. {{ number_format($adRevenueUserShare, 2) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Total Ad Payments Received</div>
                <div class="data-value">Rs. {{ number_format($totalAdPayments, 2) }}</div>
                <div class="data-sub">{{ number_format($activeAdCampaigns) }} active / {{ number_format($totalAdCampaigns) }} total campaigns</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead><tr><th>Metric</th><th class="num">Count / Amount</th></tr></thead>
            <tbody>
                <tr><td>Total Impressions Tracked</td><td class="num">{{ number_format($totalAdImpressions) }}</td></tr>
                <tr><td>Total Clicks Tracked</td><td class="num">{{ number_format($totalAdClicks) }}</td></tr>
                <tr><td>Click-Through Rate</td><td class="num">{{ $totalAdImpressions > 0 ? number_format(($totalAdClicks / $totalAdImpressions) * 100, 2) . '%' : '0.00%' }}</td></tr>
                <tr><td>Revenue per Impression</td><td class="num">Rs. {{ $totalAdImpressions > 0 ? number_format($adRevenueGross / $totalAdImpressions, 4) : '0.00' }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 7. OFFERS & REDEMPTIONS ═══════════ --}}
    <div class="section">
        <div class="section-title">7. Offers & Redemptions <span class="nepali">/ अफर र रिडेम्सन</span></div>
        <div class="data-grid three-col">
            <div class="data-card">
                <div class="data-label">Total Offers Created</div>
                <div class="data-value">{{ number_format($totalOffers) }}</div>
                <div class="data-sub">{{ number_format($activeOffers) }} active</div>
            </div>
            <div class="data-card">
                <div class="data-label">Total Redemptions</div>
                <div class="data-value blue">{{ number_format($totalRedemptions) }}</div>
                <div class="data-sub">{{ number_format($claimedRedemptions) }} claimed / {{ number_format($usedRedemptions) }} used</div>
            </div>
            <div class="data-card highlight">
                <div class="data-label">Total Offer Value</div>
                <div class="data-value green">Rs. {{ number_format($totalOfferValue, 2) }}</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead><tr><th>Financial Breakdown</th><th class="num">Amount (NPR)</th><th class="num">% of Total</th></tr></thead>
            <tbody>
                <tr><td>Total Offer Value</td><td class="num">Rs. {{ number_format($totalOfferValue, 2) }}</td><td class="num">100%</td></tr>
                <tr><td>Admin Commission</td><td class="num">Rs. {{ number_format($totalAdminCommission, 2) }}</td><td class="num">{{ $totalOfferValue > 0 ? number_format(($totalAdminCommission / $totalOfferValue) * 100, 1) : '0' }}%</td></tr>
                <tr><td>Partner Earnings</td><td class="num">Rs. {{ number_format($totalPartnerEarnings, 2) }}</td><td class="num">{{ $totalOfferValue > 0 ? number_format(($totalPartnerEarnings / $totalOfferValue) * 100, 1) : '0' }}%</td></tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 8. BOOKINGS & SUBSCRIPTIONS ═══════════ --}}
    <div class="section">
        <div class="section-title">8. Bookings & Subscriptions <span class="nepali">/ बुकिङ र सदस्यता</span></div>
        <div class="data-grid">
            <div class="data-card">
                <div class="data-label">Booking Revenue</div>
                <div class="data-value">Rs. {{ number_format($totalBookingAmount, 2) }}</div>
                <div class="data-sub">{{ number_format($totalBookings) }} total / {{ number_format($confirmedBookings) }} confirmed</div>
            </div>
            <div class="data-card">
                <div class="data-label">Booking Payments Received</div>
                <div class="data-value green">Rs. {{ number_format($bookingPaymentsSuccess, 2) }}</div>
                <div class="data-sub">Commission Earned: Rs. {{ number_format($totalBookingCommission, 2) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Subscription Revenue</div>
                <div class="data-value teal">Rs. {{ number_format($subscriptionRevenue, 2) }}</div>
                <div class="data-sub">{{ number_format($activeSubscriptions) }} active / {{ number_format($totalSubscriptions) }} total</div>
            </div>
            <div class="data-card">
                <div class="data-label">Featured Listing Revenue</div>
                <div class="data-value purple">Rs. {{ number_format($featuredPayments, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- ═══════════ PAGE BREAK ═══════════ --}}
    <div class="page-break"></div>

    {{-- ═══════════ 9. PLATFORM EXPENSES ═══════════ --}}
    <div class="section">
        <div class="section-title">9. Platform Expenses <span class="nepali">/ खर्च</span></div>
        <div class="data-grid three-col">
            <div class="data-card">
                <div class="data-label">Total Expenses</div>
                <div class="data-value red">Rs. {{ number_format($totalExpenses, 2) }}</div>
            </div>
            <div class="data-card">
                <div class="data-label">Paid Expenses</div>
                <div class="data-value green">Rs. {{ number_format($paidExpenses, 2) }}</div>
            </div>
            <div class="data-card warning">
                <div class="data-label">Unpaid Expenses</div>
                <div class="data-value" style="color:#d97706">Rs. {{ number_format($unpaidExpenses, 2) }}</div>
            </div>
        </div>
        <table class="audit-table" style="margin-top:6px">
            <thead><tr><th>Category</th><th class="num">Amount (NPR)</th></tr></thead>
            <tbody>
                <tr><td>Employee Salaries</td><td class="num">Rs. {{ number_format($totalSalaries, 2) }}</td></tr>
                <tr><td>— Paid</td><td class="num">Rs. {{ number_format($paidSalaries, 2) }}</td></tr>
                <tr><td>— Pending</td><td class="num">Rs. {{ number_format($pendingSalaries, 2) }}</td></tr>
                <tr><td>Other Platform Expenses</td><td class="num">Rs. {{ number_format($totalExpenses - $totalSalaries, 2) }}</td></tr>
                <tr class="total-row"><td>Total Outflow</td><td class="num">Rs. {{ number_format($totalExpenses + $totalSalaries, 2) }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 10. MONTHLY TREND ═══════════ --}}
    <div class="section">
        <div class="section-title">10. Monthly Trend <span class="nepali">/ मासिक प्रवृत्ति</span></div>
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="num">Coins Earned</th>
                    <th class="num">Offers Claimed</th>
                    <th class="num">Offer Value</th>
                    <th class="num">Withdrawals</th>
                    <th class="num">Ad Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyTrend as $m)
                <tr>
                    <td>{{ $m['month'] }}</td>
                    <td class="num">{{ number_format($m['coins_earned'], 2) }}</td>
                    <td class="num">{{ number_format($m['offers_claimed']) }}</td>
                    <td class="num">Rs. {{ number_format($m['offers_value'], 2) }}</td>
                    <td class="num">Rs. {{ number_format($m['withdrawals'], 2) }}</td>
                    <td class="num">Rs. {{ number_format($m['ad_revenue'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 11. COIN SETTINGS ═══════════ --}}
    <div class="section">
        <div class="section-title">11. Coin System Configuration <span class="nepali">/ कोइन सेटिङ</span></div>
        <table class="audit-table">
            <thead><tr><th>Setting</th><th>Key</th><th class="num">Value</th></tr></thead>
            <tbody>
                <tr><td>Impression Value</td><td><code>impression_value</code></td><td class="num">{{ number_format($impressionValue, 2) }} Coins</td></tr>
                <tr><td>Click Value</td><td><code>click_value</code></td><td class="num">{{ number_format($clickValue, 2) }} Coins</td></tr>
                <tr><td>User Share</td><td><code>user_share_percent</code></td><td class="num">{{ number_format($userSharePercent, 0) }}%</td></tr>
                <tr><td>Admin Share</td><td><code>admin_share_percent</code></td><td class="num">{{ number_format($adminSharePercent, 0) }}%</td></tr>
                <tr><td>Coin to NPR Rate</td><td><code>coin_to_npr_rate</code></td><td class="num">1 Coin = Rs. {{ number_format($coinToNpr, 2) }}</td></tr>
                <tr><td>Min Withdrawal</td><td><code>min_withdrawal_amount</code></td><td class="num">{{ number_format($minWithdrawal, 0) }} Coins</td></tr>
                <tr><td>Max Withdrawal</td><td><code>max_withdrawal_amount</code></td><td class="num">{{ number_format($maxWithdrawal, 0) }} Coins</td></tr>
                <tr><td>Daily Earning Cap</td><td><code>daily_earning_cap</code></td><td class="num">{{ number_format($dailyEarningCap, 0) }} Coins</td></tr>
                <tr><td>Impression Cooldown</td><td><code>impression_cooldown_minutes</code></td><td class="num">{{ number_format($impressionCooldown) }} min</td></tr>
            </tbody>
        </table>
    </div>

    {{-- ═══════════ 12. RECONCILIATION ═══════════ --}}
    <div class="section">
        <div class="section-title">12. Financial Reconciliation <span class="nepali">/ हिसाब मिलान</span></div>
        <table class="audit-table">
            <thead><tr><th>Check</th><th class="num">Wallet Sum</th><th class="num">Ledger Sum</th><th class="num">Drift</th><th>Status</th></tr></thead>
            <tbody>
                <tr>
                    <td>User Coin Wallets</td>
                    <td class="num">{{ number_format($walletSumBalance, 2) }}</td>
                    <td class="num">{{ number_format($coinTxnSum, 2) }}</td>
                    <td class="num {{ abs($coinDrift) < 0.01 ? 'recon-pass' : 'recon-fail' }}">{{ number_format($coinDrift, 2) }}</td>
                    <td>{{ abs($coinDrift) < 0.01 ? '✅ PASS' : '⚠️ DRIFT' }}</td>
                </tr>
                <tr>
                    <td>Partner Wallets</td>
                    <td class="num">{{ number_format($partnerWalletSum, 2) }}</td>
                    <td class="num recon-unknown">N/A</td>
                    <td class="num recon-unknown">—</td>
                    <td class="recon-unknown">⏳ NO LEDGER</td>
                </tr>
            </tbody>
        </table>
        @if(abs($coinDrift) >= 0.01)
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px;margin-top:6px;font-size:10px;color:#991b1b">
            <strong>⚠️ Reconciliation Alert:</strong> User coin wallet balance drift of {{ number_format($coinDrift, 2) }} coins detected.
            This indicates either missing ledger entries or duplicate credits. Immediate investigation required.
        </div>
        @endif
    </div>

    {{-- ═══════════ SUMMARY BOX ═══════════ --}}
    <div class="summary-box">
        <h3>📊 Financial Summary — वित्तीय सारांश</h3>
        <div class="summary-grid">
            <div>
                <div class="data-label">Total Platform Revenue</div>
                <div class="data-value green" style="font-size:14px">
                    Rs. {{ number_format(
                        $adRevenueAdminShare + $totalAdminCommission + $totalBookingCommission +
                        $subscriptionRevenue + $featuredPayments + $totalAdPayments, 2
                    ) }}
                </div>
            </div>
            <div>
                <div class="data-label">Total Platform Outflow</div>
                <div class="data-value red" style="font-size:14px">
                    Rs. {{ number_format(
                        $completedWithdrawalAmount + $paidPartnerWithdrawalAmount + $adRevenueUserShare +
                        $totalPartnerEarnings + $totalExpenses + $totalSalaries, 2
                    ) }}
                </div>
            </div>
            <div>
                <div class="data-label">Net Position</div>
                @php
                    $revenue = $adRevenueAdminShare + $totalAdminCommission + $totalBookingCommission + $subscriptionRevenue + $featuredPayments + $totalAdPayments;
                    $outflow = $completedWithdrawalAmount + $paidPartnerWithdrawalAmount + $adRevenueUserShare + $totalPartnerEarnings + $totalExpenses + $totalSalaries;
                    $net = $revenue - $outflow;
                @endphp
                <div class="data-value {{ $net >= 0 ? 'green' : 'red' }}" style="font-size:14px">
                    Rs. {{ number_format($net, 2) }}
                </div>
                <div class="data-sub">{{ $net >= 0 ? 'Surplus' : 'Deficit' }}</div>
            </div>
        </div>
    </div>

    {{-- ═══════════ FOOTER ═══════════ --}}
    <div class="audit-footer">
        <div>This is an automated financial audit statement generated by the Oripori Platform.</div>
        <div>यो ओरिपोरी प्लाटफर्मद्वारा स्वचालित रूपमा उत्पादन गरिएको वित्तीय ऑडिट विवरण हो।</div>
        <div class="signature-line">
            Authorized Signature / हस्ताक्षर
        </div>
    </div>

</div>

<script>
    // Auto-print after 1 second (optional — uncomment if needed)
    // setTimeout(() => window.print(), 1000);
</script>

</body>
</html>
