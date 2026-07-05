import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../bookings/booking_form_screen.dart';

class PartnerDetailsScreen extends StatefulWidget {
  final int partnerId;
  const PartnerDetailsScreen({super.key, required this.partnerId});

  @override
  State<PartnerDetailsScreen> createState() => _PartnerDetailsScreenState();
}

class _PartnerDetailsScreenState extends State<PartnerDetailsScreen> {
  final _api = ApiClient.instance;
  Map<String, dynamic>? _partner;
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
      final res = await _api.getPartnerDetail(widget.partnerId);
      _partner = res.data['data'] as Map<String, dynamic>;
    } catch (e) {
      _error = e.toString();
    }
    if (mounted) setState(() { _loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_partner?['name'] ?? 'Partner Details')),
      body: _loading ? const Center(child: CircularProgressIndicator())
          : _error != null ? Center(child: Text('Error: $_error'))
          : _partner == null ? const Center(child: Text('Not found'))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: CircleAvatar(
                      radius: 48,
                      backgroundColor: Colors.blue.shade50,
                      child: Text(_partner!['name']?[0]?.toUpperCase() ?? '?', style: TextStyle(fontSize: 40, color: Colors.blue.shade700, fontWeight: FontWeight.bold)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Center(
                    child: Text(_partner!['name'] as String? ?? '', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                  ),
                  if (_partner!['type'] != null && (_partner!['type'] as String).isNotEmpty)
                    Center(
                      child: Container(
                        margin: const EdgeInsets.only(top: 4),
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                        decoration: BoxDecoration(color: Colors.orange.shade50, borderRadius: BorderRadius.circular(12)),
                        child: Text(_partner!['type'], style: TextStyle(color: Colors.orange.shade800, fontWeight: FontWeight.w500)),
                      ),
                    ),
                  const SizedBox(height: 24),
                  _infoTile(Icons.location_on, 'District', _partner!['district'] ?? 'N/A'),
                  _infoTile(Icons.phone, 'Phone', _partner!['phone'] ?? 'N/A'),
                  _infoTile(Icons.email, 'Email', _partner!['email'] ?? 'N/A'),
                  _infoTile(Icons.language, 'Website', _partner!['website'] ?? 'N/A'),
                  _infoTile(Icons.location_city, 'Address', _partner!['address'] ?? 'N/A'),
                  if (_partner!['bookings_count'] != null)
                    _infoTile(Icons.book_online, 'Total Bookings', '${_partner!['bookings_count']}'),
                  if (_partner!['description'] != null && (_partner!['description'] as String).isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const Text('About', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    Text(_partner!['description'], style: TextStyle(fontSize: 14, color: Colors.grey.shade700, height: 1.5)),
                  ],
                  const SizedBox(height: 32),
                ],
              ),
            ),
      floatingActionButton: _partner != null
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BookingFormScreen(partnerId: widget.partnerId, partnerName: _partner!['name'] as String? ?? ''))),
              icon: const Icon(Icons.calendar_today),
              label: const Text('Book Now'),
            )
          : null,
    );
  }

  Widget _infoTile(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Colors.grey.shade600),
          const SizedBox(width: 12),
          SizedBox(width: 80, child: Text(label, style: TextStyle(color: Colors.grey.shade600, fontWeight: FontWeight.w500))),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 15))),
        ],
      ),
    );
  }
}
