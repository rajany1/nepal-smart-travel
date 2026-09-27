import 'dart:async';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../core/api/api_client.dart';

class SubscriptionPaymentScreen extends StatefulWidget {
  final int planId;
  final String planName;
  final String gateway;
  final String formHtml;
  final String? paymentUrl;

  const SubscriptionPaymentScreen({
    super.key,
    required this.planId,
    required this.planName,
    required this.gateway,
    required this.formHtml,
    this.paymentUrl,
  });

  @override
  State<SubscriptionPaymentScreen> createState() => _SubscriptionPaymentScreenState();
}

class _SubscriptionPaymentScreenState extends State<SubscriptionPaymentScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  bool _isVerifying = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            if (mounted) setState(() { _isLoading = true; });
          },
          onPageFinished: (url) {
            if (mounted) setState(() { _isLoading = false; });
          },
          onNavigationRequest: (request) {
            final url = request.url;
            // Detect callback URLs (eSewa or Khalti redirect back to our app)
            if (url.contains('/payments/subscription/esewa/callback') ||
                url.contains('/payments/subscription/khalti/callback')) {
              _verifyPayment();
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      );

    _loadPaymentPage();
  }

  void _loadPaymentPage() {
    if (widget.formHtml.isNotEmpty) {
      // eSewa: load the auto-submitting form HTML
      _controller.loadHtmlString(widget.formHtml);
    } else if (widget.paymentUrl != null) {
      // Khalti: load the payment URL directly
      _controller.loadRequest(Uri.parse(widget.paymentUrl!));
    }
  }

  Future<void> _verifyPayment() async {
    if (_isVerifying) return;
    setState(() { _isVerifying = true; _error = null; });

    try {
      final api = ApiClient.instance;
      final response = await api.verifySubscription(widget.planId);
      final data = response.data;

      if (data['success'] == true) {
        if (mounted) {
          Navigator.of(context).pop(true); // Return true on success
        }
      } else {
        setState(() {
          _error = data['message']?.toString() ?? 'Payment verification failed';
          _isVerifying = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Verification error: $e';
        _isVerifying = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Subscribe: ${widget.planName}'),
        actions: [
          if (_isVerifying)
            const Padding(
              padding: EdgeInsets.all(16.0),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              ),
            ),
        ],
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_isLoading && !_isVerifying)
            const Center(child: CircularProgressIndicator()),
          if (_isVerifying)
            Container(
              color: Colors.black54,
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(color: Colors.white),
                    SizedBox(height: 16),
                    Text('Verifying payment...', style: TextStyle(color: Colors.white, fontSize: 16)),
                  ],
                ),
              ),
            ),
          if (_error != null && !_isVerifying)
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                color: Colors.red.shade50,
                padding: const EdgeInsets.all(16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.of(context).pop(false),
                          child: const Text('Cancel'),
                        ),
                        TextButton(
                          onPressed: () {
                            setState(() { _error = null; });
                            _verifyPayment();
                          },
                          child: const Text('Retry Verification'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
