import 'dart:convert';
import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';

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
        _currentPlanId = mySubRaw['subscription_plan_id'] as int?;
      }
    } catch (_) {
      _currentPlanId = null;
    }

    if (mounted) setState(() { _loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Subscription Plans')),
      body: _loading ? const Center(child: CircularProgressIndicator())
          : _error != null ? Center(child: Text('Error: $_error'))
          : _plans.isEmpty ? const Center(child: Text('No plans available'))
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
                                child: Text('Current', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.green.shade700)),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('${p['currency'] ?? 'NPR'} ', style: const TextStyle(fontSize: 14, color: Colors.grey)),
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
                            onPressed: isCurrentPlan
                                ? null
                                : () => ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(content: Text('Payment integration coming soon')),
                                    ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: isCurrentPlan ? Colors.green.shade50 : Colors.blue,
                              foregroundColor: isCurrentPlan ? Colors.green.shade700 : Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: Text(isCurrentPlan ? 'Current Plan' : 'Upgrade'),
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
