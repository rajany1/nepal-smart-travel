import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../core/models/travel_context_model.dart';
import '../../core/services/localization_service.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../providers/travel_context_provider.dart';
import '../../widgets/ad_inline_banner.dart';

/// Route-aware intelligence: From → checkpoints → To corridor with ranked
/// reports/alerts/places along the journey.
class TravelContextScreen extends StatefulWidget {
  const TravelContextScreen({super.key});

  @override
  State<TravelContextScreen> createState() => _TravelContextScreenState();
}

class _TravelContextScreenState extends State<TravelContextScreen> {
  final LocationService _locationService = LocationService();
  final MapController _mapController = MapController();
  final _checkpointCtrl = TextEditingController();
  final _originCtrl = TextEditingController();
  bool _showMap = false;
  bool _usingAutoOrigin = false;
  String _autoOriginCoords = '';

  @override
  void initState() {
    super.initState();
    _prefillOriginFromGps();
  }

  @override
  void dispose() {
    _checkpointCtrl.dispose();
    _originCtrl.dispose();
    super.dispose();
  }

  /// Default From = user's current location (label for UX, lat,lng for API).
  Future<void> _prefillOriginFromGps() async {
    final loc = await _locationService.getLastKnownPosition() ??
        await _locationService.getCurrentLocation();
    if (!mounted || loc == null) return;
    await _applyCurrentLocation(loc.latitude, loc.longitude, overwrite: false);
  }

  Future<void> _applyCurrentLocation(
    double lat,
    double lng, {
    bool overwrite = true,
  }) async {
    if (!overwrite && _originCtrl.text.isNotEmpty && !_usingAutoOrigin) {
      return;
    }
    final coords = '$lat,$lng';
    var label = context.tr('Current location');
    try {
      final address = await _locationService.getAddressFromCoordinates(lat, lng);
      if (address != null && address.trim().isNotEmpty) {
        final parts = address
            .split(',')
            .map((s) => s.trim())
            .where((s) => s.isNotEmpty)
            .toList();
        if (parts.isNotEmpty) {
          label = parts.take(2).join(', ');
        }
      }
    } catch (_) {
      // Keep generic label — coords still resolve on the backend.
    }
    if (!mounted) return;
    _autoOriginCoords = coords;
    _usingAutoOrigin = true;
    _originCtrl.text = label;
    context.read<TravelContextProvider>().setOrigin(coords);
    setState(() {});
  }

  Future<void> _plan() async {
    final provider = context.read<TravelContextProvider>();
    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;

    // Keep From as a fresh GPS fix when the user has not overridden it.
    if (_usingAutoOrigin && loc != null) {
      _autoOriginCoords = '${loc.latitude},${loc.longitude}';
      provider.setOrigin(_autoOriginCoords);
    }

    await provider.planRoute(
      currentLat: loc?.latitude,
      currentLng: loc?.longitude,
    );
    if (!mounted) return;
    if (provider.needsClarification) {
      await _showClarificationDialog(provider);
    }
    if (mounted) {
      setState(() => _showMap = provider.hasContext);
    }
  }

