import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';

class LegalDocumentScreen extends StatefulWidget {
  final String type;
  
  const LegalDocumentScreen({super.key, required this.type});

  @override
  State<LegalDocumentScreen> createState() => _LegalDocumentScreenState();
}

class _LegalDocumentScreenState extends State<LegalDocumentScreen> {
  String? _content;
  String? _title;
  bool _isLoading = true;
  bool _hasError = false;
  late WebViewController _webViewController;

  @override
  void initState() {
    super.initState();
    _webViewController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white);
    _loadDocument();
  }

  Future<void> _loadDocument() async {
    setState(() {
      _isLoading = true;
      _hasError = false;
    });

    try {
      final apiClient = ApiClient.instance;
      final response = await apiClient.dio.get('/legal/${widget.type}');
      
      if (response.statusCode == 200 && response.data['success'] == true) {
        setState(() {
          _content = response.data['data']['content'];
          _title = response.data['data']['title'];
          _isLoading = false;
        });
        _loadHtml(_content ?? '');
      } else {
        _loadFallbackContent();
      }
    } catch (e) {
      _loadFallbackContent();
    }
  }

  void _loadFallbackContent() {
    String title;
    String content;
    
    switch (widget.type) {
      case 'privacy_policy':
        title = 'Privacy Policy';
        content = _getDefaultPrivacyPolicy();
        break;
      case 'terms_conditions':
        title = 'Terms & Conditions';
        content = _getDefaultTerms();
        break;
      case 'about':
        title = 'About';
        content = _getDefaultAbout();
        break;
      default:
        title = 'Legal Document';
        content = '<p>Document not found.</p>';
    }
    
    setState(() {
      _title = title;
      _isLoading = false;
    });
    _loadHtml(content);
  }

  void _loadHtml(String body) {
    final html = '''
      <!DOCTYPE html>
      <html>
      <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <style>
          * { box-sizing: border-box; }
          body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 20px;
            line-height: 1.7;
            color: #2d3748;
            background-color: #ffffff;
            margin: 0;
          }
          h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1a202c;
            padding-bottom: 12px;
            border-bottom: 3px solid #667eea;
          }
          h3 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 28px;
            margin-bottom: 12px;
            color: #2d3748;
          }
          p {
            margin-bottom: 14px;
            color: #4a5568;
          }
          ul {
            padding-left: 24px;
            margin-bottom: 16px;
          }
          li {
            margin-bottom: 8px;
            color: #4a5568;
            line-height: 1.6;
          }
          li::marker {
            color: #667eea;
          }
          em {
            color: #718096;
            font-style: italic;
          }
          strong {
            color: #2d3748;
            font-weight: 600;
          }
          a {
            color: #667eea;
            text-decoration: none;
          }
          a:hover {
            text-decoration: underline;
          }
        </style>
      </head>
      <body>
        $body
      </body>
      </html>
    ''';
    _webViewController.loadHtmlString(html);
  }

  String _getDefaultPrivacyPolicy() {
    return '''<h2>Privacy Policy</h2>
<p><em>Last updated: May 2026</em></p>
<h3>1. Information We Collect</h3>
<ul>
<li>Account information: name, email, phone number</li>
<li>Location data: when you submit reports or use map features</li>
<li>Usage data: app interactions, features used</li>
<li>Device information: device type, operating system</li>
</ul>
<h3>2. How We Use Your Information</h3>
<ul>
<li>Provide and improve our services</li>
<li>Personalize your experience</li>
<li>Send notifications and updates you have opted into</li>
<li>Analyze usage patterns to improve the platform</li>
<li>Ensure security and prevent fraud</li>
</ul>
<h3>3. Location Data</h3>
<p>Location data is collected only when:</p>
<ul>
<li>You submit a report or alert</li>
<li>You search for nearby places</li>
<li>You use map features</li>
</ul>
<p>You can disable location services in your device settings.</p>
<h3>4. Data Sharing</h3>
<p>We do not sell your personal data. We may share data:</p>
<ul>
<li>With your explicit consent</li>
<li>To comply with legal obligations</li>
<li>To protect rights and safety</li>
<li>With service providers essential to platform operation</li>
</ul>
<h3>5. Data Security</h3>
<p>We implement appropriate security measures:</p>
<ul>
<li>Encryption of data in transit</li>
<li>Secure token-based authentication</li>
<li>Regular security audits</li>
<li>Access controls on sensitive data</li>
</ul>
<h3>6. Your Rights</h3>
<p>You have the right to:</p>
<ul>
<li>Access your personal data</li>
<li>Correct inaccurate data</li>
<li>Delete your account and data</li>
<li>Export your data</li>
<li>Opt out of marketing communications</li>
</ul>
<h3>7. Data Retention</h3>
<p>We retain your data as long as your account is active.</p>
<h3>8. Contact</h3>
<p>For privacy concerns, contact us through the app feedback feature.</p>''';
  }

  String _getDefaultTerms() {
    return '''<h2>Terms of Service</h2>
<p><em>Last updated: May 2026</em></p>
<h3>1. Acceptance of Terms</h3>
<p>By accessing and using Nepal Smart Travel, you accept these terms.</p>
<h3>2. User Responsibilities</h3>
<ul>
<li>Provide accurate information</li>
<li>Keep account credentials confidential</li>
<li>Do not submit false content</li>
<li>No illegal activities</li>
</ul>
<h3>3. Content Guidelines</h3>
<ul>
<li>Reports must be factual</li>
<li>Respect local communities</li>
<li>Do not share others personal info</li>
<li>Follow emergency alert guidelines</li>
</ul>
<h3>4. Community Standards</h3>
<ul>
<li>Be respectful in interactions</li>
<li>Maintain information accuracy</li>
<li>Report inappropriate content</li>
<li>Contribute positively</li>
</ul>
<h3>5. Account Termination</h3>
<p>Violations leading to termination:</p>
<ul>
<li>Fraudulent reports</li>
<li>Harassing users</li>
<li>Violating laws</li>
<li>Misusing emergency features</li>
</ul>
<h3>6. Limitation of Liability</h3>
<p>Information provided as-is basis.</p>
<h3>7. Changes to Terms</h3>
<p>Users notified of material changes.</p>''';
  }

  String _getDefaultAbout() {
    return '''<h2>About Nepal Smart Travel</h2>
<p>Your Trusted Travel Intelligence Platform</p>
<p><em>Version 1.0.0</em></p>
<h3>Real-time Reports</h3>
<p>Submit and view real-time reports about road conditions, safety issues, and local events.</p>
<h3>Emergency Alerts</h3>
<p>Get critical alerts about natural disasters, security concerns, and emergency situations.</p>
<h3>Community Driven</h3>
<p>Powered by a community of travelers and locals sharing accurate information.</p>
<h3>Gamification System</h3>
<p>Earn XP, unlock badges, and climb the ranks as you contribute.</p>
<h3>Contact & Support</h3>
<p>support@nepalsmarttravel.com</p>
<p>&copy; 2026 Nepal Smart Travel</p>''';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: Text(
          _title ?? 'Legal Document',
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 18,
          ),
        ),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _loadDocument,
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _isLoading
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor.withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const SizedBox(
                      width: 40,
                      height: 40,
                      child: CircularProgressIndicator(
                        strokeWidth: 3,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    'Loading document...',
                    style: TextStyle(
                      fontSize: 16,
                      color: AppTheme.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            )
          : _hasError
              ? _buildErrorView()
              : Column(
                  children: [
                    // Info banner
                    Container(
                      margin: const EdgeInsets.all(16),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppTheme.primaryColor.withOpacity(0.08),
                            AppTheme.primaryColor.withOpacity(0.04),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppTheme.primaryColor.withOpacity(0.15),
                        ),
                      ),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppTheme.primaryColor.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(
                              Icons.info_outline_rounded,
                              size: 18,
                              color: AppTheme.primaryColor,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'For questions about these policies, contact support through the app.',
                              style: TextStyle(
                                fontSize: 13,
                                color: AppTheme.primaryColor.withOpacity(0.8),
                                height: 1.4,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    // WebView content
                    Expanded(
                      child: WebViewWidget(controller: _webViewController),
                    ),
                  ],
                ),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: AppTheme.errorColor.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.error_outline_rounded,
                size: 56,
                color: AppTheme.errorColor,
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'Oops! Something went wrong',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Failed to load the document. Please check your connection and try again.',
              style: TextStyle(
                fontSize: 14,
                color: AppTheme.textSecondary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 28),
            ElevatedButton.icon(
              onPressed: _loadDocument,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              label: const Text('Try Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primaryColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
