import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import 'partner_details_screen.dart';

class PartnersListScreen extends StatefulWidget {
  const PartnersListScreen({super.key});

  @override
  State<PartnersListScreen> createState() => _PartnersListScreenState();
}

class _PartnersListScreenState extends State<PartnersListScreen> {
  final _api = ApiClient.instance;
  List<dynamic> _partners = [];
  bool _loading = true;
  String? _error;
  final _searchCtl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final params = <String, dynamic>{};
      if (_searchCtl.text.isNotEmpty) {
        params['district'] = _searchCtl.text;
      }
      final res = await _api.getPartners();
      _partners = res.data['data'] as List<dynamic>;
    } catch (e) {
      _error = e.toString();
    }
    if (mounted) setState(() { _loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Travel Partners')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchCtl,
              decoration: InputDecoration(
                hintText: 'Search by district...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                suffixIcon: _searchCtl.text.isNotEmpty
                    ? IconButton(icon: const Icon(Icons.clear), onPressed: () { _searchCtl.clear(); _load(); })
                    : null,
              ),
              onSubmitted: (_) => _load(),
            ),
          ),
          Expanded(
            child: _loading ? const Center(child: CircularProgressIndicator())
                : _error != null ? Center(child: Text('Error: $_error'))
                : _partners.isEmpty ? const Center(child: Text('No partners found'))
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      itemCount: _partners.length,
                      itemBuilder: (_, i) {
                        final p = _partners[i] as Map<String, dynamic>;
                        return Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(12),
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PartnerDetailsScreen(partnerId: p['id'] as int))),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Row(
                                children: [
                                  CircleAvatar(
                                    radius: 28,
                                    backgroundColor: Colors.blue.shade50,
                                    child: Text(p['name']?[0]?.toUpperCase() ?? '?', style: TextStyle(fontSize: 24, color: Colors.blue.shade700, fontWeight: FontWeight.bold)),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(p['name'] as String? ?? '', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                                        const SizedBox(height: 4),
                                        Row(children: [
                                          if (p['type'] != null && (p['type'] as String).isNotEmpty)
                                            Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: Colors.orange.shade50, borderRadius: BorderRadius.circular(4)), child: Text(p['type'], style: TextStyle(fontSize: 11, color: Colors.orange.shade800))),
                                          const SizedBox(width: 6),
                                          Icon(Icons.location_on, size: 14, color: Colors.grey.shade500),
                                          const SizedBox(width: 2),
                                          Text(p['district'] ?? '', style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
                                        ]),
                                      ],
                                    ),
                                  ),
                                  Icon(Icons.chevron_right, color: Colors.grey.shade400),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