  Future<void> _showClarificationDialog(TravelContextProvider provider) async {
    final options = provider.context?.routeOptions ?? const <RouteOption>[];
    if (options.isEmpty) return;

    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(context.t('Which route are you taking?')),
        content: SizedBox(
          width: double.maxFinite,
          child: ListView.separated(
            shrinkWrap: true,
            itemCount: options.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final o = options[i];
              final km = (o.distanceM / 1000).toStringAsFixed(1);
              final min = (o.durationS / 60).round();
              return ListTile(
                leading: const Icon(Icons.route, color: AppTheme.primaryColor),
                title: Text(o.label),
                subtitle: Text('$km km · $min min'),
                onTap: () {
                  provider.applyRouteOption(o);
                  Navigator.pop(ctx);
                },
              );
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(context.t('Cancel')),
          ),
        ],
      ),
    );

    if (provider.checkpointInputs.isNotEmpty && mounted) {
      await context.read<TravelContextProvider>().planRoute();
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TravelContextProvider>();
    final tc = provider.context;
    final intel = provider.intelligence;

    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('Along your route')),
        backgroundColor: Colors.white,
        elevation: 0,
        foregroundColor: Colors.black87,
        actions: [
          if (tc != null)
            IconButton(
              icon: Icon(_showMap ? Icons.list : Icons.map_outlined),
              tooltip: _showMap ? context.t('List') : context.t('Map'),
              onPressed: () => setState(() => _showMap = !_showMap),
            ),
          if (tc != null)
            IconButton(
              icon: const Icon(Icons.close),
              onPressed: () {
                provider.clearContextOnly();
                setState(() => _showMap = false);
              },
            ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _RouteInputCard(
              provider: provider,
              checkpointCtrl: _checkpointCtrl,
              originCtrl: _originCtrl,
              onOriginChanged: (v) {
                _usingAutoOrigin = false;
                provider.setOrigin(v);
              },
              onUseCurrentLocation: () async {
                final loc = await _locationService.getCurrentLocation();
                if (loc == null || !mounted) return;
                await _applyCurrentLocation(
                  loc.latitude,
                  loc.longitude,
                  overwrite: true,
                );
              },
              onPlan: _plan,
            ),
            if (provider.error != null) ...[
              const SizedBox(height: 12),
              _ErrorBanner(message: provider.error!),
            ],
            if (tc != null) ...[
              const SizedBox(height: 16),
              _CorridorSummary(context: tc),
              if (tc.needsClarification && tc.routeOptions.isNotEmpty) ...[
                const SizedBox(height: 8),
                ActionChip(
                  avatar: const Icon(Icons.alt_route, size: 18),
                  label: Text(context.t('Which route are you taking?')),
                  onPressed: () => _showClarificationDialog(provider),
                ),
              ],
              if (tc.isApproximate) ...[
                const SizedBox(height: 8),
                Text(
                  context.t('Estimated path (approximate)'),
                  style: const TextStyle(
                    color: AppTheme.textSecondary,
                    fontSize: AppTheme.textXs,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              if (_showMap && tc.geometry.isNotEmpty)
                _CorridorMap(
                  mapController: _mapController,
                  points: tc.geometry,
                  origin: tc.origin,
                  destination: tc.destination,
                ),
              const SizedBox(height: 16),
              if (provider.isLoadingIntel)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(),
                  ),
                )
              else ...[
                if (provider.intelError != null) ...[
                  _ErrorBanner(message: provider.intelError!),
                  const SizedBox(height: 12),
                ],
                _IntelSection(intel: intel),
              ],
            ],
            const SizedBox(height: 16),
            const AdInlineBanner(adContext: 'route'),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class _RouteInputCard extends StatelessWidget {
  final TravelContextProvider provider;
  final TextEditingController checkpointCtrl;
  final TextEditingController originCtrl;
  final ValueChanged<String> onOriginChanged;
  final VoidCallback onUseCurrentLocation;
  final VoidCallback onPlan;

  const _RouteInputCard({
    required this.provider,
    required this.checkpointCtrl,
    required this.originCtrl,
    required this.onOriginChanged,
    required this.onUseCurrentLocation,
    required this.onPlan,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.t('Plan a route'),
            style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('travel_origin'),
            controller: originCtrl,
            decoration: InputDecoration(
              labelText: context.t('From'),
              hintText: context.t('e.g. Kathmandu'),
              prefixIcon: const Icon(Icons.trip_origin, color: AppTheme.primaryColor),
              suffixIcon: IconButton(
                key: const Key('travel_origin_use_location'),
                icon: const Icon(Icons.my_location, size: 20, color: AppTheme.primaryColor),
                tooltip: context.t('Use current location'),
                onPressed: onUseCurrentLocation,
              ),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              isDense: true,
            ),
            onChanged: onOriginChanged,
          ),
          const SizedBox(height: 10),
          TextField(
            key: const Key('travel_destination'),
            decoration: InputDecoration(
              labelText: context.t('To'),
              hintText: context.t('e.g. Pokhara'),
              prefixIcon: const Icon(Icons.place, color: AppTheme.errorColor),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              isDense: true,
            ),
            onChanged: provider.setDestination,
          ),
          if (provider.checkpointInputs.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                for (var i = 0; i < provider.checkpointInputs.length; i++)
                  Chip(
                    label: Text(provider.checkpointInputs[i]),
                    deleteIcon: const Icon(Icons.close, size: 16),
                    onDeleted: () => provider.removeCheckpoint(i),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextField(
                  key: const Key('travel_checkpoint'),
                  controller: checkpointCtrl,
                  decoration: InputDecoration(
                    labelText: context.t('Add stop (optional)'),
                    hintText: context.t('City, place, or lat,lng'),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    isDense: true,
                  ),
                  onSubmitted: (v) {
                    provider.addCheckpoint(v);
                    checkpointCtrl.clear();
                  },
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                icon: const Icon(Icons.add),
                tooltip: context.t('Add stop'),
                onPressed: () {
                  provider.addCheckpoint(checkpointCtrl.text);
                  checkpointCtrl.clear();
                },
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('travel_plan_button'),
              onPressed: provider.isResolving ? null : onPlan,
              icon: provider.isResolving
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.travel_explore),
              label: Text(
                provider.isResolving
                    ? context.t('Resolving route…')
                    : context.t('Show what’s ahead'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;
  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.errorColor.withOpacity(0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppTheme.errorColor.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppTheme.errorColor, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppTheme.errorColor, fontSize: AppTheme.textSm),
            ),
          ),
        ],
      ),
    );
  }
}

class _CorridorSummary extends StatelessWidget {
  final TravelContextModel context;
  const _CorridorSummary({required this.context});

  @override
  Widget build(BuildContext context) {
    final t = this.context;
    final km = t.totalDistanceKm;
    final min = t.totalDurationMin;
    final statusLabel = t.status == 'routed'
        ? 'Road route'
        : t.status == 'mixed'
            ? 'Mixed route'
            : t.status == 'approximate'
                ? 'Approximate'
                : t.status;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppTheme.primaryColor, AppTheme.primaryLight],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                t.summaryLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: AppTheme.textBase,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            _MetaPill(label: statusLabel),
            if (km != null) ...[
              const SizedBox(width: 6),
              _MetaPill(label: '${km.toStringAsFixed(1)} km'),
            ],
            if (min != null) ...[
              const SizedBox(width: 6),
              _MetaPill(label: '$min min'),
            ],
            if (t.journey != null) ...[
              const SizedBox(width: 6),
              _MetaPill(
                label: '${(t.journey!.progress * 100).round()}% along',
              ),
            ],
          ],
        ),
      ],
      ),
    );
  }
}

