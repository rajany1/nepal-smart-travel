import 'dart:async';
import "../../core/services/localization_service.dart";
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter_compass/flutter_compass.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../core/services/offline_db_service.dart';
import '../../core/services/offline_tile_provider.dart';
import '../../core/services/app_settings_service.dart';
import '../../core/models/place.dart';
import '../../core/models/route_model.dart';
import '../../core/api/api_client.dart';
import '../../providers/place_provider.dart';
import '../../providers/map_view_provider.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';
import '../routes/routes_screen.dart';
import '../routes/route_detail_screen.dart';
import 'place_details_screen.dart';
import 'add_place_screen.dart';
import 'filter_places_sheet.dart';
import 'utils/route_polyline_utils.dart';
import 'widgets/map_blue_dot.dart';
import '../../widgets/ad_inline_banner.dart';

/// Nepal Smart Travel enhanced nearby map screen with:
/// - Satellite/Standard view toggle
/// - Real-time GPS tracking
/// - Viewport-based place fetching
/// - Offline caching
/// - FABs for My Location, Filter, Add Place
class NearbyMapScreen extends StatefulWidget {
  const NearbyMapScreen({
    super.key,
    this.destinationLat,
    this.destinationLng,
    this.destinationName,
  });

  final double? destinationLat;
  final double? destinationLng;
  final String? destinationName;

  @override
  State<NearbyMapScreen> createState() => _NearbyMapScreenState();
}

