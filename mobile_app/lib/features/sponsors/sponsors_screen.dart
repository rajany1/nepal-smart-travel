import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../../core/api/api_client.dart';
import '../places/nearby_places_screen.dart';
import '../store/store_screen.dart';

class SponsorsScreen extends StatefulWidget {
  const SponsorsScreen({super.key});

  @override
  State<SponsorsScreen> createState() => _SponsorsScreenState();
}

class _SponsorsScreenState extends State<SponsorsScreen> {
  final _api = ApiClient.instance;
  final _mapController = MapController();
  List<dynamic> _sponsors = [];
  bool _loading = true;
  bool _showMap = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await _api.getSponsors();
      _sponsors = res.data['data'] as List<dynamic>;
    } catch (e) {
      _error = e.toString();
    }
    if (mounted) setState(() { _loading = false; });
  }

  void _openDirections(double lat, double lng, String name) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => NearbyPlacesScreen.withDestination(
          destinationLat: lat,
          destinationLng: lng,
          destinationName: name,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Our Sponsors'),
        actions: [
          if (!_loading && _sponsors.isNotEmpty)
            IconButton(
              icon: Icon(_showMap ? Icons.list : Icons.map),
              tooltip: _showMap ? 'List view' : 'Map view',
              onPressed: () => setState(() => _showMap = !_showMap),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text('Error: $_error'))
              : _sponsors.isEmpty
                  ? const Center(child: Text('No sponsors yet'))
                  : _showMap ? _buildMapView() : _buildListView(),
    );
  }

  Widget _buildListView() {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _sponsors.length,
        itemBuilder: (_, i) {
          final s = _sponsors[i] as Map<String, dynamic>;
          final lat = s['latitude'] is num ? (s['latitude'] as num).toDouble() : double.tryParse(s['latitude']?.toString() ?? '');
          final lng = s['longitude'] is num ? (s['longitude'] as num).toDouble() : double.tryParse(s['longitude']?.toString() ?? '');
          final hasLocation = lat != null && lng != null;
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: Colors.purple.shade50,
                      child: Text(s['name']?[0]?.toUpperCase() ?? '?', style: TextStyle(fontSize: 26, color: Colors.purple.shade700, fontWeight: FontWeight.bold)),
                    ),
                    const SizedBox(width: 14),
                    Expanded(child: Text(s['name'] as String? ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600))),
                  ]),
                  if (s['description'] != null && (s['description'] as String).isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(s['description'], style: TextStyle(color: Colors.grey.shade700, height: 1.4)),
                  ],
                  const SizedBox(height: 12),
                  Wrap(spacing: 12, children: [
                    if (s['website'] != null && (s['website'] as String).isNotEmpty)
                      ActionChip(
                        avatar: const Icon(Icons.language, size: 16),
                        label: const Text('Website'),
                        onPressed: () {/* launch URL */},
                      ),
                    if (hasLocation)
                      ActionChip(
                        avatar: const Icon(Icons.directions, size: 16, color: Colors.blue),
                        label: const Text('Directions'),
                        onPressed: () => _openDirections(lat, lng, s['name'] as String? ?? ''),
                      ),
                    if ((s['shop_items_count'] ?? 0) > 0)
                      ActionChip(
                        avatar: const Icon(Icons.store, size: 16),
                        label: Text('${s['shop_items_count']} Rewards'),
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StoreScreen())),
                      ),
                  ]),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildMapView() {
    final markers = <Marker>[];
    double? centerLat, centerLng;

    for (final s in _sponsors) {
      final lat = (s as Map<String, dynamic>)['latitude'] is num ? ((s as Map<String, dynamic>)['latitude'] as num).toDouble() : double.tryParse((s as Map<String, dynamic>)['latitude']?.toString() ?? '');
      final lng = s['longitude'] is num ? (s['longitude'] as num).toDouble() : double.tryParse(s['longitude']?.toString() ?? '');
      if (lat != null && lng != null) {
        centerLat = lat;
        centerLng = lng;
        markers.add(
          Marker(
            point: LatLng(lat, lng),
            width: 200,
            height: 60,
            child: GestureDetector(
              onTap: () => _showSponsorPopup(s),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 4)],
                    ),
                    child: Text(
                      s['name'] as String? ?? '',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Icon(Icons.location_on, color: Colors.red, size: 32),
                ],
              ),
            ),
          ),
        );
      }
    }

    return FlutterMap(
      mapController: _mapController,
      options: MapOptions(
        initialCenter: LatLng(centerLat ?? 27.7172, centerLng ?? 85.3240),
        initialZoom: 8.0,
        maxZoom: 18.0,
        minZoom: 6.0,
        cameraConstraint: CameraConstraint.contain(
          bounds: LatLngBounds(
            const LatLng(26.0, 79.5),
            const LatLng(31.0, 89.0),
          ),
        ),
        interactionOptions: const InteractionOptions(flags: InteractiveFlag.all),
      ),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'np.com.nepalsmarttravel',
        ),
        MarkerLayer(markers: markers),
      ],
    );
  }

  void _showSponsorPopup(Map<String, dynamic> s) {
    final lat = s['latitude'] is num ? (s['latitude'] as num).toDouble() : double.tryParse(s['latitude']?.toString() ?? '');
    final lng = s['longitude'] is num ? (s['longitude'] as num).toDouble() : double.tryParse(s['longitude']?.toString() ?? '');
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s['name'] as String? ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
            if (s['description'] != null && (s['description'] as String).isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(s['description'], style: TextStyle(color: Colors.grey.shade600)),
              ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                icon: const Icon(Icons.directions),
                label: const Text('Get Directions'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () {
                  Navigator.pop(ctx);
                  if (lat != null && lng != null) {
                    _openDirections(lat, lng, s['name'] as String? ?? '');
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
