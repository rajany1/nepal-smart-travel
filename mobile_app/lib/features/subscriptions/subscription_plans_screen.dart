import 'dart:convert';
import "../../core/services/localization_service.dart";
import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import 'subscription_payment_screen.dart';

class SubscriptionPlansScreen extends StatefulWidget {
  const SubscriptionPlansScreen({super.key});

  @override
  State<SubscriptionPlansScreen> createState() => _SubscriptionPlansScreenState();
}

class _SubscriptionPlansScreenState extends State<SubscriptionPlansScreen> {
  final _api = ApiClient.instance;
  List<dynamic> _plans = [];
  int? _currentPlanId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final plansRes = await _api.getSubscriptionPlans();
      final plansRaw = plansRes.data['data'];
      if (plansRaw is List) {
        _plans = plansRaw;
      } else if (plansRaw is String) {
        _error = plansRaw;
        return;
      }
    } catch (e) {
      _error = e.toString();
      return;
    }

    if (!mounted) return;
    try {
      final mySubRes = await _api.getMySubscription();
      final mySubRaw = mySubRes.data['data'];
      if (mySubRaw is Map) {
        final rawPlanId = mySubRaw['subscription_plan_id'];
        _currentPlanId = rawPlanId is num
            ? rawPlanId.toInt()
            : int.tryParse(rawPlanId?.toString() ?? '');
      }
    } catch (_) {
      _currentPlanId = null;
    }

    if (mounted) setState(() { _loading = false; });
  }

  Future<void> _purchasePlan(Map<String, dynamic> plan) async {
    final planId = plan['id'] is num ? (plan['id'] as num).toInt() : int.tryParse(plan['id'].toString() ?? '');
    if (planId == null) return;

    // Show gateway selection dialog
    final gateway = await showDialog<String>(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: Text(context.t('Choose Payment Method')),
        children: [
          SimpleDialogOption(
            onPressed: () => Navigator.pop(ctx, 'esewa'),
            child: Row(
              children: [
                Icon(Icons.account_balance_wallet, color: Colors.green.shade600),
                const SizedBox(width: 12),
                const Text('eSewa', style: TextStyle(fontSize: 16)),
              ],
            ),
          ),
          SimpleDialogOption(
            onPressed: () => Navigator.pop(ctx, 'khalti'),
            child: Row(
              children: [
                Icon(Icons.phone_android, color: Colors.purple.shade600),
                const SizedBox(width: 12),
                const Text('Khalti', style: TextStyle(fontSize: 16)),
              ],
            ),
          ),
        ],
      ),
    );

    if (gateway == null || !mounted) return;

    // Show loading
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final response = await _api.purchaseSubscription(planId, gateway);
      if (!mounted) return;
      Navigator.of(context).pop(); // Dismiss loading

      final data = response.data;
      if (data['success'] != true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['message']?.toString() ?? context.t('Payment initiation failed'))),
        );
        return;
      }

      final paymentData = data['data'];
      final formHtml = paymentData['form_html']?.toString() ?? '';
      final paymentUrl = paymentData['payment_url']?.toString();
      final planName = plan['name']?.toString() ?? context.t('Subscription');

      // Navigate to payment screen
      final result = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => SubscriptionPaymentScreen(
            planId: planId,
            planName: planName,
            gateway: gateway,
            formHtml: formHtml,
            paymentUrl: paymentUrl,
          ),
        ),
      );

      if (result == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.t('Subscription activated successfully!')), backgroundColor: Colors.green),
        );
        _load(); // Refresh plans
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context).pop(); // Dismiss loading
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${context.t('Error:')} $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFF7C3AED).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.workspace_premium, color: Color(0xFF7C3AED), size: 22),
            ),
            const SizedBox(width: 12),
            Text(context.t('Subscription Plans')),
          ],
        ),
      ),
      body: _loading ? const Center(child: CircularProgressIndicator())
          : _error != null ? Center(child: Text('${context.t('Error:')} $_error'))
          : _plans.isEmpty ? Center(child: Text(context.t('No plans available')))
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _plans.length,
              itemBuilder: (_, i) {
                final p = _plans[i] as Map<String, dynamic>;
                final features = switch (p['features']) {
                  List l => l,
                  String s => (jsonDecode(s) as List?) ?? [],
                  _ => <dynamic>[],
                };
                final isCurrentPlan = _currentPlanId != null && p['id'] == _currentPlanId;
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: isCurrentPlan ? const BorderSide(color: Colors.green, width: 2) : BorderSide.none,
                  ),
                  elevation: isCurrentPlan ? 4 : 1,
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(p['name'] as String? ?? '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                            if (isCurrentPlan) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.green.shade300)),
                                child: Text(context.t('Current'), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.green.shade700)),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('${p['currency'] ?? context.t('NPR')} ', style: const TextStyle(fontSize: 14, color: Colors.grey)),
                            Text('${p['price'] ?? 0}', style: TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: Colors.blue.shade700)),
                            Text('/${p['billing_interval'] ?? ''}', style: const TextStyle(fontSize: 14, color: Colors.grey)),
                          ],
                        ),
                        if (p['description'] != null && (p['description'] as String).isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(p['description'], textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
                        ],
                        if (features.isNotEmpty) ...[
                          const Divider(height: 24),
                          ...features.map((f) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(children: [
                              Icon(Icons.check_circle, size: 18, color: Colors.green.shade600),
                              const SizedBox(width: 8),
                              Text('$f', style: TextStyle(color: Colors.grey.shade700)),
                            ]),
                          )),
                        ],
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: isCurrentPlan ? null : () => _purchasePlan(p),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: isCurrentPlan ? Colors.green.shade50 : Colors.blue,
                              foregroundColor: isCurrentPlan ? Colors.green.shade700 : Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: Text(isCurrentPlan ? context.t('Current Plan') : context.t('Upgrade')),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}