class _NearbyMapScreenState extends State<NearbyMapScreen>
    with SingleTickerProviderStateMixin {
  final LocationService _locationService = LocationService();
  final MapController _mapController = MapController();
  final DraggableScrollableController _sheetController =
      DraggableScrollableController();
  final TextEditingController _searchController = TextEditingController();
  final OfflineDbService _offlineDb = OfflineDbService.instance;
  // Standard (OSM/Carto) map layer. Bypasses the on-device tile cache because
  // that cache can hold blanks/corrupt tiles (OSM valid-but-empty PNGs) which
  // otherwise show as a permanent gray basemap. Satellite keeps its own cache.
  final OfflineTileProvider _offlineTiles =
      OfflineTileProvider(bypassNetworkCache: true);
  final OfflineTileProvider _satelliteTiles =
      OfflineTileProvider(tileType: 'satellite');

  double? _lat;
  double? _lng;
  double _currentZoom = AppConstants.defaultMapZoom;
  bool _isTracking = true;
  bool _isLoadingPlaces = false;
  bool _isFetchingPlaces = false;
  PlaceModel? _selectedPlace;
  Timer? _debounceTimer;
  Timer? _autoDownloadTimer;
  StreamSubscription? _positionStream;
  StreamController<int>? _syncStreamController;

  // Current location
  LatLng? _currentLocation;

  // Heading (degrees, clockwise from north): GPS bearing while moving,
  // compass fallback so the light rotates with the phone when standing.
  double? _gpsHeading;
  double? _compassHeading;
  DateTime _lastGpsHeadingAt = DateTime.fromMillisecondsSinceEpoch(0);
  bool _useGpsHeading = false;
  double _accuracyM = 20;
  bool _hasArrived = false;
  StreamSubscription? _compassSub;
  DateTime _lastCompassUi = DateTime.now().subtract(const Duration(milliseconds: 100));

  double? get _heading => _useGpsHeading ? _gpsHeading : _compassHeading;

  static double _shortestArc(double from, double to) {
    var d = (to - from) % 360;
    if (d > 180) d -= 360;
    if (d < -180) d += 360;
    return d;
  }

  // Compass rotation (degrees, clockwise positive)
  final ValueNotifier<double> _rotationNotifier = ValueNotifier<double>(0);

  // Route / Directions (multi-route)
  List<Map<String, dynamic>> _routes = [];
  bool _isLoadingRoute = false;
  String _sortMode = 'nearest'; // nearest | rating | featured

  // Destination (e.g. opened from Place Details "Directions")
  double? _destinationLat;
  double? _destinationLng;
  String? _destinationName;

  // Trekking / curated route overlays on the map
  List<CuratedRouteModel> _routeOverlays = [];
  bool _isLoadingRouteOverlays = false;
  PlaceFilter? _activeFilter;
  double? _lastFetchLat;
  double? _lastFetchLng;
  double _lastFetchRadius = -1;
  double? _cachedBoundsNorth, _cachedBoundsSouth, _cachedBoundsEast, _cachedBoundsWest;

  // Weather overlay state
  List<_WeatherGridPoint> _weatherGrid = [];
  Timer? _weatherDebounceTimer;

  // Label positioning state
  final Map<String, double> _labelWidthCache = {};
  String _lastLabelStateKey = '';
  int _lastPlacesHash = 0;
  List<_LabelAssignment> _lastLabelAssignments = [];

  // Grid clustering state (Nepal-wide pins at low zoom)
  static const int _clusterMinCount = 120;
  static const double _clusterMaxZoom = 12.0;
  static const int _clusterMaxPins = 400;

  // Smooth camera glide (last-known -> fresh GPS fix)
  late final AnimationController _cameraAnimController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 700),
  );
  VoidCallback? _cameraAnimListener;

  // OSM submission tracking

  Map<String, String> _osmSubmissionStatuses = {}; // osmId -> none/pending/approved

  // Nepal bounding box
  static const double _nepalMinLat = 26.347;
  static const double _nepalMaxLat = 30.447;
  static const double _nepalMinLng = 80.058;
  static const double _nepalMaxLng = 88.201;

  @override
  void initState() {
    super.initState();
    _destinationLat = widget.destinationLat;
    _destinationLng = widget.destinationLng;
    _destinationName = widget.destinationName;
    _syncStreamController = StreamController<int>.broadcast();
    _pollSyncCount();
    _initCompass();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initMap());
  }

  void _initCompass() {
    final events = FlutterCompass.events;
    if (events == null) return;
    _compassSub = events.listen((event) {
      if (!mounted) return;
      final h = event.heading;
      if (h == null) return;
      // Throttle ~20Hz: the compass fires at 200Hz+; rebuilding the map at
      // that rate is wasteful. Tiny changes are also ignored (tilt jitter).
      final now = DateTime.now();
      if (now.difference(_lastCompassUi).inMilliseconds < 50) return;
      _lastCompassUi = now;
      final h360 = (h % 360 + 360) % 360;
      final prev = _compassHeading;
      if (prev != null && _shortestArc(prev, h360).abs() < 1) return;
      setState(() {
        _compassHeading = h360;
        // When GPS bearing is stale (standing still), let the compass drive
        // the light so it follows the phone's rotation.
        if (DateTime.now().difference(_lastGpsHeadingAt).inSeconds > 4) {
          _useGpsHeading = false;
        }
      });
    });
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _autoDownloadTimer?.cancel();
    _positionStream?.cancel();
    _compassSub?.cancel();
    _syncStreamController?.close();
    _weatherDebounceTimer?.cancel();
    _rotationNotifier.dispose();
    _sheetController.dispose();
    _searchController.dispose();
    _cameraAnimController.dispose();
    super.dispose();
  }

  Future<void> _initMap() async {
    final provider = context.read<PlaceProvider>();

    await provider.fetchCategories();

    // Instant map: Nepal-wide dataset loads in parallel with GPS lookup —
    // markers paint immediately (from SQLite cache or the 10-min Redis-backed
    // /places/all), and the map recenters when GPS arrives.
    unawaited(provider.setNepalCachedPlaces());
    unawaited(provider.fetchNepalPlaces());

    // 1) Last-known location straight away — the map opens on a real spot
    //    with zero "waiting for location" state.
    final lastKnown = await _locationService.getLastKnownPosition();
    if (lastKnown != null && mounted) {
      setState(() {
        _lat = lastKnown.latitude;
        _lng = lastKnown.longitude;
        _currentLocation = LatLng(lastKnown.latitude, lastKnown.longitude);
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _recenterMap();
      });
    }

    if (mounted && _lat != null && _lng != null) {
      // Attempt to load cached data first
      await _loadCachedPlaces();

      // Viewport places come from the on-device Nepal dataset (client-side
      // nearest) — no Overpass round-trip on open.
      await _fetchPlacesForViewport();
      provider.fetchFeaturedPlaces(lat: _lat, lng: _lng);
      _fetchWeatherForViewport();
    }

    // 2) Fresh GPS fix in the background. If it moved from the last-known
    //    spot, glide the map smoothly to the live position.
    final loc = await _locationService.getCurrentLocation();
    if (loc != null && mounted) {
      final distM = _currentLocation == null
          ? double.infinity
          : const Distance()
              .as(LengthUnit.Meter, _currentLocation!, LatLng(loc.latitude, loc.longitude));
      setState(() {
        _lat = loc.latitude;
        _lng = loc.longitude;
        _currentLocation = LatLng(loc.latitude, loc.longitude);
      });
      _startPositionTracking();
      if (distM > 50 && _mapReady) {
        _smoothMoveTo(_currentLocation!);
      }
    }

    if (!mounted) return;
    if (_lat == null || _lng == null) {
      // No location at all — fall back to the default Nepal center so the
      // map (and its Nepal-wide pins) is still usable.
      setState(() {
        _lat = AppConstants.defaultLatitude;
        _lng = AppConstants.defaultLongitude;
        _currentLocation = LatLng(AppConstants.defaultLatitude, AppConstants.defaultLongitude);
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _recenterMap();
      });
    }

    // Auto-fetch route to destination (Place Details "Directions")
    if (_destinationLat != null && _destinationLng != null) {
      await _fetchDestinationRoute();
    }
  }

  /// Smoothly glide the camera to [target] (ease-in-out ~700ms).
  void _smoothMoveTo(LatLng target) {
    if (!_mapReady) return;
    final start = _mapController.camera.center;
    final zoom = _mapController.camera.zoom;
    final oldListener = _cameraAnimListener;
    if (oldListener != null) {
      _cameraAnimController.removeListener(oldListener);
    }
    final listener = () {
      final t = Curves.easeInOut.transform(_cameraAnimController.value);
      try {
        _mapController.move(
          LatLng(
            start.latitude + (target.latitude - start.latitude) * t,
            start.longitude + (target.longitude - start.longitude) * t,
          ),
          zoom,
        );
      } catch (e) {
        debugPrint('Smooth camera move failed: $e');
      }
    };
    _cameraAnimListener = listener;
    _cameraAnimController.addListener(listener);
    _cameraAnimController
      ..reset()
      ..forward();
  }

  /// flutter_map 7 MapController has no `hasMaps` getter — reading the
  /// camera throws when no map is attached yet.
  bool get _mapReady {
    try {
      _mapController.camera;
      return true;
    } catch (_) {
      return false;
    }
  }

  void _startPositionTracking() {
    _positionStream = _locationService
        .getPositionStream(intervalMs: 3000, distanceFilterM: 5)
        .listen((position) {
      final loc = LatLng(position.latitude, position.longitude);
      if (mounted) {
        setState(() {
          _currentLocation = loc;
          _accuracyM = position.accuracy;
          final gpsH = position.heading;
          if (gpsH != null && gpsH > 0) {
            _gpsHeading = (gpsH % 360 + 360) % 360;
            _lastGpsHeadingAt = DateTime.now();
            _useGpsHeading = true;
          } else {
            // No bearing (stationary) - compass takes over.
            _useGpsHeading = false;
          }
        });
      }
      if (!mounted) return;
      _checkArrival(loc);
      if (!_isTracking) return;
      setState(() {
        _lat = position.latitude;
        _lng = position.longitude;
      });
      if (_routes.isNotEmpty) {
        // A route is displayed: keep it in view instead of dragging the
        // camera to the user. Only re-fit (route + user) when they walk
        // outside the current viewport, so the route never "disappears".
        try {
          final vp = _getViewportBounds();
          final outside = position.latitude < vp.minLat ||
              position.latitude > vp.maxLat ||
              position.longitude < vp.minLng ||
              position.longitude > vp.maxLng;
          if (outside) {
            final pts = <LatLng>[loc];
            for (final r in _routes) {
              pts.addAll(r['points'] as List<LatLng>);
            }
            _mapController.fitCamera(CameraFit.bounds(
              bounds: _latLngBoundsFromPoints(pts),
              padding: const EdgeInsets.all(80),
            ));
          }
        } catch (e) {
          debugPrint('Route-aware camera fit failed: $e');
        }
      } else {
        _mapController.move(loc, _currentZoom);
      }
    });
  }

  void _checkArrival(LatLng loc) {
    if (_routes.isEmpty || _hasArrived) return;
    final selected = _routes.firstWhere(
      (r) => r['isSelected'] == true,
      orElse: () => _routes.first,
    );
    final dest = ((selected['points'] as List<LatLng>?) ?? const <LatLng>[]);
    if (dest.isEmpty) return;
    final dist = const Distance().as(LengthUnit.Meter, loc, dest.last);
    if (dist < 40) {
      _hasArrived = true;
      HapticFeedback.mediumImpact();
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 8),
          content: const Text('You have arrived at your destination'),
          action: SnackBarAction(
            label: 'Done',
            onPressed: () => _clearRoute(),
          ),
        ));
    }
  }

  void _recenterMap() {
    if (_lat != null && _lng != null) {
      try {
        _mapController.move(LatLng(_lat!, _lng!), _currentZoom);
      } catch (e) {
        debugPrint('MapController move failed: $e');
      }
    }
  }

  Future<void> _loadCachedPlaces() async {
    try {
      if (_lat == null || _lng == null) return;
      final bounds = _getViewportBounds();
      final cached = await _offlineDb.getCachedPlacesInBounds(
        minLat: bounds.minLat,
        maxLat: bounds.maxLat,
        minLng: bounds.minLng,
        maxLng: bounds.maxLng,
      );
      if (cached.isNotEmpty && mounted) {
        final provider = context.read<PlaceProvider>();
        final places = cached.map((j) => PlaceModel.fromJson(j)).toList();
        provider.setCachedPlaces(places);
      }
    } catch (e) {
      debugPrint('Failed to load cached places: $e');
    }
  }

  _ViewportBounds _getViewportBounds() {
    if (_lat == null || _lng == null) {
      return _ViewportBounds(
        minLat: _nepalMinLat,
        maxLat: _nepalMaxLat,
        minLng: _nepalMinLng,
        maxLng: _nepalMaxLng,
      );
    }

    final zoom = _currentZoom;
    final viewLatSpan = 180.0 / math.pow(2, zoom) * 0.8;
    final viewLngSpan = 360.0 / math.pow(2, zoom) * 0.8;

    return _ViewportBounds(
      minLat: math.max(_lat! - viewLatSpan, _nepalMinLat),
      maxLat: math.min(_lat! + viewLatSpan, _nepalMaxLat),
      minLng: math.max(_lng! - viewLngSpan, _nepalMinLng),
      maxLng: math.min(_lng! + viewLngSpan, _nepalMaxLng),
    );
  }

  void _onMapMoved(MapCamera camera) {
    setState(() {
      _isTracking = false;
      _currentZoom = camera.zoom;
      // Update lat/lng to the center of the visible map viewport
      // so place fetching uses the correct map area, not the stale device location
      _lat = camera.center.latitude;
      _lng = camera.center.longitude;
    });

    // Throttle place fetching on map move - 300ms delay after movement stops
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      _fetchPlacesForViewport();
    });

    // Throttle weather fetch on map move
    _weatherDebounceTimer?.cancel();
    _weatherDebounceTimer = Timer(const Duration(milliseconds: 300), () {
      _fetchWeatherForViewport();
    });

    // Auto-download maps: background-cache the visible area once the map
    // settles, so the region works offline later.
    if (_currentZoom >= 8 && _lat != null && _lng != null) {
      _autoDownloadTimer?.cancel();
      _autoDownloadTimer = Timer(const Duration(seconds: 2), () async {
        if (!await AppSettingsService.autoDownloadMaps) return;
        OfflineTileDownloader.downloadRegion(
          minLat: _lat! - 0.15,
          maxLat: _lat! + 0.15,
          minLng: _lng! - 0.2,
          maxLng: _lng! + 0.2,
          minZoom: 8,
          maxZoom: 16,
        );
      });
    }
  }

  Future<void> _fetchPlacesForViewport({String? search}) async {
    if (_lat == null || _lng == null) return;

    final provider = context.read<PlaceProvider>();

    // Fast path (default): the Nepal-wide dataset is already on device —
    // nearest-first viewport list computed client-side. Instant, offline-safe,
    // and never shows the "Updating places..." overlay.
    if (provider.nepalPlaces.isNotEmpty) {
      final filtered = _applyPlaceFilters(provider.nepalPlaces);
      final center = LatLng(_lat!, _lng!);
      final distance = const Distance();
      final sorted = filtered
          .map((p) => p.copyWith(
                distanceKm: distance.as(
                  LengthUnit.Kilometer,
                  center,
                  LatLng(p.latitude, p.longitude),
                ),
              ))
          .toList()
        ..sort((a, b) => (a.distanceKm ?? 0).compareTo(b.distanceKm ?? 0));
      provider.setViewportPlaces(sorted.take(60).toList());
      _checkOsmSubmissionStatuses();
      return;
    }

    // Cold start (offline, nothing cached yet): legacy network fallback.
    if (_currentZoom < 10) return;
    if (_isFetchingPlaces) return;

    final radius = _zoomToRadius(_currentZoom);

    // BBox cache check: if current viewport is inside cached area, skip API call
    if (search == null &&
        _cachedBoundsNorth != null &&
        _lat! <= _cachedBoundsNorth! &&
        _lat! >= _cachedBoundsSouth! &&
        _lng! <= _cachedBoundsEast! &&
        _lng! >= _cachedBoundsWest!) {
      return;
    }

    _isFetchingPlaces = true;
    setState(() => _isLoadingPlaces = true);

    try {
      // Fetch 2x the visible radius so panning within cached area is instant
      final fetchRadius = radius * 2;
      await provider.fetchNearbyPlaces(
        lat: _lat!,
        lng: _lng!,
        radiusKm: fetchRadius,
        categoryId: _activeFilter?.categoryId,
        search: search ?? _activeFilter?.search,
      );

      _lastFetchLat = _lat;
      _lastFetchLng = _lng;
      _lastFetchRadius = radius;

      // Save BBox for cache check
      final latKm = 111.32;
      final lngKm = 111.32 * math.cos(_lat! * math.pi / 180);
      _cachedBoundsNorth = _lat! + (fetchRadius / latKm);
      _cachedBoundsSouth = _lat! - (fetchRadius / latKm);
      _cachedBoundsEast = _lng! + (fetchRadius / lngKm);
      _cachedBoundsWest = _lng! - (fetchRadius / lngKm);

      // Cache the fetched places offline
      final placesJson = provider.places.map((p) => {
            'id': p.id.toString(),
            'name': p.name,
            'description': p.description,
            'latitude': p.latitude,
            'longitude': p.longitude,
            'category': p.category,
            'source': p.source,
            'is_verified': p.isVerified,
            'is_featured': p.isFeatured,
            'average_rating': p.averageRating,
            'total_reviews': p.totalReviews,
            'distance_km': p.distanceKm,
            'images': p.images,
          }).toList();

      await _offlineDb.cachePlacesBulk(placesJson);
    } catch (e) {
      debugPrint('Failed to fetch places for viewport: $e');
    }

    _isFetchingPlaces = false;
    if (mounted) {
      setState(() => _isLoadingPlaces = false);
      _checkOsmSubmissionStatuses();
    }
  }

  double _zoomToRadius(double zoom) {
    if (zoom >= 16) return 1.0;
    if (zoom >= 14) return 3.0;
    if (zoom >= 12) return 8.0;
    if (zoom >= 10) return 20.0;
    return 50.0;
  }

  double _distanceFromUser(PlaceModel place) {
    if (_currentLocation == null) return (place.distanceKm ?? 0);
    const double earthRadius = 6371;
    final double dLat = (place.latitude - _currentLocation!.latitude) * math.pi / 180;
    final double dLng = (place.longitude - _currentLocation!.longitude) * math.pi / 180;
    final double a = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(_currentLocation!.latitude * math.pi / 180) *
            math.cos(place.latitude * math.pi / 180) *
            math.sin(dLng / 2) * math.sin(dLng / 2);
    return earthRadius * 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));
  }

  Future<void> _checkOsmSubmissionStatuses() async {
    if (context == null || !mounted) return;
    final provider = context.read<PlaceProvider>();
    final osmIds = provider.places
        .where((p) => p.source == 'osm')
        .map((p) => p.id.toString())
        .where((id) => id.isNotEmpty)
        .toSet()
        .toList();

    if (osmIds.isEmpty) return;

    try {
      final response = await ApiClient.instance.dio.post(
        '/places/osm-status',
        data: {'osm_ids': osmIds},
      );
      if (response.data?['success'] == true && response.data?['data'] != null) {
        final data = Map<String, String>.from(
          (response.data['data'] as Map).map((k, v) => MapEntry(k.toString(), v.toString())),
        );
        if (mounted) {
          setState(() => _osmSubmissionStatuses = data);
        }
      }
    } catch (_) {}
  }

  void _confirmSaveOsmPlace(PlaceModel place) {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(ctx.t('Login required')),
          content: Text('${ctx.t('Log in to save')} "${place.name}" ${ctx.t('to our local database.')}'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.t('Cancel'))),
            FilledButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
              },
              child: Text(ctx.t('Log in')),
            ),
          ],
        ),
      );
      return;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.t('Save to Database')),
        content: Text('${ctx.t('Add')} "${place.name}" ${ctx.t('to our local database?')}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.t('Cancel'))),
          FilledButton.icon(
            onPressed: () async {
              Navigator.pop(ctx);
              final result = await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => AddPlaceScreen(
                    initialLat: place.latitude,
                    initialLng: place.longitude,
                    initialName: place.name,
                    initialDescription: place.description,
                    initialAddress: place.address,
                    initialCategory: place.category,
                    osmId: place.id.toString(),
                  ),
                ),
              );
              if (mounted) {
                if (result == true) {
                  setState(() {
                    _osmSubmissionStatuses[place.id.toString()] = 'pending';
                  });
                } else {
                  _checkOsmSubmissionStatuses();
                }
              }
            },
            icon: const Icon(Icons.save, size: 18),
            label: Text(ctx.t('Save')),
          ),
        ],
      ),
    );
  }

  Widget _buildOsmSaveButton(PlaceModel place) {
    final osmId = place.id.toString();
    final status = _osmSubmissionStatuses[osmId] ?? 'none';

    if (status == 'approved') return const SizedBox.shrink();

    final bool isPending = status == 'pending';
    final String tooltip;
    if (status == 'rejected') {
      tooltip = context.t('Re-submit (was rejected)');
    } else if (isPending) {
      tooltip = context.t('Pending review');
    } else {
      tooltip = context.t('Save to database');
    }
    return Tooltip(
      message: tooltip,
      child: GestureDetector(
        onTap: isPending ? null : () => _confirmSaveOsmPlace(place),
        child: Container(
          padding: const EdgeInsets.all(6),
          margin: const EdgeInsets.only(right: 4),
          decoration: BoxDecoration(
            color: isPending ? Colors.grey.shade200 : Colors.green.shade50,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(
            isPending ? Icons.hourglass_empty : Icons.save_outlined,
            size: 16,
            color: isPending ? Colors.grey.shade400 : Colors.green.shade700,
          ),
        ),
      ),
    );
  }

  void _onPlaceTap(PlaceModel place) async {
    setState(() => _selectedPlace = place);
    try {
      _mapController.move(LatLng(place.latitude, place.longitude), 15.0);
      _sheetController.animateTo(
        0.25,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    } catch (e) {
      debugPrint('Map move failed: $e');
    }

    await _offlineDb.addRecentlyViewed(place.id.toString());
  }

  void _navigateToDetails(PlaceModel place) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PlaceDetailsScreen(place: place.toPlace()),
      ),
    );
  }

  Future<void> _getDirections(PlaceModel place) async {
    _hasArrived = false;
    final msgNoRoutes = context.tr('No valid routes found');
    final msgFetchFailed = context.tr('Could not fetch route. Please try again.');
    var originLat = _currentLocation?.latitude ?? _lat;
    var originLng = _currentLocation?.longitude ?? _lng;
    if (originLat == null || originLng == null) {
      originLat = 28.3949;
      originLng = 84.1240;
    }
    setState(() => _isLoadingRoute = true);
    try {
      final response = await ApiClient.instance.getDirections(
        fromLat: originLat,
        fromLng: originLng,
        toLat: place.latitude,
        toLng: place.longitude,
      );
      final data = response.data['routes'] as List? ?? [];
      final parsed = <Map<String, dynamic>>[];
      for (final r in data) {
        final pts = <LatLng>[];
        final offRoad = <bool>[];
        for (final p in (r['points'] as List)) {
          final m = p as Map;
          pts.add(LatLng(
            ((m['lat'] as num)).toDouble(),
            ((m['lng'] as num)).toDouble(),
          ));
          offRoad.add(m['offRoad'] == true);
        }
        if (pts.length > 1) {
          parsed.add({
            'points': pts,
            'offRoad': offRoad,
            'distance': (r['distance'] as num).toDouble(),
            'duration': (r['duration'] as num).toDouble(),
          });
        }
      }
      if (parsed.isNotEmpty) {
        debugPrint('Directions OK: ${parsed.length} route(s) for ${place.name}');
        setState(() => _routes = parsed);
        final allPoints = parsed.expand((r) => r['points'] as List<LatLng>).toList();
        final bounds = _latLngBoundsFromPoints(allPoints);
        final cameraFit = CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(60));
        try {
          _mapController.fitCamera(cameraFit);
        } catch (e) {
          debugPrint('fitCamera failed: $e');
        }
      } else {
        _showRouteError(msgNoRoutes);
      }
    } catch (e) {
      debugPrint('Route error: $e');
      _showRouteError(msgFetchFailed);
    }
    if (mounted) setState(() => _isLoadingRoute = false);
  }

  void _showRouteError(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: Colors.red.shade700,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  void _clearRoute() {
    _hasArrived = false;
    setState(() => _routes = []);
  }

  Future<void> _fetchDestinationRoute() async {
    if (_destinationLat == null || _destinationLng == null) return;
    _hasArrived = false;
    final msgNoRoutes = context.tr('No valid routes found');
    final msgFetchFailed = context.tr('Could not fetch route. Please try again.');
    var originLat = _currentLocation?.latitude ?? _lat;
    var originLng = _currentLocation?.longitude ?? _lng;
    if (originLat == null || originLng == null) {
      originLat = 28.3949;
      originLng = 84.1240;
    }
    setState(() => _isLoadingRoute = true);
    try {
      final response = await ApiClient.instance.getDirections(
        fromLat: originLat,
        fromLng: originLng,
        toLat: _destinationLat!,
        toLng: _destinationLng!,
      );
      final data = response.data['routes'] as List? ?? [];
      final parsed = <Map<String, dynamic>>[];
      for (final r in data) {
        final pts = <LatLng>[];
        final offRoad = <bool>[];
        for (final p in (r['points'] as List)) {
          final m = p as Map;
          pts.add(LatLng(
            ((m['lat'] as num)).toDouble(),
            ((m['lng'] as num)).toDouble(),
          ));
          offRoad.add(m['offRoad'] == true);
        }
        if (pts.length > 1) {
          parsed.add({
            'points': pts,
            'offRoad': offRoad,
            'distance': (r['distance'] as num).toDouble(),
            'duration': (r['duration'] as num).toDouble(),
          });
        }
      }
      if (parsed.isNotEmpty) {
        setState(() => _routes = parsed);
        final allPoints = parsed.expand((r) => r['points'] as List<LatLng>).toList()
          ..add(LatLng(_destinationLat!, _destinationLng!))
          ..add(LatLng(originLat, originLng));
        final bounds = _latLngBoundsFromPoints(allPoints);
        try {
          _mapController.fitCamera(
            CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(80)),
          );
        } catch (e) {
          debugPrint('fitCamera failed: $e');
        }
      } else {
        _showRouteError(msgNoRoutes);
      }
    } catch (e) {
      debugPrint('Destination route error: $e');
      _showRouteError(msgFetchFailed);
    }
    if (mounted) setState(() => _isLoadingRoute = false);
  }

  LatLngBounds _latLngBoundsFromPoints(List<LatLng> points) {
    if (points.isEmpty) return LatLngBounds(const LatLng(0, 0), const LatLng(0, 0));
    double minLat = points.first.latitude;
    double maxLat = points.first.latitude;
    double minLng = points.first.longitude;
    double maxLng = points.first.longitude;
    for (final p in points) {
      if (p.latitude < minLat) minLat = p.latitude;
      if (p.latitude > maxLat) maxLat = p.latitude;
      if (p.longitude < minLng) minLng = p.longitude;
      if (p.longitude > maxLng) maxLng = p.longitude;
    }
    return LatLngBounds(
      LatLng(minLat, minLng),
      LatLng(maxLat, maxLng),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // Map with tile mode switch (single map, single controller)
          Consumer<MapViewProvider>(
            builder: (context, mapView, _) {
              return _buildFlutterMap(
                isSatellite: mapView.isSatellite,
                placesVisible: mapView.showPlaces,
                showWeather: mapView.showWeather,
                showRoutes: mapView.showRoutes,
              );
            },
          ),

          // Map mode toggle button
          Positioned(
            top: MediaQuery.of(context).padding.top + 50,
            left: 16,
            child: _buildMapModeToggle(),
          ),

          // Trekking & curated routes button
          Positioned(
            top: MediaQuery.of(context).padding.top + 104,
            left: 16,
            child: _buildRoutesButton(),
          ),

          // Compass (N) indicator - top right, shows north direction
          Positioned(
            top: MediaQuery.of(context).padding.top + 62,
            right: 16,
            child: _buildCompass(),
          ),

          // Search bar
          Positioned(
            top: MediaQuery.of(context).padding.top + 4,
            left: 60,
            right: 16,
            child: _buildSearchBar(),
          ),

          // Floating action buttons (right side)
          Positioned(
            right: 16,
            bottom: 140,
            child: _buildFloatingActions(),
          ),

          // Syncing indicator
          _buildSyncIndicator(),

          // Loading overlay
          if (_isLoadingPlaces)
            Positioned(
              top: 120,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2)),
                      const SizedBox(width: 10),
                      Text(context.t('Updating places...'),
                          style: const TextStyle(
                              fontSize: 13, color: AppTheme.textSecondary)),
                    ],
                  ),
                ),
              ),
            ),

          if (_lat == null || _lng == null)
            Positioned(
              top: MediaQuery.of(context).size.height * 0.22,
              left: 20,
              right: 20,
              child: Card(
                color: Colors.white.withOpacity(0.95),
                elevation: 4,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.my_location, color: AppTheme.errorColor),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              context.t('Waiting for your current location... Please enable GPS and allow location permission.'),
                              style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                            ),
                          ),
                        ],
                      ),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(
                          onPressed: _retryLocation,
                          icon: const Icon(Icons.refresh, size: 16),
                          label: Text(context.t('Try Again')),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          _buildBottomSheet(),
        ],
      ),
    );
  }

  Widget _buildFlutterMap({
    required bool isSatellite,
    required bool placesVisible,
    required bool showWeather,
    required bool showRoutes,
  }) {
    return FlutterMap(
      mapController: _mapController,
      options: MapOptions(
        initialCenter: LatLng(
            _lat ?? AppConstants.defaultLatitude,
            _lng ?? AppConstants.defaultLongitude),
        initialZoom: AppConstants.defaultMapZoom,
        maxZoom: AppConstants.maxMapZoom,
        minZoom: 6.0,
        cameraConstraint: CameraConstraint.contain(
          bounds: LatLngBounds(
            const LatLng(26.0, 79.5),
            const LatLng(31.0, 89.0),
          ),
        ),
        interactionOptions: const InteractionOptions(
          flags: InteractiveFlag.all,
        ),
        onMapEvent: (event) {
          if (event is MapEventMoveEnd) {
            _onMapMoved(event.camera);
          }
        },
        onPositionChanged: (camera, hasGesture) {
          _rotationNotifier.value = camera.rotation;
        },
        onTap: (_, __) {
          setState(() => _selectedPlace = null);
        },
      ),
      children: [
        if (isSatellite) ...[
          TileLayer(
            urlTemplate: 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
            fallbackUrl:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            userAgentPackageName: 'np.com.nepalsmarttravel',
            maxZoom: 19,
            tileProvider: _satelliteTiles,
          ),
          ColorFiltered(
            colorFilter: const ColorFilter.matrix(<double>[
              1, 0, 0, 0, 0,
              0, 1, 0, 0, 0,
              0, 0, 1, 0, 0,
              -1.0/3.0, -1.0/3.0, -1.0/3.0, 1, 0,
            ]),
            child: TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'np.com.nepalsmarttravel',
              maxZoom: 20,
              tileProvider: _offlineTiles,
            ),
          ),
        ] else
          TileLayer(
            // OSM raster for the standard (non-satellite) layer. A Carto
            // (OSM-based) fallback is provided so the map still renders real
            // tiles if tile.openstreetmap.org is slow/blocked/rate-limited.
            urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            fallbackUrl:
                'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            userAgentPackageName: 'np.com.nepalsmarttravel',
            maxZoom: 19,
            tileProvider: _offlineTiles,
          ),
        if (showWeather && _weatherGrid.isNotEmpty)
          PolygonLayer(polygons: _buildWeatherPolygons()),
        if (_routes.isNotEmpty)
          PolylineLayer(
            polylines: [
              for (int i = 0; i < _routes.length; i++)
                ...buildRoutePolylines(
                  _routes[i]['points'] as List<LatLng>,
                  _routes[i]['offRoad'] as List<bool>? ??
                      List<bool>.filled(
                          (_routes[i]['points'] as List<LatLng>).length, false),
                  color: i == 0
                      ? const Color(0xFF4285F4).withOpacity(0.85)
                      : Colors.grey.withOpacity(0.5),
                  strokeWidth: i == 0 ? 5 : 3,
                ),
            ],
          ),
        // Trekking / curated routes overlay
        if (showRoutes && _routeOverlays.isNotEmpty)
          PolylineLayer(
            polylines: [
              for (final r in _routeOverlays)
                if (r.track.length > 1)
                  Polyline(
                    points: r.track.map((p) => LatLng(p.lat, p.lng)).toList(),
                    color: r.isTrekking
                        ? const Color(0xFFB45309).withOpacity(0.9)
                        : AppTheme.primaryColor.withOpacity(0.9),
                    strokeWidth: 4,
                  ),
            ],
          ),
        if (showRoutes && _routeOverlays.isNotEmpty)
          MarkerLayer(
            markers: [
              for (final r in _routeOverlays)
                if (r.track.isNotEmpty) ...[
                  Marker(
                    point: LatLng(r.track.first.lat, r.track.first.lng),
                    width: 34,
                    height: 34,
                    child: _buildRouteOverlayMarker(r, isStart: true),
                  ),
                  if (r.track.length > 1)
                    Marker(
                      point: LatLng(r.track.last.lat, r.track.last.lng),
                      width: 34,
                      height: 34,
                      child: _buildRouteOverlayMarker(r, isStart: false),
                    ),
                ],
            ],
          ),
        // "You are here" indicator - always visible regardless of places toggle
        if (_currentLocation != null)
          MarkerLayer(
            markers: [
              Marker(
                point: _currentLocation!,
                width: 200,
                height: 200,
                alignment: Alignment.center,
                child: MapBlueDot(
                  heading: _heading,
                  accuracyMeters: _accuracyM,
                  zoom: _currentZoom,
                  latitude: _currentLocation!.latitude,
                ),
              ),
            ],
          ),
        if (_destinationLat != null && _destinationLng != null)
          MarkerLayer(
            markers: [
              Marker(
                point: LatLng(_destinationLat!, _destinationLng!),
                width: 36,
                height: 36,
                alignment: Alignment.center,
                child: Container(
                  decoration: BoxDecoration(
                    color: const Color(0xFFE91E63),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFFE91E63).withOpacity(0.4),
                        blurRadius: 6,
                        spreadRadius: 1,
                      ),
                    ],
                  ),
                  child: const Icon(Icons.flag, color: Colors.white, size: 18),
                ),
              ),
            ],
          ),
        if (placesVisible)
          Consumer<PlaceProvider>(
            builder: (context, provider, _) {
              return MarkerLayer(
                markers: _buildMarkers(_markerPlacesForViewport(provider)),
              );
            },
          ),
      ],
    );
  }

  Widget _buildCompass() {
    return ValueListenableBuilder<double>(
      valueListenable: _rotationNotifier,
      builder: (context, rotation, _) {
        final isNorthUp = rotation.abs() < 0.5;
        return AnimatedOpacity(
          opacity: isNorthUp ? 0.45 : 1.0,
          duration: const Duration(milliseconds: 200),
          child: Material(
            elevation: 3,
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            shadowColor: Colors.black26,
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: _resetRotationToNorth,
              child: SizedBox(
                width: 44,
                height: 44,
                child: Transform.rotate(
                  angle: rotation * math.pi / 180,
                  child: const Column(
                    mainAxisSize: MainAxisSize.min,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.arrow_upward, size: 16, color: Colors.red),
                      Text(
                        'N',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: Colors.red,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  void _resetRotationToNorth() {
    _rotationNotifier.value = 0;
    _mapController.rotate(0);
  }

  Future<void> _loadRouteOverlays() async {
    if (_routeOverlays.isNotEmpty || _isLoadingRouteOverlays) return;
    setState(() => _isLoadingRouteOverlays = true);
    try {
      final res = await ApiClient.instance.getRoutes(withTrack: true, limit: 50);
      final data = (res.data['routes'] as List<dynamic>?) ?? [];
      final overlays = data
          .map((e) => CuratedRouteModel.fromJson(e as Map<String, dynamic>))
          .where((r) => r.track.length > 1)
          .toList();
      if (mounted) setState(() => _routeOverlays = overlays);
    } catch (e) {
      debugPrint('Route overlays load failed: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.t('Could not load trekking routes')),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
    if (mounted) setState(() => _isLoadingRouteOverlays = false);
  }

  Widget _buildRouteOverlayMarker(CuratedRouteModel route, {required bool isStart}) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => RouteDetailScreen(routeId: route.id)),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: isStart ? const Color(0xFF2E7D32) : const Color(0xFFC62828),
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 2),
          boxShadow: const [
            BoxShadow(color: Colors.black26, blurRadius: 3),
          ],
        ),
        alignment: Alignment.center,
        child: Icon(
          isStart ? Icons.flag : Icons.sports_score,
          size: 16,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildRoutesButton() {
    return Material(
      elevation: 3,
      color: Colors.white,
      borderRadius: BorderRadius.circular(24),
      shadowColor: Colors.black26,
      child: InkWell(
        borderRadius: BorderRadius.circular(24),
        onTap: () {
          HapticFeedback.lightImpact();
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const RoutesScreen()),
          );
        },
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.hiking, size: 20, color: Color(0xFFB45309)),
              const SizedBox(width: 6),
              Text(
                context.t('Routes'),
                style: const TextStyle(
                  fontSize: AppTheme.textSm,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.textPrimary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMapModeToggle() {
    return Consumer<MapViewProvider>(
      builder: (context, mapView, _) {
        return AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.15),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(24),
              onTap: () {
                HapticFeedback.lightImpact();
                mapView.toggleMapMode();
              },
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      mapView.isSatellite ? Icons.map : Icons.satellite,
                      size: 18,
                      color: AppTheme.primaryColor,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      mapView.isSatellite ? context.t('Standard') : context.t('Satellite'),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildFloatingActions() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _mapFAB(
          icon: Icons.my_location,
          color: _isTracking ? AppTheme.primaryColor : Colors.white,
          iconColor: _isTracking ? Colors.white : AppTheme.primaryColor,
          onTap: _onMyLocationTap,
        ),
        const SizedBox(height: 8),
        Container(width: 32, height: 1, color: Colors.grey.shade200),
        const SizedBox(height: 8),
        _mapFAB(
          icon: Icons.filter_list,
          color: Colors.white,
          iconColor: AppTheme.textPrimary,
          onTap: _onFilterTap,
        ),
        const SizedBox(height: 4),
        _mapFAB(
          icon: Icons.add_location,
          color: AppTheme.secondaryColor,
          iconColor: Colors.white,
          onTap: _onAddPlaceTap,
        ),
      ],
    );
  }

  Widget _mapFAB({
    required IconData icon,
    required VoidCallback onTap,
    Color? color,
    Color? iconColor,
  }) {
    return Material(
      elevation: 3,
      color: color ?? Colors.white,
      borderRadius: BorderRadius.circular(12),
      shadowColor: Colors.black26,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          child: Icon(icon, size: 22, color: iconColor ?? AppTheme.textPrimary),
        ),
      ),
    );
  }

  void _onMyLocationTap() {
    // Always recenter to the user's exact location, no toggle confusion.
    if (_currentLocation != null) {
      setState(() => _isTracking = true);
      _mapController.move(_currentLocation!, 15.0);
      return;
    }
    if (_lat != null && _lng != null) {
      setState(() => _isTracking = true);
      _mapController.move(LatLng(_lat!, _lng!), 15.0);
      return;
    }
    _requestLocationAndRecenter();
  }

  Future<void> _requestLocationAndRecenter() async {
    final msgGettingLocation = context.t('Getting your exact location...');
    final msgLocationFailed = context.t('Could not get your location. Please enable GPS and allow location permission, then try again.');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msgGettingLocation),
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 2),
      ),
    );
    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;
    if (loc == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msgLocationFailed),
          backgroundColor: AppTheme.errorColor,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
        ),
      );
      return;
    }
    setState(() {
      _lat = loc.latitude;
      _lng = loc.longitude;
      _currentLocation = LatLng(loc.latitude, loc.longitude);
      _isTracking = true;
    });
    _startPositionTracking();
    _mapController.move(_currentLocation!, 15.0);
  }

  Future<void> _retryLocation() async {
    final msgLocationFailed = context.t('Could not get your location. Please enable GPS and allow location permission, then try again.');
    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;
    if (loc == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msgLocationFailed),
          backgroundColor: AppTheme.errorColor,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
        ),
      );
      return;
    }
    setState(() {
      _lat = loc.latitude;
      _lng = loc.longitude;
      _currentLocation = LatLng(loc.latitude, loc.longitude);
    });
    _startPositionTracking();
    final provider = context.read<PlaceProvider>();
    await _loadCachedPlaces();
    await provider.fetchNearbyPlaces(lat: _lat!, lng: _lng!, radiusKm: 10.0);
    _lastFetchLat = _lat;
    _lastFetchLng = _lng;
    _lastFetchRadius = 10.0;
    _cachedBoundsNorth = null;
    _cachedBoundsSouth = null;
    _cachedBoundsEast = null;
    _cachedBoundsWest = null;
    try {
      _mapController.move(LatLng(_lat!, _lng!), 15.0);
    } catch (e) {
      debugPrint('Map move failed: $e');
    }
  }

  void _onFilterTap() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => FilterPlacesSheet(
        initialFilter: _activeFilter,
        onApply: (filters) {
          setState(() => _activeFilter = filters);
          _debounceTimer?.cancel();
          _lastFetchLat = null; // Force re-fetch with the new filter
          _lastFetchLng = null;
          _cachedBoundsNorth = null; // Invalidate bbox cache so the filter is applied
          _cachedBoundsSouth = null;
          _cachedBoundsEast = null;
          _cachedBoundsWest = null;
          _fetchPlacesForViewport();
        },
      ),
    ).then((_) {
      // Overlay toggles live in the filter sheet now; load trekking routes
      // if the user enabled them there.
      if (mounted && context.read<MapViewProvider>().showRoutes) {
        _loadRouteOverlays();
      }
    });
  }

  void _onAddPlaceTap() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => AddPlaceScreen(
          initialLat: _lat,
          initialLng: _lng,
        ),
      ),
    );
  }

  Widget _buildSyncIndicator() {
    return StreamBuilder<int>(
      stream: _syncCountStream(),
      builder: (context, snapshot) {
        final count = snapshot.data ?? 0;
        if (count == 0) return const SizedBox.shrink();

        return Positioned(
          top: MediaQuery.of(context).padding.top + 100,
          left: 60,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.orange.shade50,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.orange.shade200),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: 12,
                  height: 12,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor:
                        AlwaysStoppedAnimation(Colors.orange.shade700),
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  '$count ${context.t('pending sync')}',
                  style: TextStyle(fontSize: 11, color: Colors.orange.shade800),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Stream<int> _syncCountStream() {
    return _syncStreamController!.stream;
  }

  Future<void> _pollSyncCount() async {
    while (!_syncStreamController!.isClosed) {
      try {
        final count = await _offlineDb.getPendingSyncCount();
        if (!_syncStreamController!.isClosed) {
          _syncStreamController!.add(count);
        }
      } catch (_) {}
      await Future.delayed(const Duration(seconds: 10));
    }
  }

  Widget _buildSearchBar() {
    return Material(
      elevation: 4,
      borderRadius: BorderRadius.circular(28),
      shadowColor: Colors.black26,
      child: TextField(
        controller: _searchController,
        decoration: InputDecoration(
          hintText: context.t('Search places in Nepal...'),
          hintStyle: TextStyle(color: Colors.grey.shade500, fontSize: AppTheme.textBase),
          prefixIcon:
              Icon(Icons.search, color: AppTheme.primaryColor, size: 22),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear, size: 20),
                  onPressed: () {
                    _searchController.clear();
                    _debounceTimer?.cancel();
                    _lastFetchLat = null;
                    _fetchPlacesForViewport();
                  },
                )
              : null,
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide.none,
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(28),
            borderSide: BorderSide.none,
          ),
        ),
        style: const TextStyle(fontSize: AppTheme.textBase),
        textInputAction: TextInputAction.search,
        onChanged: (value) {
          setState(() {});
        },
        onSubmitted: (value) {
          _debounceTimer?.cancel();
          _lastFetchLat = null; // Force re-fetch for search
          _lastFetchLng = null;
          _fetchPlacesForViewport(search: value.isNotEmpty ? value : null);
          FocusScope.of(context).unfocus();
        },
      ),
    );
  }

  Widget _buildBottomSheet() {
    return Consumer<PlaceProvider>(
      builder: (context, provider, _) {
        return _buildBottomSheetContent(provider);
      },
    );
  }

  List<PlaceModel> _applyPlaceFilters(List<PlaceModel> places) {
    var result = places;
    final f = _activeFilter;

    final showFeatured = f?.onlyFeatured ?? false;
    if (showFeatured) {
      result = result.where((p) => p.isFeatured).toList();
    }
    if (f?.onlyVerified == true) {
      result = result.where((p) => p.isVerified).toList();
    }
    if (f?.categoryId != null) {
      String? categoryName;
      for (final c in context.read<PlaceProvider>().categories) {
        if (c.id == f!.categoryId) {
          categoryName = c.name;
          break;
        }
      }
      if (categoryName != null && categoryName.toLowerCase() != 'all') {
        final match = categoryName.toLowerCase();
        result = result
            .where((p) => (p.category ?? '').toLowerCase() == match)
            .toList();
      }
    }
    final q = f?.search;
    if (q != null && q.isNotEmpty) {
      final query = q.toLowerCase();
      result = result
          .where((p) => p.name.toLowerCase().contains(query))
          .toList();
    }
    return result;
  }

  List<PlaceModel> _sortedPlaces(List<PlaceModel> places) {
    final result = List<PlaceModel>.from(places);
    switch (_sortMode) {
      case 'rating':
        result.sort((a, b) {
          final ra = a.averageRating ?? -1.0;
          final rb = b.averageRating ?? -1.0;
          return rb.compareTo(ra);
        });
      case 'featured':
        result.sort((a, b) {
          if (a.isFeatured != b.isFeatured) return a.isFeatured ? -1 : 1;
          final ra = a.averageRating ?? -1.0;
          final rb = b.averageRating ?? -1.0;
          return rb.compareTo(ra);
        });
      default:
        result.sort((a, b) {
          final da = _distanceFromUser(a);
          final db = _distanceFromUser(b);
          return da.compareTo(db);
        });
    }
    return result;
  }

  Widget _buildSortChip(String mode, IconData icon, String label) {
    final selected = _sortMode == mode;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () => setState(() => _sortMode = mode),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: selected
              ? AppTheme.primaryColor.withOpacity(0.1)
              : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? AppTheme.primaryColor : Colors.grey.shade300,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon,
                size: 14,
                color: selected
                    ? AppTheme.primaryColor
                    : Colors.grey.shade500),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: selected
                    ? AppTheme.primaryColor
                    : AppTheme.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomSheetContent(PlaceProvider provider) {
    final displayPlaces = _sortedPlaces(_applyPlaceFilters(provider.places));
    return DraggableScrollableSheet(
      controller: _sheetController,
      initialChildSize: 0.22,
      minChildSize: 0.08,
      maxChildSize: 0.55,
      snap: true,
      snapSizes: const [0.08, 0.22, 0.45],
      builder: (context, scrollController) {
        return SafeArea(
          top: false,
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 10,
                  offset: Offset(0, -2),
                ),
              ],
            ),
            child: CustomScrollView(
              controller: scrollController,
              slivers: [
                SliverToBoxAdapter(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 12, bottom: 6),
                        child: Container(
                          width: 48,
                          height: 6,
                          decoration: BoxDecoration(
                            color: Colors.grey.shade300,
                            borderRadius: BorderRadius.circular(3),
                          ),
                        ),
                      ),
                      if (_selectedPlace != null)
                        _buildSheetSelectedPreview(_selectedPlace!, () {
                          _navigateToDetails(_selectedPlace!);
                        }),
                      if (_selectedPlace == null &&
                          _destinationLat != null &&
                          _destinationLng != null)
                        _buildDestinationPreview(),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              context.t('Nearby Places'),
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w600,
                                color: AppTheme.textPrimary,
                              ),
                            ),
                            Row(
                              children: [
                                if (displayPlaces.isNotEmpty)
                                  Text(
                                    '${displayPlaces.length} ${context.t('found')}',
                                    style: TextStyle(
                                        fontSize: 13, color: Colors.grey.shade500),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const Divider(height: 1, thickness: 1),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                        child: Row(
                          children: [
                            _buildSortChip('nearest', Icons.near_me, context.t('Nearest')),
                            const SizedBox(width: 8),
                            _buildSortChip('rating', Icons.star, context.t('Rating')),
                            const SizedBox(width: 8),
                            _buildSortChip('featured', Icons.workspace_premium, context.t('Featured')),
                          ],
                        ),
                      ),
                      AdInlineBanner(adContext: 'explore'),
                    ],
                  ),
                ),
                if (provider.isLoading)
                  const SliverFillRemaining(
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (displayPlaces.isEmpty)
                  SliverFillRemaining(
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.map_outlined,
                              size: 48,
                              color: Colors.grey.shade300),
                          const SizedBox(height: 8),
                          Text(
                            _activeFilter?.onlyFeatured == true ? context.t('No featured places') : context.t('No places found'),
                            style: TextStyle(
                                color: Colors.grey.shade500,
                                fontSize: AppTheme.textBase),
                          ),
                          const SizedBox(height: 4),
                          if (_activeFilter?.onlyFeatured != true)
                            Text(
                              context.t('Try zooming in or moving the map'),
                              style: TextStyle(
                                  color: Colors.grey.shade400,
                                  fontSize: AppTheme.textSm),
                            ),
                        ],
                      ),
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.only(left: 12, right: 12, top: 4, bottom: 8),
                    sliver: SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          final place = displayPlaces[index];
                          return _buildPlaceItem(place);
                        },
                        childCount: displayPlaces.length,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildPlaceItem(PlaceModel place) {
    final isSelected = _selectedPlace?.id.toString() == place.id.toString();
    final markerColor = _getCategoryColor(place.category);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: isSelected
            ? AppTheme.primaryColor.withOpacity(0.05)
            : Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _onPlaceTap(place),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected
                    ? AppTheme.primaryColor.withOpacity(0.3)
                    : Colors.grey.shade200,
                width: isSelected ? 1.5 : 1,
              ),
            ),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: SizedBox(
                    width: 60,
                    height: 60,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        place.images.isNotEmpty
                            ? CachedNetworkImage(
                                imageUrl: place.images.first,
                                fit: BoxFit.cover,
                                placeholder: (_, __) =>
                                    _placePlaceholder(place),
                                errorWidget: (_, __, ___) =>
                                    _placePlaceholder(place),
                              )
                            : _placePlaceholder(place),
                        if (place.isFeatured)
                          Positioned(
                            top: 0,
                            left: 0,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 5, vertical: 2),
                              decoration: const BoxDecoration(
                                color: AppTheme.secondaryColor,
                                borderRadius: BorderRadius.only(
                                    bottomRight: Radius.circular(10)),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.star,
                                      size: 10, color: Colors.white),
                                  SizedBox(width: 2),
                                  Text(
                                    'FEATURED',
                                    style: TextStyle(
                                      fontSize: 8,
                                      fontWeight: FontWeight.w700,
                                      color: Colors.white,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              place.name,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, fontSize: AppTheme.textBase),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (place.isVerified)
                            const Padding(
                              padding: EdgeInsets.only(left: 4),
                              child: Icon(Icons.verified,
                                  size: 14,
                                  color: AppTheme.primaryColor),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          if (place.category != null) ...[
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: markerColor.withOpacity(0.12),
                                borderRadius: BorderRadius.circular(5),
                              ),
                              child: Text(
                                place.category!,
                                style: TextStyle(
                                    fontSize: 10,
                                    color: markerColor,
                                    fontWeight: FontWeight.w600),
                              ),
                            ),
                            const SizedBox(width: 6),
                          ],
                          if (place.district != null &&
                              place.district!.isNotEmpty)
                            Flexible(
                              child: Text(
                                place.district!,
                                style: TextStyle(
                                    fontSize: AppTheme.textSm,
                                    color: Colors.grey.shade600),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          if (place.averageRating != null) ...[
                            const Icon(Icons.star,
                                size: 14, color: AppTheme.secondaryColor),
                            const SizedBox(width: 3),
                            Text(
                              place.averageRating!.toStringAsFixed(1),
                              style: const TextStyle(
                                  fontSize: AppTheme.textSm, fontWeight: FontWeight.w600),
                            ),
                            if (place.totalReviews > 0) ...[
                              const SizedBox(width: 4),
                              Text(
                                '(${place.totalReviews})',
                                style: TextStyle(
                                    fontSize: AppTheme.textSm, color: Colors.grey.shade500),
                              ),
                            ],
                            const SizedBox(width: 8),
                          ],
                          if (_distanceFromUser(place) != null)
                            Text(
                              '${_distanceFromUser(place)!.toStringAsFixed(1)} km',
                              style: TextStyle(
                                  fontSize: AppTheme.textSm, color: Colors.grey.shade500),
                            ),
                          if (place.source == 'osm')
                            Container(
                              margin: const EdgeInsets.only(left: 6),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 4, vertical: 1),
                              decoration: BoxDecoration(
                                color: Colors.blue.shade50,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                'OSM',
                                style: TextStyle(
                                    fontSize: 9,
                                    color: Colors.blue.shade600,
                                    fontWeight: FontWeight.w600),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                if (place.source == 'osm')
                  _buildOsmSaveButton(place),
                Icon(Icons.chevron_right,
                    size: 18, color: Colors.grey.shade400),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _placePlaceholder(PlaceModel place) {
    final color = _getCategoryColor(place.category);
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(_getCategoryIcon(place.category), color: color, size: 24),
    );
  }

  Widget _buildSelectedPlaceCard() {
    final place = _selectedPlace!;
    final markerColor = _getCategoryColor(place.category);
    return Material(
      elevation: 6,
      borderRadius: BorderRadius.circular(16),
      shadowColor: Colors.black26,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => _navigateToDetails(place),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: SizedBox(
                      width: 50,
                      height: 50,
                      child: place.images.isNotEmpty
                          ? CachedNetworkImage(
                              imageUrl: place.images.first,
                              width: 50,
                              height: 50,
                              fit: BoxFit.cover,
                              placeholder: (_, __) =>
                                  _placePlaceholderSmall(place),
                              errorWidget: (_, __, ___) =>
                                  _placePlaceholderSmall(place),
                            )
                          : _placePlaceholderSmall(place),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                place.name,
                                style: const TextStyle(
                                    fontWeight: FontWeight.w600, fontSize: AppTheme.textBase),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (place.isVerified)
                              const Padding(
                                padding: EdgeInsets.only(left: 4),
                                child: Icon(Icons.verified,
                                    size: 14, color: AppTheme.primaryColor),
                              ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          runSpacing: 2,
                          children: [
                            if (place.averageRating != null) ...[
                              const Icon(Icons.star, size: 13, color: AppTheme.secondaryColor),
                              Text(place.averageRating!.toStringAsFixed(1), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                              if (place.totalReviews > 0)
                                Text('(${place.totalReviews})', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                            ],
                            if (place.category != null)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                decoration: BoxDecoration(
                                  color: markerColor.withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(place.category!, style: TextStyle(fontSize: 10, color: markerColor, fontWeight: FontWeight.w600)),
                              ),
                            if (_distanceFromUser(place) != null)
                              Text('${_distanceFromUser(place)!.toStringAsFixed(1)} km', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right,
                      size: 20, color: Colors.grey.shade400),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.directions, size: 16),
                    label: Text(_isLoadingRoute ? context.t('Loading...') : context.t('Directions'), style: const TextStyle(fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF4285F4),
                      side: const BorderSide(color: Color(0xFF4285F4)),
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    onPressed: _isLoadingRoute ? null : () => _getDirections(place),
                  ),
                ),
                if (_routes.isNotEmpty) ...[
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(Icons.close, size: 18),
                    constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                    padding: EdgeInsets.zero,
                    onPressed: _clearRoute,
                    tooltip: context.t('Clear route'),
                  ),
                ],
              ],
            ),
            if (_routes.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: SizedBox(
                  height: 30,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: _routes.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 6),
                    itemBuilder: (context, i) {
                      final r = _routes[i];
                      final dist = (r['distance'] as double).toStringAsFixed(1);
                      final dur = (r['duration'] as double).toStringAsFixed(0);
                      final isFirst = i == 0;
                      return GestureDetector(
                        onTap: () {
                          if (i != 0) {
                            final routes = List<Map<String, dynamic>>.from(_routes);
                            final item = routes.removeAt(i);
                            routes.insert(0, item);
                            setState(() => _routes = routes);
                          }
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: isFirst ? const Color(0xFF4285F4) : Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(14),
                            border: isFirst ? null : Border.all(color: Colors.grey.shade300),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                '${context.t('Route')} ${i + 1}',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: isFirst ? Colors.white : AppTheme.textPrimary,
                                ),
                              ),
                              const SizedBox(width: 4),
                              Text(
                                'Â· $dur min ($dist km)',
                                style: TextStyle(
                                  fontSize: 10,
                                  color: isFirst ? Colors.white70 : AppTheme.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildSheetSelectedPreview(PlaceModel place, VoidCallback onTap) {
    final markerColor = _getCategoryColor(place.category);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(color: markerColor, width: 4),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 48, height: 48,
                    decoration: BoxDecoration(
                      color: markerColor.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(_getCategoryIcon(place.category), color: markerColor, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(place.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis),
                            ),
                            if (place.isVerified) const Icon(Icons.verified, size: 14, color: AppTheme.primaryColor),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          runSpacing: 2,
                          children: [
                            if (place.averageRating != null) ...[
                              const Icon(Icons.star, size: 13, color: AppTheme.secondaryColor),
                              Text(place.averageRating!.toStringAsFixed(1), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                              if (place.totalReviews > 0)
                                Text('(${place.totalReviews})', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                            ],
                            if (place.category != null)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                decoration: BoxDecoration(
                                  color: markerColor.withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(place.category!, style: TextStyle(fontSize: 10, color: markerColor, fontWeight: FontWeight.w600)),
                              ),
                            if (_distanceFromUser(place) != null)
                              Text('${_distanceFromUser(place)!.toStringAsFixed(1)} km', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.directions, size: 16),
                      label: Text(_isLoadingRoute ? context.t('Loading...') : context.t('Directions'), style: const TextStyle(fontSize: 12)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF4285F4),
                        side: const BorderSide(color: Color(0xFF4285F4)),
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      onPressed: _isLoadingRoute ? null : () => _getDirections(place),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.rate_review_outlined, size: 16),
                      label: Text(context.t('Details'), style: const TextStyle(fontSize: 12)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppTheme.primaryColor,
                        side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.4)),
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      onPressed: onTap,
                    ),
                  ),
                  if (_routes.isNotEmpty) ...[
                    const SizedBox(width: 8),
                    IconButton(
                      icon: const Icon(Icons.close, size: 18),
                      constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                      padding: EdgeInsets.zero,
                      onPressed: _clearRoute,
                      tooltip: context.t('Clear route'),
                    ),
                  ],
                ],
              ),
              if (_routes.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: SizedBox(
                    height: 30,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _routes.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 6),
                      itemBuilder: (context, i) {
                        final r = _routes[i];
                        final dist = (r['distance'] as double).toStringAsFixed(1);
                        final dur = (r['duration'] as double).toStringAsFixed(0);
                        final isFirst = i == 0;
                        return GestureDetector(
                          onTap: () {
                            if (i != 0) {
                              final routes = List<Map<String, dynamic>>.from(_routes);
                              final item = routes.removeAt(i);
                              routes.insert(0, item);
                              setState(() => _routes = routes);
                            }
                          },
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: isFirst ? const Color(0xFF4285F4) : Colors.grey.shade100,
                              borderRadius: BorderRadius.circular(14),
                              border: isFirst ? null : Border.all(color: Colors.grey.shade300),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  '${context.t('Route')} ${i + 1}',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: isFirst ? Colors.white : AppTheme.textPrimary,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  'Â· $dur min ($dist km)',
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: isFirst ? Colors.white70 : AppTheme.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDestinationPreview() {
    return Material(
      color: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        decoration: const BoxDecoration(
          border: Border(left: BorderSide(color: Color(0xFFE91E63), width: 4)),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: const Color(0xFFE91E63).withOpacity(0.15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.flag, color: Color(0xFFE91E63), size: 24),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_destinationName ?? context.t('Destination'),
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 16),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 2),
                  Text(context.t('Tap Get Directions for route'),
                      style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            if (_routes.isEmpty)
              SizedBox(
                height: 36,
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.directions, size: 16),
                  label: Text(_isLoadingRoute ? '...' : context.t('Go')),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE91E63),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20)),
                  ),
                  onPressed: _isLoadingRoute ? null : _fetchDestinationRoute,
                ),
              )
            else
              SizedBox(
                height: 36,
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.clear, size: 16),
                  label: Text(context.t('Clear')),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.textSecondary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20)),
                  ),
                  onPressed: _clearRoute,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _placePlaceholderSmall(PlaceModel place) {
    final color = _getCategoryColor(place.category);
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(_getCategoryIcon(place.category), color: color, size: 22),
    );
  }

  /// Nepal-wide dataset filtered to the current viewport (with margin) so
  /// marker/label work stays proportional to what's on screen. When no
  /// Nepal data is available yet (cold start), falls back to the nearby list.
  List<PlaceModel> _markerPlacesForViewport(PlaceProvider provider) {
    final source = provider.nepalPlaces.isNotEmpty
        ? provider.nepalPlaces
        : provider.places;
    var filtered = _applyPlaceFilters(source);
    if (filtered.isEmpty) return filtered;

    final vp = _getViewportBounds();
    const margin = 0.15;
    return filtered
        .where((p) =>
            p.latitude >= vp.minLat - margin &&
            p.latitude <= vp.maxLat + margin &&
            p.longitude >= vp.minLng - margin &&
            p.longitude <= vp.maxLng + margin)
        .toList();
  }

  /// Grid clustering: group pins by map tile at the current zoom, show one
  /// count badge per cell. Tap zooms in, re-clustering at a finer grain.
  List<Marker> _buildClusteredMarkers(List<PlaceModel> places) {
    // Pre-GPS (whole Nepal in view at any zoom) the cells stay coarse so
    // badges, not pin soup, are shown until the map centers on the user.
    final cellDeg = 360.0 / math.pow(2, math.min(_currentZoom, 9.0));
    final groups = <int, List<PlaceModel>>{};
    for (final p in places) {
      final cx = ((p.longitude + 180.0) / cellDeg).floor();
      final cy = ((p.latitude + 90.0) / cellDeg).floor();
      final key = cx * 100003 + cy;
      groups.putIfAbsent(key, () => []).add(p);
    }

    final markers = <Marker>[];
    groups.forEach((_, group) {
      if (group.length == 1) {
        final p = group.first;
        markers.add(Marker(
          point: LatLng(p.latitude, p.longitude),
          width: 32,
          height: 32,
          alignment: Alignment.center,
          child: _buildPinChild(p, 32.0, false),
        ));
        return;
      }
      // Anchor the badge on the cell's best (first/featured) place so the
      // cluster always sits on real coordinates.
      final anchor = group.first;
      markers.add(Marker(
        point: LatLng(anchor.latitude, anchor.longitude),
        width: 44,
        height: 44,
        alignment: Alignment.center,
        child: GestureDetector(
          onTap: () => _zoomIntoCluster(anchor.latitude, anchor.longitude),
          child: _buildClusterBadge(group.length),
        ),
      ));
    });
    return markers;
  }

  Widget _buildClusterBadge(int count) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: AppTheme.primaryColor,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 4)],
      ),
      alignment: Alignment.center,
      child: Text(
        '$count',
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.bold,
          fontSize: 13,
        ),
      ),
    );
  }

  void _zoomIntoCluster(double lat, double lng) {
    try {
      _mapController.move(
        LatLng(lat, lng),
        math.min(_currentZoom + 3, 15.0),
      );
    } catch (e) {
      debugPrint('Cluster zoom failed: $e');
    }
  }

  /// Plain circular category pin (no label) — shared by the cluster and the
  /// high-zoom paths.
  Widget _buildPinChild(PlaceModel place, double markerSize, bool isSelected) {
    final markerColor = _getCategoryColor(place.category);
    return GestureDetector(
      onTap: () => _onPlaceTap(place),
      onDoubleTap: () => _navigateToDetails(place),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            width: markerSize, height: markerSize,
            decoration: BoxDecoration(
              color: markerColor, shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: isSelected ? 3 : 1.5),
              boxShadow: [
                BoxShadow(
                  color: markerColor.withOpacity(isSelected ? 0.6 : 0.25),
                  blurRadius: isSelected ? 10 : 3,
                  spreadRadius: isSelected ? 3 : 0.5,
                ),
              ],
            ),
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(_getCategoryIcon(place.category), color: Colors.white, size: isSelected ? 22 : 16),
                if (isSelected)
                  Positioned(
                    top: 0, right: 0,
                    child: Container(
                      width: 14, height: 14,
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: const Icon(Icons.check_circle, size: 9, color: AppTheme.successColor),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<Marker> _buildMarkers(List<PlaceModel> places) {
    // Grid clustering: group pins by map tile at the current zoom, show one
    // count badge per cell. Clusters sit ON a real place (the first/featured
    // pin of the cell) — never on the group average, which floats in empty
    // terrain between towns. Tap zooms in, re-clustering at a finer grain.
    final forceCluster = _lat == null || _lng == null;
    final tooManyPins = places.length > _clusterMaxPins;
    final denseLowZoom =
        places.length > _clusterMinCount && _currentZoom < _clusterMaxZoom;
    if (forceCluster || tooManyPins || denseLowZoom) {
      return _buildClusteredMarkers(places);
    }

    final markers = <Marker>[];

    if (places.isEmpty) return markers;

    final showAddress = _currentZoom >= 16;
    final highZoom = _currentZoom >= 16;
    final midZoom = _currentZoom >= 14;

    // Phase 0: Check if label assignment needs recomputation
    final camera = _mapController.camera;
    final viewport = MediaQuery.of(context).size;
    final stateKey = '${_currentZoom}|${camera.center.latitude}|${camera.center.longitude}|${camera.rotation}|${_selectedPlace?.id}';
    final placesHash = Object.hashAll(places.map((p) => p.id));

    if (stateKey != _lastLabelStateKey || placesHash != _lastPlacesHash) {
      _lastLabelAssignments = _computeLabelAssignments(places, showAddress, highZoom, midZoom, camera, viewport);
      _lastLabelStateKey = stateKey;
      _lastPlacesHash = placesHash;
    }

    // Phase 1: Build marker widgets
    for (int i = 0; i < places.length; i++) {
      final place = places[i];
      final assignment = _lastLabelAssignments[i];
      final isSelected = _selectedPlace?.id.toString() == place.id.toString();
      final markerSize = isSelected ? 44.0 : 32.0;

      final markerChild = Stack(
        clipBehavior: Clip.none,
        children: [
          _buildPinChild(place, markerSize, isSelected),
          if (assignment.side != null)
            _buildLabel(place, assignment.side!, assignment.labelWidth, markerSize, showAddress),
        ],
      );

      markers.add(Marker(
        point: LatLng(place.latitude, place.longitude),
        width: markerSize,
        height: markerSize,
        alignment: Alignment.center,
        child: markerChild,
      ));
    }

    return markers;
  }

  Widget _buildLabel(PlaceModel place, _LabelSide side, double labelWidth, double markerSize, bool showAddress) {
    final labelH = showAddress && place.address != null ? 42.0 : 24.0;
    final m2 = markerSize / 2;

    double left, right, top, bottom;
    switch (side) {
      case _LabelSide.right:
        left = markerSize + 4;
        top = m2 - labelH / 2;
        right = double.infinity;
        bottom = double.infinity;
      case _LabelSide.left:
        right = markerSize + 4;
        top = m2 - labelH / 2;
        left = double.infinity;
        bottom = double.infinity;
      case _LabelSide.top:
        left = m2 - labelWidth / 2;
        bottom = markerSize + 4;
        top = double.infinity;
        right = double.infinity;
      case _LabelSide.bottom:
        left = m2 - labelWidth / 2;
        top = markerSize + 4;
        right = double.infinity;
        bottom = double.infinity;
    }

    return Positioned(
      left: left.isFinite ? left : null,
      right: right.isFinite ? right : null,
      top: top.isFinite ? top : null,
      bottom: bottom.isFinite ? bottom : null,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.92),
          borderRadius: BorderRadius.circular(6),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 3, offset: const Offset(0, 1)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              place.name,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.black87),
              maxLines: 1, overflow: TextOverflow.ellipsis,
            ),
            if (showAddress && place.address != null)
              Text(
                place.address!,
                style: const TextStyle(fontSize: 9, color: Colors.black54),
                maxLines: 1, overflow: TextOverflow.ellipsis,
              ),
          ],
        ),
      ),
    );
  }

  double _measureLabelWidth(PlaceModel place) {
    final key = '${place.id}|${place.name}';
    return _labelWidthCache.putIfAbsent(key, () {
      final tp = TextPainter(
        text: TextSpan(text: place.name, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
        maxLines: 1, textDirection: TextDirection.ltr,
      )..layout(maxWidth: 140);
      return tp.width + 12;
    });
  }

  List<_LabelAssignment> _computeLabelAssignments(
    List<PlaceModel> places, bool showAddress, bool highZoom, bool midZoom,
    MapCamera camera, Size viewport,
  ) {
    final infos = <_LabelInfo>[];
    for (final place in places) {
      final isSelected = _selectedPlace?.id.toString() == place.id.toString();
      final isFeatured = place.isFeatured;
      final showName = highZoom || (midZoom && (isFeatured || isSelected));
      final markerSize = isSelected ? 44.0 : 32.0;

      if (!showName) {
        infos.add(_LabelInfo(placeId: place.id, showLabel: false, markerSize: markerSize));
        continue;
      }

      final pt = camera.latLngToScreenPoint(LatLng(place.latitude, place.longitude));
      final labelW = _measureLabelWidth(place);
      infos.add(_LabelInfo(placeId: place.id, showLabel: true, markerSize: markerSize, screenPt: Offset(pt.x, pt.y), labelWidth: labelW));
    }

    // Sort: selected first, featured, then regular
    final sortedInfos = List<_LabelInfo>.from(infos);
    sortedInfos.sort((a, b) {
      final aSelected = _selectedPlace?.id.toString() == a.placeId.toString();
      final bSelected = _selectedPlace?.id.toString() == b.placeId.toString();
      if (aSelected != bSelected) return aSelected ? -1 : 1;
      final aF = places.firstWhere((p) => p.id.toString() == a.placeId.toString()).isFeatured;
      final bF = places.firstWhere((p) => p.id.toString() == b.placeId.toString()).isFeatured;
      if (aF != bF) return aF ? -1 : 1;
      return 0;
    });

    final placedRects = <Rect>[];
    final allPts = infos.where((i) => i.showLabel).map((i) => i.screenPt).toList();

    for (final info in sortedInfos) {
      if (!info.showLabel) {
        info.side = null;
        continue;
      }

      final pt = info.screenPt;
      final r = info.markerSize / 2;
      final lw = info.labelWidth;
      final lh = (showAddress && places.firstWhere((p) => p.id.toString() == info.placeId.toString()).address != null) ? 42.0 : 24.0;
      info.labelHeight = lh;

      // 4 candidate positions in screen coordinates
      final candidates = <_LabelSide, Rect>{
        _LabelSide.right: Rect.fromLTWH(pt.dx + r + 4, pt.dy - lh / 2, lw, lh),
        _LabelSide.left: Rect.fromLTWH(pt.dx - r - 4 - lw, pt.dy - lh / 2, lw, lh),
        _LabelSide.top: Rect.fromLTWH(pt.dx - lw / 2, pt.dy - r - 4 - lh, lw, lh),
        _LabelSide.bottom: Rect.fromLTWH(pt.dx - lw / 2, pt.dy + r + 4, lw, lh),
      };

      // Stability: keep previous side if still valid
      final prev = _lastLabelAssignments.where((a) => a.placeId == info.placeId).firstOrNull;
      if (prev?.side != null) {
        final prevRect = candidates[prev!.side!]!;
        final clampedPrev = _clampToViewport(prevRect, viewport);
        bool stable = true;
        if (clampedPrev.left < 0 || clampedPrev.right > viewport.width ||
            clampedPrev.top < 0 || clampedPrev.bottom > viewport.height) stable = false;
        else {
          for (final placed in placedRects) {
            if (clampedPrev.overlaps(placed.inflate(6))) { stable = false; break; }
          }
          if (stable) {
            for (final otherPt in allPts) {
              if (otherPt == pt) continue;
              final markerRect = Rect.fromCenter(center: otherPt, width: 44, height: 44);
              if (clampedPrev.overlaps(markerRect.inflate(4))) { stable = false; break; }
            }
          }
        }
        if (stable) {
          info.side = prev.side;
          placedRects.add(clampedPrev);
          continue;
        }
      }

      // Score all 4 positions
      _LabelSide? bestSide;
      double bestScore = double.infinity;
      Rect? bestClamped;

      for (final entry in candidates.entries) {
        final side = entry.key;
        final clamped = _clampToViewport(entry.value, viewport);
        double score = _sideRank(side);

        // Label-label overlap (6px gap)
        for (final placed in placedRects) {
          if (clamped.overlaps(placed.inflate(6))) score += 100;
        }

        // Label-marker overlap (4px gap)
        for (final otherPt in allPts) {
          if (otherPt == pt) continue;
          final markerRect = Rect.fromCenter(center: otherPt, width: 44, height: 44);
          if (clamped.overlaps(markerRect.inflate(4))) score += 150;
        }

        // Offscreen penalty
        if (clamped.left < 0 || clamped.right > viewport.width ||
            clamped.top < 0 || clamped.bottom > viewport.height) {
          score += 500;
        }

        if (score < bestScore) {
          bestScore = score;
          bestSide = side;
          bestClamped = clamped;
        }
      }

      if (bestSide != null) {
        info.side = bestSide;
        placedRects.add(bestClamped!);
      } else {
        info.side = null;
      }
    }

    // Map assignments back to original order
    final assignmentMap = <Object, _LabelAssignment>{};
    for (final info in infos) {
      assignmentMap[info.placeId] = _LabelAssignment(
        placeId: info.placeId,
        side: info.side,
        labelWidth: info.labelWidth,
      );
    }
    return places.map((p) => assignmentMap[p.id] ?? _LabelAssignment(placeId: p.id)).toList();
  }

  Rect _clampToViewport(Rect r, Size vp) {
    final w = r.width > vp.width ? vp.width : r.width;
    final h = r.height > vp.height ? vp.height : r.height;
    return Rect.fromLTWH(
      r.left.clamp(0, (vp.width - w).toDouble()),
      r.top.clamp(0, (vp.height - h).toDouble()),
      w, h,
    );
  }

  double _sideRank(_LabelSide side) {
    switch (side) {
      case _LabelSide.right: return 0;
      case _LabelSide.left: return 1;
      case _LabelSide.top: return 2;
      case _LabelSide.bottom: return 3;
    }
  }

  IconData _getCategoryIcon(String? category) {
    switch (category?.toLowerCase()) {
      case 'hotel':
      case 'accommodation':
      case 'hotels':
        return Icons.hotel;
      case 'restaurant':
      case 'food':
      case 'restaurants':
        return Icons.restaurant;
      case 'cafe':
        return Icons.local_cafe;
      case 'emergency':
        return Icons.warning;
      case 'hospital':
      case 'clinic':
        return Icons.local_hospital;
      case 'pharmacy':
        return Icons.medication;
      case 'transport':
      case 'bus':
      case 'airport':
        return Icons.directions_bus;
      case 'attraction':
      case 'landmark':
      case 'sightseeing':
      case 'attractions':
        return Icons.photo_camera;
      case 'activity':
      case 'adventure':
      case 'activities':
        return Icons.directions_run;
      case 'atm':
      case 'atms':
      case 'bank':
        return Icons.account_balance;
      case 'fuel':
        return Icons.local_gas_station;
      case 'shopping':
        return Icons.shopping_bag;
      case 'parking':
        return Icons.local_parking;
      case 'education':
      case 'school':
      case 'college':
        return Icons.school;
      case 'entertainment':
        return Icons.movie;
      case 'nature':
        return Icons.forest;
      case 'services':
        return Icons.build;
      case 'recreation':
        return Icons.sports_tennis;
      default:
        return Icons.place;
    }
  }

  Color _getCategoryColor(String? category) {
    switch (category?.toLowerCase()) {
      case 'hotel':
      case 'accommodation':
        return const Color(0xFF4A90D9);
      case 'restaurant':
      case 'food':
      case 'cafe':
        return const Color(0xFFE74C3C);
      case 'hospital':
      case 'clinic':
      case 'pharmacy':
        return const Color(0xFF27AE60);
      case 'transport':
      case 'bus_station':
        return const Color(0xFFF39C12);
      case 'attraction':
      case 'museum':
      case 'landmark':
        return const Color(0xFF9B59B6);
      case 'viewpoint':
      case 'nature':
        return const Color(0xFF2ECC71);
      case 'shopping':
      case 'market':
        return const Color(0xFFE67E22);
      case 'atm':
      case 'bank':
        return const Color(0xFF3498DB);
      default:
        return AppTheme.primaryColor;
    }
  }

  void _fetchWeatherForViewport() {
    try {
      final bounds = _mapController.camera.visibleBounds;
      _fetchWeatherGrid(
        minLat: bounds.south,
        maxLat: bounds.north,
        minLng: bounds.west,
        maxLng: bounds.east,
      );
    } catch (_) {}
  }

  Future<void> _fetchWeatherGrid({
    double? minLat, double? maxLat, double? minLng, double? maxLng,
  }) async {
    try {
      final params = <String, dynamic>{};
      if (minLat != null) {
        params['min_lat'] = minLat;
        params['max_lat'] = maxLat;
        params['min_lng'] = minLng;
        params['max_lng'] = maxLng;
      }
      final response = await ApiClient.instance.dio.get('/weather/grid', queryParameters: params);
      final data = response.data['data'] as List? ?? [];
      if (mounted) {
        setState(() {
          _weatherGrid = data.map((j) => _WeatherGridPoint.fromJson(j)).toList();
        });
      }
    } catch (e) {
      print('Weather grid fetch failed: $e');
    }
  }

  List<Polygon> _buildWeatherPolygons() {
    const step = 0.05;
    const halfStep = step / 2;
    return _weatherGrid.map((pt) {
      return Polygon(
        points: [
          LatLng(pt.lat - halfStep, pt.lng - halfStep),
          LatLng(pt.lat - halfStep, pt.lng + halfStep),
          LatLng(pt.lat + halfStep, pt.lng + halfStep),
          LatLng(pt.lat + halfStep, pt.lng - halfStep),
        ],
        color: _weatherCodeToColor(pt.code).withOpacity(0.4),
        borderStrokeWidth: 0,
        isFilled: true,
      );
    }).toList();
  }

  Color _weatherCodeToColor(int code) {
    if (code == 0) return Colors.amber;
    if (code >= 1 && code <= 3) return Colors.grey;
    if (code >= 45 && code <= 48) return const Color(0xFFD3D3D3);
    if (code >= 51 && code <= 55) return const Color(0xFF87CEEB);
    if (code >= 61 && code <= 65) return const Color(0xFF4169E1);
    if (code >= 71 && code <= 77) return Colors.white;
    if (code >= 80 && code <= 82) return Colors.blue;
    if (code >= 95 && code <= 99) return Colors.purple;
    return Colors.transparent;
  }
}
enum _LabelSide { right, left, top, bottom }

