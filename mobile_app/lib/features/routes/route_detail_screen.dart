import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import "../../core/services/localization_service.dart";
import '../../config/themes/app_theme.dart';
import '../../core/models/place.dart';
import '../../core/models/route_model.dart';
import '../../providers/route_provider.dart';
import '../places/place_details_screen.dart';

/// Route detail: GPS track on a map, stats, and stops.
class RouteDetailScreen extends StatefulWidget {
  final int routeId;

  const RouteDetailScreen({super.key, required this.routeId});

  @override
  State<RouteDetailScreen> createState() => _RouteDetailScreenState();
}

class _RouteDetailScreenState extends State<RouteDetailScreen> {
  final MapController _mapController = MapController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RouteProvider>().fetchRouteDetail(widget.routeId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RouteProvider>();
    final route = provider.selectedRoute;

    return Scaffold(
      appBar: AppBar(title: Text(route?.title ?? context.t('Route'))),
      body: provider.isLoadingDetail && route == null
          ? const Center(child: CircularProgressIndicator())
          : route == null
              ? _buildError(context, provider)
              : _buildContent(context, route),
    );
  }

  Widget _buildError(BuildContext context, RouteProvider provider) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.map_outlined, size: 56, color: AppTheme.textSecondary),
          const SizedBox(height: 12),
          Text(context.t('Could not load this route')),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => provider.fetchRouteDetail(widget.routeId),
            child: Text(context.t('Retry')),
          ),
        ],
      ),
    );
  }

  Widget _buildContent(BuildContext context, CuratedRouteModel route) {
    final track = route.track;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Stats header
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: route.isTrekking
                ? const LinearGradient(
                    colors: [Color(0xFFB45309), Color(0xFF78350F)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  )
                : const LinearGradient(
                    colors: [AppTheme.primaryColor, AppTheme.primaryLight],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _HeaderBadge(
                    label: route.isTrekking ? context.t('Trekking') : context.t('Itinerary'),
                  ),
                  if (route.difficulty != null)
                    _HeaderBadge(
                      label: route.difficultyLabel,
                      icon: Icons.signal_cellular_alt,
                    ),
                  if (route.bestSeason != null)
                    _HeaderBadge(label: route.bestSeason!, icon: Icons.wb_sunny_outlined),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                route.title,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: AppTheme.text2xl,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (route.description != null && route.description!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  route.description!,
                  style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textSm),
                ),
              ],
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _StatItem(icon: Icons.schedule, value: '${route.durationDays}', unit: context.t('days')),
                  if (route.totalDistanceKm != null)
                    _StatItem(icon: Icons.straighten, value: '${route.totalDistanceKm!.round()}', unit: 'km'),
                  if (route.maxAltitudeM != null)
                    _StatItem(icon: Icons.terrain, value: '${route.maxAltitudeM}', unit: 'm'),
                  if (route.elevationGainM != null)
                    _StatItem(icon: Icons.trending_up, value: '${route.elevationGainM}', unit: 'm'),
                ],
              ),
              if (route.startingPoint != null) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(Icons.flag, color: Colors.white, size: 16),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        '${route.startingPoint}${route.endingPoint != null && route.endingPoint != route.startingPoint ? ' → ${route.endingPoint}' : ''}',
                        style: const TextStyle(color: Colors.white, fontSize: AppTheme.textSm, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 20),

        // Map with the GPS track
        if (track.isNotEmpty) ...[
          Text(context.t('Route map'), style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              height: 280,
              child: _buildMap(track),
            ),
          ),
          const SizedBox(height: 20),
        ],

        // Stops (database places)
        if (route.places.isNotEmpty) ...[
          Text(context.t('Stops on this route'), style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          ...route.places.asMap().entries.map((entry) => _StopTile(
                index: entry.key + 1,
                name: entry.value.name,
                subtitle: entry.value.district,
                rating: entry.value.averageRating,
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => PlaceDetailsScreen(
                      place: Place(
                        id: entry.value.id.toString(),
                        name: entry.value.name,
                        district: entry.value.district,
                        category: entry.value.category ?? 'Place',
                        latitude: entry.value.latitude,
                        longitude: entry.value.longitude,
                        averageRating: entry.value.averageRating,
                      ),
                    ),
                  ),
                ),
              )),
          const SizedBox(height: 12),
        ],

        // Trail waypoints (GPS track stops)
        if (track.isNotEmpty) ...[
          Text(context.t('Trail waypoints'), style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          ...track.asMap().entries.map((entry) => _StopTile(
                index: entry.key + 1,
                name: entry.value.name ?? '${context.t('Waypoint')} ${entry.key + 1}',
                subtitle: '${entry.value.lat.toStringAsFixed(4)}, ${entry.value.lng.toStringAsFixed(4)}',
                isEnd: entry.key == 0 || entry.key == track.length - 1,
              )),
          const SizedBox(height: 12),
        ],

        if (route.places.isEmpty && track.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 40),
            child: Center(
              child: Text(
                context.t('No stops added yet.'),
                style: const TextStyle(color: AppTheme.textSecondary),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildMap(List<RouteTrackPoint> track) {
    final latlngs = track.map((p) => LatLng(p.lat, p.lng)).toList();

    return FlutterMap(
      mapController: _mapController,
      options: MapOptions(
        initialCenter: latlngs.first,
        initialZoom: 10,
        onTap: (_, __) {},
      ),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'np.com.nepalsmarttravel',
          maxZoom: 19,
        ),
        PolylineLayer(
          polylines: [
            Polyline(
              points: latlngs,
              color: AppTheme.primaryColor.withOpacity(0.9),
              strokeWidth: 4,
            ),
          ],
        ),
        MarkerLayer(
          markers: [
            for (int i = 0; i < track.length; i++)
              Marker(
                point: latlngs[i],
                width: 30,
                height: 30,
                child: _NumberedMarker(
                  number: i + 1,
                  isEnd: i == 0 || i == track.length - 1,
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _HeaderBadge extends StatelessWidget {
  final String label;
  final IconData? icon;

  const _HeaderBadge({required this.label, this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: Colors.white),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: AppTheme.textXs,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatItem extends StatelessWidget {
  final IconData icon;
  final String value;
  final String unit;

  const _StatItem({required this.icon, required this.value, required this.unit});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: Colors.white70, size: 18),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: AppTheme.textLg,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          unit,
          style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textXs),
        ),
      ],
    );
  }
}

class _NumberedMarker extends StatelessWidget {
  final int number;
  final bool isEnd;

  const _NumberedMarker({required this.number, required this.isEnd});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: isEnd ? const Color(0xFFD97706) : AppTheme.primaryColor,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: const [
          BoxShadow(color: Colors.black26, blurRadius: 3),
        ],
      ),
      alignment: Alignment.center,
      child: Text(
        '$number',
        style: const TextStyle(
          color: Colors.white,
          fontSize: AppTheme.textSm,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}

class _StopTile extends StatelessWidget {
  final int index;
  final String name;
  final String? subtitle;
  final double? rating;
  final bool isEnd;
  final VoidCallback? onTap;

  const _StopTile({
    required this.index,
    required this.name,
    this.subtitle,
    this.rating,
    this.isEnd = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: AppTheme.surfaceColor,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              border: Border.all(color: AppTheme.dividerColor),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: isEnd ? const Color(0xFFD97706) : AppTheme.primaryColor,
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    '$index',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: AppTheme.textSm,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase),
                      ),
                      if (subtitle != null)
                        Text(
                          subtitle!,
                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
                        ),
                    ],
                  ),
                ),
                if (rating != null && rating! > 0)
                  Row(
                    children: [
                      const Icon(Icons.star, size: 14, color: AppTheme.secondaryColor),
                      const SizedBox(width: 2),
                      Text(
                        rating!.toStringAsFixed(1),
                        style: const TextStyle(fontSize: AppTheme.textSm),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
