import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';

class MyBookingsScreen extends StatefulWidget {
  const MyBookingsScreen({super.key});

  @override
  State<MyBookingsScreen> createState() => _MyBookingsScreenState();
}

class _MyBookingsScreenState extends State<MyBookingsScreen> {
  final _api = ApiClient.instance;
  List<dynamic> _bookings = [];
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
      final res = await _api.getMyBookings();
      _bookings = res.data['data'] as List<dynamic>;
    } catch (e) {
      _error = e.toString();
    }
    if (mounted) setState(() { _loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Bookings')),
      body: _loading ? const Center(child: CircularProgressIndicator())
          : _error != null ? Center(child: Text('Error: $_error'))
          : _bookings.isEmpty ? const Center(child: Text('No bookings yet'))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: _bookings.length,
                itemBuilder: (_, i) {
                  final b = _bookings[i] as Map<String, dynamic>;
                  final partner = b['travel_partner'] as Map<String, dynamic>?;
                  final status = b['status'] as String? ?? '';
                  final Color sColor = switch (status) {
                    'confirmed' => Colors.green,
                    'completed' => Colors.blue,
                    'cancelled' => Colors.red,
                    _ => Colors.orange,
                  };
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            Expanded(child: Text(b['customer_name'] ?? 'Booking #${b['id']}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15))),
                            Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: sColor.withOpacity(0.15), borderRadius: BorderRadius.circular(6)), child: Text(status.toUpperCase(), style: TextStyle(color: sColor, fontSize: 11, fontWeight: FontWeight.w700))),
                          ]),
                          const SizedBox(height: 8),
                          Text('Partner: ${partner?['name'] ?? 'N/A'}', style: TextStyle(color: Colors.grey.shade700)),
                          if (b['booked_at'] != null) Text('Date: ${b['booked_at']}', style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
                          if ((b['amount'] ?? 0) > 0) Text('Amount: NPR ${b['amount']}', style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
                          if (b['notes'] != null && (b['notes'] as String).isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text('Notes: ${b['notes']}', style: TextStyle(color: Colors.grey.shade500, fontSize: 12, fontStyle: FontStyle.italic)),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}