class _LabelAssignment {
  final dynamic placeId;
  _LabelSide? side;
  final double labelWidth;

  _LabelAssignment({required this.placeId, this.side, this.labelWidth = 0});
}

class _LabelInfo {
  final dynamic placeId;
  bool showLabel;
  _LabelSide? side;
  double markerSize;
  Offset screenPt;
  double labelWidth;
  double labelHeight = 0;

  _LabelInfo({
    required this.placeId,
    required this.showLabel,
    required this.markerSize,
    this.screenPt = Offset.zero,
    this.labelWidth = 0,
  });
}

class _ViewportBounds {
  final double minLat, maxLat, minLng, maxLng;
  _ViewportBounds({
    required this.minLat,
    required this.maxLat,
    required this.minLng,
    required this.maxLng,
  });
}

class _WeatherGridPoint {
  final double lat;
  final double lng;
  final int code;
  final double? temp;
  final double? precip;

  _WeatherGridPoint({
    required this.lat,
    required this.lng,
    required this.code,
    this.temp,
    this.precip,
  });

  factory _WeatherGridPoint.fromJson(Map<String, dynamic> json) {
    return _WeatherGridPoint(
      lat: (json['lat'] is num ? (json['lat'] as num).toDouble() : double.tryParse(json['lat']?.toString() ?? '')) ?? 0.0,
      lng: (json['lng'] is num ? (json['lng'] as num).toDouble() : double.tryParse(json['lng']?.toString() ?? '')) ?? 0.0,
      code: json['code'] is int ? json['code'] as int : int.tryParse(json['code']?.toString() ?? '') ?? 0,
      temp: (json['temp'] is num ? (json['temp'] as num).toDouble() : double.tryParse(json['temp']?.toString() ?? '')),
      precip: (json['precip'] is num ? (json['precip'] as num).toDouble() : double.tryParse(json['precip']?.toString() ?? '')),
    );
  }
}
