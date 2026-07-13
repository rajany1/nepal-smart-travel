import 'package:flutter/material.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../widgets/section_card.dart';
import '../../widgets/info_tile.dart';
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
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text('Error: $_error'))
              : _partner == null
                  ? const Center(child: Text('Not found'))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Center(
                            child: CircleAvatar(
                              radius: 48,
                              backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                              child: Text(_partner!['name']?[0]?.toUpperCase() ?? '?', style: TextStyle(fontSize: AppTheme.text3xl, color: AppTheme.primaryColor, fontWeight: FontWeight.bold)),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Center(child: Text(_partner!['name'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold))),
                          if (_partner!['type'] != null && (_partner!['type'] as String).isNotEmpty)
                            Center(
                              child: Container(
                                margin: const EdgeInsets.only(top: 4),
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                decoration: BoxDecoration(color: AppTheme.secondaryColor.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                                child: Text(_partner!['type'], style: TextStyle(color: AppTheme.secondaryDark, fontWeight: FontWeight.w500)),
                              ),
                            ),
                          const SizedBox(height: 24),
                          SectionCard(
                            child: Column(
                              children: [
                                InfoTile(icon: Icons.location_on, label: 'District', value: _partner!['district'] ?? 'N/A'),
                                const Divider(height: 1),
                                InfoTile(icon: Icons.phone, label: 'Phone', value: _partner!['phone'] ?? 'N/A'),
                                const Divider(height: 1),
                                InfoTile(icon: Icons.email, label: 'Email', value: _partner!['email'] ?? 'N/A'),
                                const Divider(height: 1),
                                InfoTile(icon: Icons.language, label: 'Website', value: _partner!['website'] ?? 'N/A'),
                                const Divider(height: 1),
                                InfoTile(icon: Icons.location_city, label: 'Address', value: _partner!['address'] ?? 'N/A'),
                                if (_partner!['bookings_count'] != null) ...[
                                  const Divider(height: 1),
                                  InfoTile(icon: Icons.book_online, label: 'Total Bookings', value: '${_partner!['bookings_count']}'),
                                ],
                              ],
                            ),
                          ),
                          if (_partner!['description'] != null && (_partner!['description'] as String).isNotEmpty) ...[
                            const SizedBox(height: 16),
                            SectionCard(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('About', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600)),
                                  const SizedBox(height: 8),
                                  Text(_partner!['description'], style: const TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary, height: 1.5)),
                                ],
                              ),
                            ),
                          ],
                          const SizedBox(height: 32),
                        ],
                      ),
                    ),
      floatingActionButton: _partner != null
          ? FloatingActionButton.extended(
              heroTag: 'book',
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BookingFormScreen(partnerId: widget.partnerId, partnerName: _partner!['name'] as String? ?? ''))),
              icon: const Icon(Icons.calendar_today),
              label: const Text('Book Now'),
            )
          : null,
    );
  }
}