class _MetaPill extends StatelessWidget {
  final String label;
  const _MetaPill({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.18),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: AppTheme.textXs,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _CorridorMap extends StatelessWidget {
  final MapController mapController;
  final List<TravelLatLng> points;
  final TravelPoint origin;
  final TravelPoint destination;

  const _CorridorMap({
    required this.mapController,
    required this.points,
    required this.origin,
    required this.destination,
  });

  @override
  Widget build(BuildContext context) {
    final latlngs = [for (final p in points) LatLng(p.lat, p.lng)];
    if (latlngs.length < 2) return const SizedBox.shrink();

    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: SizedBox(
        height: 240,
        child: FlutterMap(
          mapController: mapController,
          options: MapOptions(
            initialCenter: latlngs[latlngs.length ~/ 2],
            initialZoom: 9,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'np.oripori.app',
            ),
            PolylineLayer(
              polylines: [
                Polyline(
                  points: latlngs,
                  strokeWidth: 4,
                  color: AppTheme.primaryColor,
                ),
              ],
            ),
            MarkerLayer(
              markers: [
                Marker(
                  point: LatLng(origin.lat, origin.lng),
                  width: 32,
                  height: 32,
                  child: const Icon(Icons.trip_origin,
                      color: AppTheme.primaryColor, size: 28),
                ),
                Marker(
                  point: LatLng(destination.lat, destination.lng),
                  width: 32,
                  height: 32,
                  child: const Icon(Icons.place, color: AppTheme.errorColor, size: 28),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _IntelSection extends StatelessWidget {
  final RouteIntelligence? intel;
  const _IntelSection({required this.intel});

  @override
  Widget build(BuildContext context) {
    if (intel == null || intel!.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: AppTheme.primaryColor.withOpacity(0.05),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppTheme.dividerColor),
        ),
        child: Column(
          children: [
            const Icon(Icons.check_circle_outline,
                color: AppTheme.successColor, size: 36),
            const SizedBox(height: 8),
            Text(
              context.t('No significant updates along your route right now.'),
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppTheme.textSecondary,
                fontSize: AppTheme.textBase,
              ),
            ),
          ],
        ),
      );
    }

    final items = intel!.all;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('What’s ahead'),
          style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(
          '${intel!.reports.length} ${context.t('reports')} · '
          '${intel!.alerts.length} ${context.t('alerts')} · '
          '${intel!.places.length} ${context.t('places')}',
          style: const TextStyle(
            color: AppTheme.textSecondary,
            fontSize: AppTheme.textXs,
          ),
        ),
        const SizedBox(height: 12),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _IntelCard(item: items[i]),
        ),
      ],
    );
  }
}

class _IntelCard extends StatelessWidget {
  final RouteIntelItem item;
  const _IntelCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final isSevere = item.isSevere;
    final typeColor = item.type == 'alert'
        ? (isSevere ? AppTheme.errorColor : AppTheme.warningColor)
        : item.type == 'place'
            ? AppTheme.infoColor
            : AppTheme.primaryColor;

    final meta = <String>[
      if (item.direction == 'ahead') context.t('Ahead'),
      if (item.direction == 'passed') context.t('Passed'),
      if (item.direction == 'behind_severe') context.t('Behind you'),
      if (item.direction == 'broadcast') context.t('Broadcast'),
      if (item.corridorDistanceKm != null)
        '${item.corridorDistanceKm!.toStringAsFixed(1)} km ${context.t('from route')}',
      if (item.timeAgo != null) item.timeAgo!,
      if (item.district != null && item.district!.isNotEmpty) item.district!,
    ].where((s) => s.isNotEmpty).join(' · ');

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isSevere
              ? AppTheme.errorColor.withOpacity(0.45)
              : AppTheme.dividerColor,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: typeColor.withOpacity(0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              item.type == 'alert'
                  ? Icons.warning_amber
                  : item.type == 'place'
                      ? Icons.place
                      : Icons.assignment_outlined,
              color: typeColor,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: AppTheme.textBase,
                        ),
                      ),
                    ),
                    if (item.isAhead && isSevere)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.errorColor,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          context.t('Priority'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                  ],
                ),
                if (item.description != null &&
                    item.description!.trim().isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    item.description!.trim(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppTheme.textSecondary,
                      fontSize: AppTheme.textSm,
                    ),
                  ),
                ],
                if (meta.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    meta,
                    style: const TextStyle(
                      color: AppTheme.textSecondary,
                      fontSize: AppTheme.textXs,
                    ),
                  ),
                ],
                if (item.categoryName != null) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: typeColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      item.categoryName!,
                      style: TextStyle(
                        color: typeColor,
                        fontSize: AppTheme.textXs,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
