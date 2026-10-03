<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Your Account - Oripori</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding-top: var(--header-height); }
        @include('web.partials.site_chrome_css')
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .card { background: white; border-radius: 12px; padding: 40px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { font-size: 28px; margin-bottom: 8px; color: #1a1a1a; }
        h2 { font-size: 20px; margin: 24px 0 12px; color: #1a1a1a; }
        p { margin-bottom: 16px; color: #555; }
        ul, ol { margin: 12px 0 16px 24px; }
        li { margin-bottom: 8px; color: #555; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .warning strong { color: #856404; }
        .retention { background: #fef3f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .retention strong { color: #991b1b; }
        .contact { background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .steps { background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 16px 0; }
        .steps ol { margin-left: 20px; }
        .steps li { margin-bottom: 12px; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .footer { text-align: center; margin-top: 40px; color: #888; font-size: 14px; }
        .footer a { color: #0066cc; }
    </style>
</head>
<body>
    @include('web.partials.site_header')
    <div class="container">
        <div class="card">
            <h1>Delete Your Oripori Account</h1>
            <p>This page explains how to delete your Oripori account and what happens to your data.</p>

            <div class="warning">
                <strong>Important:</strong> Account deletion is permanent and cannot be undone. Please read this page carefully before proceeding.
            </div>

            <h2>How to Delete Your Account</h2>
            <div class="steps">
                <ol>
                    <li>Open the Oripori app on your device</li>
                    <li>Go to <strong>Profile</strong> &rarr; <strong>Settings</strong></li>
                    <li>Scroll down and tap <strong>Delete Account</strong></li>
                    <li>Type your email address to confirm</li>
                    <li>Tap <strong>Delete</strong></li>
                </ol>
            </div>
            <p>If you cannot access your account, you can request deletion by contacting us at <a href="mailto:support@nepalsmarttravel.com">support@nepalsmarttravel.com</a> from the email address associated with your account. We will verify your identity before processing.</p>

            <h2>What Data Is Deleted</h2>
            <p>When you delete your account, the following data is permanently removed:</p>
            <ul>
                <li>Profile information (name, email, phone, avatar, bio, preferences)</li>
                <li>Authentication tokens and active login sessions</li>
                <li>Social account connections (Google, etc.)</li>
                <li>Push notification tokens</li>
                <li>Experience points (XP), level, and achievement records</li>
                <li>Active subscription</li>
                <li>Emergency contacts</li>
                <li>SOS alert history and associated reports</li>
                <li>Support conversations and messages</li>
                <li>AI assistant chat history</li>
                <li>Offer redemptions</li>
                <li>Ad impressions, clicks, and fraud detection records</li>
                <li>Place reviews and place corrections</li>
                <li>Report images/media files (deleted from storage)</li>
                <li>Report comments, reactions, and confirmations</li>
                <li>Moderation records (violations, strikes, queue entries)</li>
                <li>Idempotency keys</li>
            </ul>

            <div class="retention">
                <strong>What Is Retained (Anonymized):</strong>
                <p style="margin-top: 8px; margin-bottom: 0;">Some data is retained in anonymized form as required by law, financial audit requirements, and platform integrity:</p>
                <ul>
                    <li><strong>Reports:</strong> Your reports remain visible with an anonymized author ("Deleted User") to preserve community value and historical information.</li>
                    <li><strong>Coin transactions:</strong> Financial ledger records are retained (anonymized) for audit compliance. Transaction descriptions are prefixed with "[deleted]".</li>
                    <li><strong>Withdrawal records:</strong> Withdrawal history is retained (account details cleared) for financial audit compliance.</li>
                    <li><strong>Payment transactions:</strong> Payment records are retained (personal details cleared) for financial audit compliance.</li>
                    <li><strong>Ad revenue events:</strong> Revenue attribution records are retained (metadata cleared) for financial audit.</li>
                    <li><strong>Audit logs:</strong> System audit logs are retained for compliance purposes.</li>
                    <li><strong>Legal acceptances:</strong> Records of your acceptance of terms and privacy policy may be retained for legal compliance.</li>
                </ul>
            </div>

            <h2>Wallet and Coins</h2>
            <p>Before deleting your account, we strongly recommend withdrawing any remaining Oripori Coins balance. Account deletion will zero your wallet balance. Any pending withdrawal requests may be cancelled during the deletion process.</p>

            <h2>Pending Transactions and Bookings</h2>
            <p>If you have pending withdrawals, active bookings, or an active premium subscription at the time of deletion:</p>
            <ul>
                <li><strong>Pending withdrawals:</strong> May be cancelled during deletion. We recommend completing or cancelling withdrawals before deleting your account.</li>
                <li><strong>Active bookings:</strong> Existing bookings will be orphaned from your account. The business partner will still have the booking record.</li>
                <li><strong>Premium subscription:</strong> Your active subscription will be cancelled. No further charges will be made.</li>
            </ul>

            <h2>Processing</h2>
            <p>Account deletion is processed immediately upon confirmation. Your personal data is anonymized in a single database transaction. Media files (images) are deleted from cloud storage. Some cached content and CDN purges may take additional time to propagate.</p>

            <h2>Legal Basis</h2>
            <p>We retain anonymized records based on our legitimate interest in maintaining platform integrity, financial audit compliance, and community safety. For more details, see our <a href="/legal/privacy_policy">Privacy Policy</a> and <a href="/legal/terms_conditions">Terms of Use</a>.</p>

            <div class="contact">
                <strong>Questions about account deletion?</strong><br>
                Contact us at <a href="mailto:support@nepalsmarttravel.com">support@nepalsmarttravel.com</a>
            </div>
        </div>
    </div>
    @include('web.partials.site_footer')
    @include('web.partials.site_nav_js')
</body>
</html>
