import 'dart:async';
import "../../core/services/localization_service.dart";
import 'dart:math' as math;
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter_compass/flutter_compass.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../core/services/nearby_map_cache.dart';
import '../../core/services/offline_db_service.dart';
import '../../core/services/offline_tile_provider.dart';
import '../../core/services/app_settings_service.dart';
import '../../core/services/proximity_alert_service.dart';
import '../../core/services/nepal_boundary_service.dart';
import '../../core/models/place.dart';
import '../../core/models/route_model.dart';
import '../../core/api/api_client.dart';
import '../../providers/place_provider.dart';
import '../../providers/map_view_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/sos_provider.dart';
import '../auth/login_screen.dart';
import '../emergency/widgets/sos_marker_painter.dart';
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
///
/// Open sequence (cached experience):
/// ```text
/// restore cached camera + places  ->  render map immediately
///           ->  fresh GPS in background  ->  refresh places in background
/// ```
/// The map never waits on GPS or the network to paint something useful.
class NearbyMapScreen extends StatefulWidget {
  const NearbyMapScreen({
    super.key,
    this.destinationLat,
    this.destinationLng,
    this.destinationName,
    this.focusLat,
    this.focusLng,
    this.focusLabel,
  });

  final double? destinationLat;
  final double? destinationLng;
  final String? destinationName;
  // When set, the map opens centered here (e.g. an SOS location from the
  // emergency inbox) instead of the user's live GPS position.
  final double? focusLat;
  final double? focusLng;
  final String? focusLabel;

  @override
  State<NearbyMapScreen> createState() => _NearbyMapScreenState();
}

class _NearbyMapScreenState extends State<NearbyMapScreen>
    with TickerProviderStateMixin {
  final LocationService _locationService = LocationService();
  final MapController _mapController = MapController();
  final DraggableScrollableController _sheetController =
      DraggableScrollableController();
  final TextEditingController _searchController = TextEditingController();
  final OfflineDbService _offlineDb = OfflineDbService.instance;
  final OfflineTileProvider _offlineTiles = OfflineTileProvider();
  final OfflineTileProvider _satelliteTiles =
      OfflineTileProvider(tileType: 'satellite_v2');

  double? _lat;
  double? _lng;
  double _currentZoom = AppConstants.defaultMapZoom;
  double _previousZoom = AppConstants.defaultMapZoom;
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

  // ── Instant-restore / cached-open state ──────────────────────────────
  // The map widget is only built once the previous session's camera has been
  // restored (a single SharedPreferences read, ~1 frame) so the first painted
  // frame is already the map the user left behind — never the blank default.
  bool _initialCameraReady = false;

  // Latest location-resolution result. Drives the "Waiting for your current
  // location / Try Again" card so a denied permission is reported even while
  // the cached map keeps rendering underneath it.
  DeviceLocationStatus? _locationStatus;

  // ── Blue-dot smoothing ───────────────────────────────────────────────
  // _currentLocation is the raw target (latest GPS fix); the marker and the
  // follow-camera chase _displayLocation, which interpolates toward the
  // target every frame instead of teleporting on each fix.
  LatLng? _displayLocation;
  final ValueNotifier<LatLng?> _displayLocationNotifier = ValueNotifier(null);
  final ValueNotifier<double> _accuracyNotifier = ValueNotifier<double>(20);
  final ValueNotifier<double> _zoomNotifier =
      ValueNotifier<double>(AppConstants.defaultMapZoom);
  Ticker? _dotTicker;
  DateTime _lastFixAt = DateTime.fromMillisecondsSinceEpoch(0);

  // ── Follow / compass state machine ───────────────────────────────────
  // _isTracking      — camera follows the blue dot (GPS controls center)
  // _compassMode     — camera bearing follows the device heading
  // Both can be on at the same time: center and bearing are independent.
  bool _compassMode = false;
  bool _hasRecenteredWithButton = false;

  // Camera / location persistence (debounced)
  Timer? _cameraPersistTimer;
  Timer? _locationPersistTimer;
  bool _persistLocationQueued = false;

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
  double? _smoothedCompassHeading;

  double? get _heading => _useGpsHeading ? _gpsHeading : _compassHeading;

  static double _shortestArc(double from, double to) {
    var d = (to - from) % 360;
    if (d > 180) d -= 360;
    if (d < -180) d += 360;
    return d;
  }

  // Compass rotation (degrees, clockwise positive)
  final ValueNotifier<double> _rotationNotifier = ValueNotifier<double>(0);

  // Camera-state notifier: incremented on every map move/zoom so FlutterMap
  // rebuilds via ValueListenableBuilder without a full-widget setState.
  final ValueNotifier<int> _camVersion = ValueNotifier(0);

  // Heading notifier: updated on compass/GPS-heading change so only the
  // blue-dot rebuilds — not the entire FlutterMap or screen.
  final ValueNotifier<double?> _headingNotifier = ValueNotifier(null);

  // Search text notifier: drives the suffix-icon (clear button) via
  // ValueListenableBuilder instead of a full-widget setState on every keystroke.
  final ValueNotifier<String> _searchTextNotifier = ValueNotifier('');

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
  Timer? _sosRefreshTimer;

  // Label positioning state
  final Map<String, double> _labelWidthCache = {};
  String _lastLabelStateKey = '';
  int _lastPlacesHash = 0;
  List<_LabelAssignment> _lastLabelAssignments = [];

  // Screen-space marker clustering (cell ~48px = marker 32 + 16px buffer)
  static const double _markerGridCell = 48.0;

  // Smooth camera glide (last-known -> fresh GPS fix)
  late final AnimationController _cameraAnimController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 700),
  );
  VoidCallback? _cameraAnimListener;
  bool _isAnimating = false;

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
    ProximityAlertService.instance.onProximityAlert = _showProximityBanner;
    _pollSyncCount();
    _initCompass();
    _searchController.addListener(() {
      _searchTextNotifier.value = _searchController.text;
    });
    NepalBoundaryService.instance.onLoaded(_onBoundaryLoaded);
    NepalBoundaryService.instance.load();
    // Started immediately rather than post-frame: the first await inside is
    // the local camera restore, which has to finish BEFORE the first frame so
    // the map opens on the previous view instead of the default Nepal centre.
    unawaited(_initMap());
  }

  void _onBoundaryLoaded() {
    if (mounted) _camVersion.value++;
  }

  /// In-app banner when the user walks into an alert zone while
  /// navigating (the system notification fires in parallel).
  void _showProximityBanner(ProximityAlertItem item) {
    if (!mounted) return;
    HapticFeedback.heavyImpact();
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 6),
        backgroundColor: Colors.red.shade800,
        content: Text('${item.severityEmoji} ${item.title}'),
        action: SnackBarAction(
          label: context.tr('OK'),
          onPressed: () {},
        ),
      ));
  }

  void _initCompass() {
    final events = FlutterCompass.events;
    if (events == null) return;
    // Single shared compass subscription — the blue-dot heading, the FAB state
    // and compass-mode bearing all read from this one listener. (A second
    // listener used to be created when compass-follow started, which meant
    // duplicated sensor callbacks and two competing rotate() callers.)
    _compassSub = events.listen((event) {
      if (!mounted) return;
      final h = event.heading;
      if (h == null) return;
      // Throttle ~20Hz: the compass fires at 200Hz+; rebuilding the map at
      // that rate is wasteful.
      final now = DateTime.now();
      if (now.difference(_lastCompassUi).inMilliseconds < 50) return;
      _lastCompassUi = now;
      final h360 = (h % 360 + 360) % 360;
      _compassHeading = h360;
      // When GPS bearing is stale (standing still), let the compass drive
      // the light so it follows the phone's rotation.
      if (now.difference(_lastGpsHeadingAt).inSeconds > 4) {
        _useGpsHeading = false;
      }
      // ValueNotifier swallows no-op writes, so jitter costs nothing here.
      _headingNotifier.value = _heading;
      // Heading only ever drives the camera BEARING, and only while compass
      // mode is on. Camera center is exclusively GPS-driven.
      if (_compassMode) _applyCompassRotation(h360);
    });
  }

  @override
  void dispose() {
    _persistNow();
    NepalBoundaryService.instance.removeOnLoaded(_onBoundaryLoaded);
    ProximityAlertService.instance.stopNavigationMonitoring();
    _debounceTimer?.cancel();
    _autoDownloadTimer?.cancel();
    _cameraPersistTimer?.cancel();
    _locationPersistTimer?.cancel();
    _positionStream?.cancel();
    _compassSub?.cancel();
    _dotTicker?.stop();
    _dotTicker?.dispose();
    _syncStreamController?.close();
    _weatherDebounceTimer?.cancel();
    _sosRefreshTimer?.cancel();
    _rotationNotifier.dispose();
    _camVersion.dispose();
    _headingNotifier.dispose();
    _displayLocationNotifier.dispose();
    _accuracyNotifier.dispose();
    _zoomNotifier.dispose();
    _searchTextNotifier.dispose();
    _sheetController.dispose();
    _searchController.dispose();
    _cameraAnimController.dispose();
    super.dispose();
  }

  Future<void> _initMap() async {
    final provider = context.read<PlaceProvider>();

    // ── 0) Restore the previous session BEFORE anything can block ──────
    // One local read (no GPS, no network). This is what stops every reopen
    // from feeling like the map is starting from zero.
    await _restoreCachedCamera();
    if (!mounted) return;

    // ── 1) Datasets load in parallel — never gate the map on them ──────
    // setNepalCachedPlaces() restores markers from SQLite instantly;
    // fetchNepalPlaces() only hits the network once the payload is stale
    // (the server caches /places/all for 10 minutes too).
    unawaited(provider.fetchCategories());
    unawaited(provider.setNepalCachedPlaces());
    unawaited(provider.fetchNepalPlaces());

    if (widget.focusLat != null && widget.focusLng != null) {
      // Focus mode: open centered on a given point (e.g. an SOS location from
      // the emergency inbox) instead of the user's live GPS position.
      setState(() {
        _lat = widget.focusLat;
        _lng = widget.focusLng;
        _currentLocation = LatLng(widget.focusLat!, widget.focusLng!);
        _displayLocation = _currentLocation;
        _displayLocationNotifier.value = _currentLocation;
        _initialCameraReady = true;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _recenterMap();
      });
      // Load the SOS alert around the focused point immediately so the marker
      // is visible even before the camera-move debounce fires.
      context
          .read<SosProvider>()
          .fetchNearbySos(widget.focusLat!, widget.focusLng!, radiusKm: 5);
    }

    if (mounted && _lat != null && _lng != null) {
      // Attempt to load cached data first (SQLite — instant, no spinner)
      await _loadCachedPlaces();
      if (!mounted) return;

      // Viewport places: served from memory / the grid-keyed Redis cache when
      // the same area was fetched recently, so reopening never re-issues it.
      unawaited(_fetchPlacesForViewport());
      unawaited(provider.fetchFeaturedPlaces(lat: _lat, lng: _lng));
      _fetchWeatherForViewport();
      context.read<SosProvider>().fetchNearbySos(_lat!, _lng!, radiusKm: 5);
    }

    if (widget.focusLat == null && widget.focusLng == null) {
      // ── 2) Live GPS in the BACKGROUND ────────────────────────────────
      // The stream starts immediately (blue dot + follow work off it) while
      // the bounded resolver below settles permission / service status.
      // Neither one delays the restored map.
      _startPositionTracking();
      unawaited(_resolveFreshLocation());
    }

    if (!mounted) return;
    if (_lat == null || _lng == null) {
      // No location at all — fall back to the default Nepal center so the
      // map (and its Nepal-wide pins) is still usable. _currentLocation is
      // deliberately left null: no fake blue dot until a real fix arrives.
      setState(() {
        _lat = AppConstants.defaultLatitude;
        _lng = AppConstants.defaultLongitude;
        _initialCameraReady = true;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _recenterMap();
      });
    }

    if (mounted && _lat != null) {
      _zoomNotifier.value = _currentZoom;
      _persistCameraSoon();
    }

    // Auto-fetch route to destination (Place Details "Directions")
    if (_destinationLat != null && _destinationLng != null) {
      await _fetchDestinationRoute();
    }

    // Periodically refresh nearby SOS markers so they move in real-time
    // when the active SOS user is traveling.
    _startSosRefreshTimer();
  }

  void _startSosRefreshTimer() {
    _sosRefreshTimer?.cancel();
    _sosRefreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!mounted || _lat == null || _lng == null) return;
      context.read<SosProvider>().fetchNearbySos(_lat!, _lng!, radiusKm: 5);
    });
  }

  // ─────────────────────────────────────────────────────────────────────
  // Cached open: restore the previous session, then resolve fresh GPS
  // ─────────────────────────────────────────────────────────────────────

  /// Rebuilds the previous camera + position from SharedPreferences.
  ///
  /// This is the first `await` in [_initMap] on purpose: one local read, no
  /// GPS, no network — so the first frame is already the view the user left
  /// behind instead of the default Nepal centre. Falls back to the OS
  /// last-known fix on a first-ever open.
  Future<void> _restoreCachedCamera() async {
    try {
      final saved = await NearbyMapCache.instance.read();
      if (!mounted) return;

      if (saved != null) {
        // The camera is restored unconditionally (it is where the user
        // *looked*); the blue dot is only restored when the stored fix is
        // recent enough to honestly mean "you are here".
        final loc = saved.freshLocation;
        setState(() {
          _lat = saved.centerLat;
          _lng = saved.centerLng;
          _currentZoom = saved.zoom;
          _previousZoom = saved.zoom;
          _isTracking = saved.isTracking;
          if (loc != null) {
            _currentLocation = loc;
            _displayLocation = loc;
            _displayLocationNotifier.value = loc;
            if (saved.accuracy != null) {
              _accuracyM = saved.accuracy!;
              _accuracyNotifier.value = _accuracyM;
            }
            _lastFixAt = saved.fixedAt ?? _lastFixAt;
          }
        });
        return;
      }

      // First-ever open: the OS last-known fix is free (no permission prompt).
      final lastKnown = await _locationService.getLastKnownPosition();
      if (!mounted) return;
      setState(() {
        if (lastKnown != null) {
          _lat = lastKnown.latitude;
          _lng = lastKnown.longitude;
          _currentLocation = LatLng(lastKnown.latitude, lastKnown.longitude);
          _displayLocation = _currentLocation;
          _displayLocationNotifier.value = _currentLocation;
          _accuracyM = lastKnown.accuracy;
          _accuracyNotifier.value = _accuracyM;
        }
      });
    } catch (e) {
      debugPrint('Camera restore failed: $e');
    } finally {
      // Whatever happened, the map must build — an unreadable snapshot
      // degrades to the default centre, it never leaves a spinner.
      if (mounted) _initialCameraReady = true;
    }
  }

  /// Resolves permission + service status once per open with a bounded
  /// timeout, then applies the fix it returns. Never blocks the map: the
  /// restored camera is already on screen by the time this runs.
  Future<void> _resolveFreshLocation() async {
    var status = DeviceLocationStatus.unavailable;
    LatLng? fix;
    double? accuracy;
    DateTime? fixedAt;
    try {
      final result = await _locationService
          .resolveDeviceLocation()
          .timeout(const Duration(seconds: 12));
      status = result.status;
      if (result.hasCoordinates) {
        fix = LatLng(result.latitude!, result.longitude!);
        accuracy = result.accuracy;
        fixedAt = result.fixedAt ?? DateTime.now();
      }
    } catch (_) {
      status = DeviceLocationStatus.unavailable;
    }
    if (!mounted) return;
    if (_locationStatus != status) {
      setState(() => _locationStatus = status);
    }
    if (fix != null) {
      _applyLocationFix(fix, accuracy: accuracy, fixedAt: fixedAt);
    }
  }

  /// Accepts a raw GPS fix: stores it as the chase target, starts the smooth
  /// blue-dot interpolation, and persists it for the next open.
  ///
  /// [snap] skips the interpolation — used when the user explicitly asked for
  /// a fresh position, so the dot must land exactly where it was asked for
  /// rather than easing in from wherever it was before.
  void _applyLocationFix(
    LatLng loc, {
    double? accuracy,
    DateTime? fixedAt,
    bool snap = false,
  }) {
    if (!mounted) return;
    // Reject out-of-order fixes so the dot never rewinds in time.
    if (fixedAt != null) {
      if (fixedAt.isBefore(_lastFixAt)) return;
      _lastFixAt = fixedAt;
    }
    final firstFix = _currentLocation == null;
    _currentLocation = loc;
    if (accuracy != null && accuracy > 0) {
      _accuracyM = accuracy;
      _accuracyNotifier.value = accuracy;
    }

    final display = _displayLocation;
    if (snap || display == null) {
      // First fix (or an explicit request): snap, nothing to interpolate.
      _displayLocation = loc;
      _displayLocationNotifier.value = loc;
      _stopDotChase();
    } else if (const Distance().as(LengthUnit.Meter, display, loc) > 60000) {
      // Huge jump (travelled while the app was closed): the dot cannot be
      // eased across 60km+, so it teleports — and while following, the camera
      // glides there in one smooth shot instead of snapping.
      _displayLocation = loc;
      _displayLocationNotifier.value = loc;
      _stopDotChase();
      if (_isTracking && _mapReady && !_isAnimating) {
        _smoothMoveTo(
          loc,
          duration: const Duration(milliseconds: 700),
          curve: Curves.easeOut,
        );
      }
    } else {
      _startDotChase();
    }
    // First fix on this screen: the map only starts showing the dot once
    // _currentLocation is set, so a rebuild has to be requested explicitly.
    if (firstFix) setState(() {});
    _persistLocationSoon();
  }

  // ─────────────────────────────────────────────────────────────────────
  // Blue-dot chase: exponential interpolation toward the latest GPS fix
  // ─────────────────────────────────────────────────────────────────────

  void _startDotChase() {
    if (_currentLocation == null) return;
    final ticker = _dotTicker ??= createTicker(_tickDot);
    if (!ticker.isActive) ticker.start();
  }

  void _stopDotChase() => _dotTicker?.stop();

  void _tickDot(Duration _) {
    final target = _currentLocation;
    final display = _displayLocation;
    if (target == null || display == null) {
      _stopDotChase();
      return;
    }

    const double k = 0.18; // ~1/6 of the gap per frame at 60fps
    const double maxStepDeg = 0.05; // ~5.5km — big jumps never teleport

    var dLat = target.latitude - display.latitude;
    var dLng = target.longitude - display.longitude;
    if (dLng > 180) dLng -= 360;
    if (dLng < -180) dLng += 360;

    var stepLat = dLat * k;
    var stepLng = dLng * k;
    if (stepLat.abs() > maxStepDeg) stepLat = stepLat.sign * maxStepDeg;
    if (stepLng.abs() > maxStepDeg) stepLng = stepLng.sign * maxStepDeg;

    var newLat = display.latitude + stepLat;
    var newLng = display.longitude + stepLng;
    if (newLat > 90) newLat = 90;
    if (newLat < -90) newLat = -90;
    newLng = ((newLng + 540) % 360) - 180;

    final next = LatLng(newLat, newLng);
    final remaining =
        const Distance().as(LengthUnit.Meter, next, target);
    final settled = remaining < 0.4;

    _displayLocation = settled ? target : next;
    _displayLocationNotifier.value = _displayLocation;

    // The camera chases the DISPLAYED dot (not the raw fix), so the map and
    // the blue dot always move as one thing.
    if (_isTracking && !_isAnimating && _routes.isEmpty && _mapReady) {
      _moveCamera(_displayLocation!);
    }

    if (settled) _stopDotChase();
  }

  /// Applies the follow-camera glide (no gesture events, so it never flips
  /// tracking off the way a user pan would).
  void _moveCamera(LatLng center, {double? zoom}) {
    if (!_mapReady) return;
    try {
      _mapController.move(center, zoom ?? _currentZoom);
      final cam = _mapController.camera;
      _currentZoom = cam.zoom;
      _lat = cam.center.latitude;
      _lng = cam.center.longitude;
      if (_zoomNotifier.value != _currentZoom) {
        _zoomNotifier.value = _currentZoom;
      }
      _persistCameraSoon();
    } catch (e) {
      debugPrint('Map move failed: $e');
    }
  }

  // ─────────────────────────────────────────────────────────────────────
  // Camera-state bookkeeping shared by gestures and programmatic moves
  // ─────────────────────────────────────────────────────────────────────

  void _handleViewportChanged(MapCamera camera) {
    _currentZoom = camera.zoom;
    _lat = camera.center.latitude;
    _lng = camera.center.longitude;
    if (_zoomNotifier.value != _currentZoom) {
      _zoomNotifier.value = _currentZoom;
    }

    // Bump _camVersion only when crossing polygon zoom thresholds (7, 9).
    // Toggling province/district layers must not rebuild on every pan.
    final prev = _previousZoom;
    final now = _currentZoom;
    final crossedThreshold = (prev < 7 && now >= 7) || (prev >= 7 && now < 7) ||
        (prev < 9 && now >= 9) || (prev >= 9 && now < 9);
    _previousZoom = now;
    if (crossedThreshold) _camVersion.value++;

    // Throttle place fetching on map move - 300ms delay after movement stops
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      if (!mounted) return;
      _fetchPlacesForViewport();
      if (_lat != null && _lng != null) {
        context.read<SosProvider>().fetchNearbySos(_lat!, _lng!, radiusKm: 5);
      }
    });

    // Throttle weather fetch on map move
    _weatherDebounceTimer?.cancel();
    _weatherDebounceTimer = Timer(const Duration(milliseconds: 300), () {
      if (mounted) _fetchWeatherForViewport();
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

    _persistCameraSoon();
  }

  /// Turns off follow + compass — the user took manual control of the camera.
  void _exitFollowModes() {
    if (!_isTracking && !_compassMode) return;
    _stopCompassFollow();
    setState(() {
      _isTracking = false;
      _compassMode = false;
    });
    _persistCameraSoon();
  }

  /// True when the camera is showing the blue dot near the middle of the
  /// screen — used to decide whether the FAB still needs a recenter.
  bool _centeredOnUser({double tolerancePx = 80}) {
    final loc = _displayLocation ?? _currentLocation;
    if (loc == null || !_mapReady) return false;
    try {
      final cam = _mapController.camera;
      final a = cam.latLngToScreenPoint(cam.center);
      final b = cam.latLngToScreenPoint(loc);
      final dx = a.x - b.x;
      final dy = a.y - b.y;
      return math.sqrt(dx * dx + dy * dy) <= tolerancePx;
    } catch (_) {
      return false;
    }
  }

  /// Compass-mode bearing: shortest-arc smoothing so a small sensor jitter
  /// never whips the map the long way round.
  void _applyCompassRotation(double heading) {
    if (!_compassMode || !_mapReady) return;
    double target;
    if (_smoothedCompassHeading == null) {
      // Seed from the camera's current rotation so entering compass mode
      // never snaps — the first sample just starts nudging from here.
      target = -_mapController.camera.rotation;
      _smoothedCompassHeading = target;
    } else {
      final arc = _shortestArc(_smoothedCompassHeading!, heading);
      target = _smoothedCompassHeading! + arc * 0.15;
    }
    _smoothedCompassHeading = target;

    // MapCamera.withRotation does NOT normalise the angle; keep it in
    // [-180, 180) so _buildCompass's isNorthUp check stays correct.
    final rotation = _normalizeRotation(-target);
    if (_mapController.rotate(rotation)) {
      _rotationNotifier.value = rotation;
    }
  }

  static double _normalizeRotation(double deg) {
    var r = deg % 360;
    if (r >= 180) r -= 360;
    if (r < -180) r += 360;
    return r;
  }

  // ─────────────────────────────────────────────────────────────────────
  // Persistence (debounced) — one key, camera + location written together
  // ─────────────────────────────────────────────────────────────────────

  void _persistCameraSoon() {
    if (_cameraPersistTimer?.isActive ?? false) return;
    _cameraPersistTimer = Timer(const Duration(milliseconds: 600), _persistNow);
  }

  void _persistLocationSoon() {
    if (_persistLocationQueued) return;
    _persistLocationQueued = true;
    _locationPersistTimer?.cancel();
    _locationPersistTimer = Timer(const Duration(seconds: 2), _persistNow);
  }

  void _persistNow() {
    _cameraPersistTimer?.cancel();
    _locationPersistTimer?.cancel();
    _persistLocationQueued = false;
    // A focus-mode open (SOS pin) must not overwrite the user's own view.
    if (!_initialCameraReady || widget.focusLat != null) return;
    final lat = _lat;
    final lng = _lng;
    if (lat == null || lng == null) return;
    unawaited(NearbyMapCache.instance.save(
      centerLat: lat,
      centerLng: lng,
      zoom: _currentZoom,
      isTracking: _isTracking,
      location: _currentLocation,
      accuracy: _currentLocation == null ? null : _accuracyM,
      fixedAt: _lastFixAt.millisecondsSinceEpoch == 0 ? null : _lastFixAt,
    ));
  }

  /// Smoothly glide the camera to [target] with optional zoom change.
  ///
  /// Uses [_cameraAnimController] with [curve] over [duration].
  /// Sets [_isAnimating] so [_tickDot] and the GPS stream leave the camera
  /// alone while the glide runs — a programmatic move is not a user gesture,
  /// so it must NOT cancel follow the way a drag would.
  void _smoothMoveTo(
    LatLng target, {
    Duration duration = const Duration(milliseconds: 400),
    double? targetZoom,
    Curve curve = Curves.easeInOut,
    double? targetRotation,
  }) {
    if (!_mapReady) return;
    // Cancel any in-progress animation cleanly
    if (_cameraAnimController.isAnimating) {
      final oldListener = _cameraAnimListener;
      if (oldListener != null) {
        _cameraAnimController.removeListener(oldListener);
      }
      _cameraAnimController.stop();
    }

    final startCam = _mapController.camera;
    final start = startCam.center;
    final startZoom = startCam.zoom;
    final startRot = startCam.rotation;
    final endZoom = targetZoom ?? startZoom;
    final endRot =
        targetRotation == null ? null : _normalizeRotation(targetRotation);
    _cameraAnimController.duration = duration;
    _isAnimating = true;

    final listener = () {
      final t = curve.transform(_cameraAnimController.value);
      try {
        final center = LatLng(
          start.latitude + (target.latitude - start.latitude) * t,
          start.longitude + (target.longitude - start.longitude) * t,
        );
        final zoom = startZoom + (endZoom - startZoom) * t;
        if (endRot == null) {
          _mapController.move(center, zoom);
        } else {
          _mapController.moveAndRotate(
            center,
            zoom,
            startRot + _shortestArc(startRot, endRot) * t,
          );
        }
        // Keep the viewport bookkeeping live mid-flight so a debounced fetch
        // during the glide asks for the area actually being shown.
        _lat = center.latitude;
        _lng = center.longitude;
        _currentZoom = zoom;
      } catch (e) {
        debugPrint('Smooth camera move failed: $e');
      }
    };
    _cameraAnimListener = listener;
    _cameraAnimController.addListener(listener);

    _cameraAnimController
      ..reset()
      ..forward().then((_) {
        // Animation complete — clean up and run one final update
        _cameraAnimController.removeListener(listener);
        _cameraAnimListener = null;
        _isAnimating = false;
        if (mounted && _mapReady) {
          _handleViewportChanged(_mapController.camera);
        }
      });
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
    // Re-entrant: the resolver, "Try Again" and the focus path all call this.
    // A second subscription would double-apply every fix.
    _positionStream?.cancel();
    _positionStream = _locationService
        .getPositionStream(intervalMs: 3000, distanceFilterM: 5)
        .listen((position) {
      final loc = LatLng(position.latitude, position.longitude);
      if (!mounted) return;
      _checkArrival(loc);
      // Merge GPS heading + location into a single setState to avoid
      // two consecutive full-widget rebuilds per GPS fix.
      final gpsH = position.heading;
      setState(() {
        if (gpsH != null && gpsH > 0) {
          _gpsHeading = (gpsH % 360 + 360) % 360;
          _lastGpsHeadingAt = DateTime.now();
          _useGpsHeading = true;
        } else {
          // No bearing (stationary) - compass takes over.
          _useGpsHeading = false;
        }
      });
      _headingNotifier.value = _heading;

      // Route on screen: keep it visible instead of dragging the camera to
      // the user, re-fitting only when they walk outside the viewport.
      if (_isTracking && _routes.isNotEmpty) {
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
      }

      // Feed the chase: the blue dot glides to this fix, and the camera
      // follows the dot (never the raw fix) while tracking is on.
      _applyLocationFix(
        loc,
        accuracy: position.accuracy,
        fixedAt: position.timestamp,
      );
    }, onError: (Object e) {
      // The stream starts before permission is resolved, so a denial can
      // arrive as a stream error — the status card reports it instead.
      debugPrint('Position stream error: $e');
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

  /// Maximum bbox dimension (degrees) the client will request from the API.
  /// Prevents zooming out from generating a country-spanning query.
  static const double _maxBboxDim = 15.0;

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

    // Cap span so the bbox never exceeds _maxBboxDim degrees in either axis.
    final cappedLatSpan = math.min(viewLatSpan, _maxBboxDim / 2);
    final cappedLngSpan = math.min(viewLngSpan, _maxBboxDim / 2);

    return _ViewportBounds(
      minLat: math.max(_lat! - cappedLatSpan, _nepalMinLat),
      maxLat: math.min(_lat! + cappedLatSpan, _nepalMaxLat),
      minLng: math.max(_lng! - cappedLngSpan, _nepalMinLng),
      maxLng: math.min(_lng! + cappedLngSpan, _nepalMaxLng),
    );
  }


  Future<void> _fetchPlacesForViewport({String? search}) async {
    if (_lat == null || _lng == null) return;

    final provider = context.read<PlaceProvider>();

    // ── Viewport bbox calculation with 40% prefetch buffer ──────────────
    // Request a larger area than the visible viewport so panning within
    // the buffered region is instant (no API call needed).
    final vp = _getViewportBounds();
    final latSpan = vp.maxLat - vp.minLat;
    final lngSpan = vp.maxLng - vp.minLng;
    final bufLat = latSpan * 0.4;
    final bufLng = lngSpan * 0.4;
    final bboxMinLat = vp.minLat - bufLat;
    final bboxMaxLat = vp.maxLat + bufLat;
    final bboxMinLng = vp.minLng - bufLng;
    final bboxMaxLng = vp.maxLng + bufLng;

    // ── Smart skip: if previous bbox covers current viewport, no API call ──
    if (search == null &&
        _cachedBoundsNorth != null &&
        _cachedBoundsSouth != null &&
        _cachedBoundsEast != null &&
        _cachedBoundsWest != null &&
        vp.minLat >= _cachedBoundsSouth! &&
        vp.maxLat <= _cachedBoundsNorth! &&
        vp.minLng >= _cachedBoundsWest! &&
        vp.maxLng <= _cachedBoundsEast!) {
      return; // Viewport is inside previously loaded area
    }

    // ── Fast path: bbox API (primary data source) ───────────────────────
    if (search == null && _currentZoom >= 8) {
      if (_isFetchingPlaces) return;
      _isFetchingPlaces = true;

      try {
        await provider.fetchViewportPlaces(
          minLat: bboxMinLat,
          maxLat: bboxMaxLat,
          minLng: bboxMinLng,
          maxLng: bboxMaxLng,
          zoom: _currentZoom.round(),
          category: _activeFilter?.categoryId != null
              ? _getCategoryName(_activeFilter!.categoryId!)
              : null,
        );

        // Update cached bounds for smart skip
        _cachedBoundsNorth = bboxMaxLat;
        _cachedBoundsSouth = bboxMinLat;
        _cachedBoundsEast = bboxMaxLng;
        _cachedBoundsWest = bboxMinLng;

        _checkOsmSubmissionStatuses();
      } catch (e) {
        debugPrint('bbox API failed, falling back to nepalPlaces: $e');
      }

      _isFetchingPlaces = false;
      return;
    }

    // ── Fallback: client-side from nepalPlaces (offline / search / cold start) ──
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
      provider.setViewportPlacesDirect(sorted.take(200).toList());
      _checkOsmSubmissionStatuses();
      return;
    }

    // ── Legacy cold start: nearby-combined API ──────────────────────────
    if (_currentZoom < 10) return;
    if (_isFetchingPlaces) return;

    final radius = _zoomToRadius(_currentZoom);
    _isFetchingPlaces = true;
    setState(() => _isLoadingPlaces = true);

    try {
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

      final latKm = 111.32;
      final lngKm = 111.32 * math.cos(_lat! * math.pi / 180);
      _cachedBoundsNorth = _lat! + (fetchRadius / latKm);
      _cachedBoundsSouth = _lat! - (fetchRadius / latKm);
      _cachedBoundsEast = _lng! + (fetchRadius / lngKm);
      _cachedBoundsWest = _lng! - (fetchRadius / lngKm);
    } catch (e) {
      debugPrint('Legacy viewport fetch failed: $e');
    }

    _isFetchingPlaces = false;
    if (mounted) setState(() => _isLoadingPlaces = false);
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
    // Inspecting another place means the camera is no longer about the user:
    // stop following/compass before the glide, or the dot would drag it back.
    _exitFollowModes();
    try {
      _smoothMoveTo(
        LatLng(place.latitude, place.longitude),
        duration: const Duration(milliseconds: 400),
        targetZoom: 15.0,
        curve: Curves.easeInOut,
      );
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
        // Following a route -> watch for alerts/reports along the way.
        ProximityAlertService.instance.startNavigationMonitoring();
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
    ProximityAlertService.instance.stopNavigationMonitoring();
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
        // Following a route -> watch for alerts/reports along the way.
        ProximityAlertService.instance.startNavigationMonitoring();
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

  /// Location status card: nothing to show while the status is still
  /// resolving or when it is fine — only the states the user can fix.
  List<Widget> _locationStatusCard() {
    final status = _locationStatus;
    if (status == null ||
        status == DeviceLocationStatus.grantedFresh ||
        status == DeviceLocationStatus.grantedLastKnown) {
      return const [];
    }

    final String message;
    final IconData icon;
    switch (status) {
      case DeviceLocationStatus.denied:
        message = context
            .t('Location permission is off. Allow location access to show your position on the map.');
        icon = Icons.location_off;
        break;
      case DeviceLocationStatus.serviceDisabled:
        message = context
            .t('Your device location is turned off. Enable it to show your position.');
        icon = Icons.gps_off;
        break;
      default:
        message = context.t(
            'Waiting for your current location... Please enable GPS and allow location permission.');
        icon = Icons.my_location;
    }

    return [
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
                    Icon(icon, color: AppTheme.errorColor),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        message,
                        style: const TextStyle(
                            fontSize: 13, color: AppTheme.textSecondary),
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
    ];
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        body: Stack(
        children: [
          // Map with tile mode switch (single map, single controller)
          // Held back until the previous session's camera has been restored,
          // so the FlutterMap's initialCenter/initialZoom are already the
          // view the user left — no default-centre flash on open.
          if (!_initialCameraReady)
            const Center(child: CircularProgressIndicator())
          else
            ValueListenableBuilder<int>(
              valueListenable: _camVersion,
              builder: (context, _, __) {
                return Consumer<MapViewProvider>(
                  builder: (context, mapView, _) {
                    return _buildFlutterMap(
                      isSatellite: mapView.isSatellite,
                      placesVisible: mapView.showPlaces,
                      showWeather: mapView.showWeather,
                      showRoutes: mapView.showRoutes,
                    );
                  },
                );
              },
            ),

          // Map mode toggle button
          Positioned(
            top: MediaQuery.of(context).padding.top + 50,
            left: 16,
            child: _buildMapModeToggle(),
          ),

          // Visible source attribution for the active tile provider
          _buildMapAttribution(),

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

          // Focus banner (e.g. viewing an SOS location from the emergency inbox)
          if (widget.focusLat != null && widget.focusLng != null && widget.focusLabel != null)
            Positioned(
              top: MediaQuery.of(context).padding.top + 62,
              left: 16,
              right: 74,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.95),
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.12),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.sos, size: 16, color: AppTheme.errorColor),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          widget.focusLabel!,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
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

          // Location status card — only when the user can act on it. The
          // restored map keeps rendering underneath, so a denied permission
          // never blocks the screen the way the old "no location yet" card did.
          ..._locationStatusCard(),

          _buildBottomSheet(),
        ],
      ),
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
        initialZoom: _currentZoom,
        maxZoom: AppConstants.maxMapZoom,
        minZoom: 7.0,
        cameraConstraint: CameraConstraint.contain(
          bounds: LatLngBounds(
            const LatLng(_nepalMinLat - 1.0, _nepalMinLng - 1.0),
            const LatLng(_nepalMaxLat + 1.0, _nepalMaxLng + 1.0),
          ),
        ),
        interactionOptions: const InteractionOptions(
          flags: InteractiveFlag.all,
        ),
        onMapEvent: (event) {
          // Programmatic moves emit MapEventMove / MapEventRotate only, so
          // our own animations and the follow-camera never trip the checks
          // below. The Start/End pairs (and the scroll-wheel event, which has
          // no pair) are emitted exclusively by user gestures.
          final isGestureEnd = event is MapEventMoveEnd ||
              event is MapEventRotateEnd ||
              event is MapEventDoubleTapZoomEnd ||
              event is MapEventFlingAnimationEnd;
          final isGestureBegun = event is MapEventMoveStart ||
              event is MapEventRotateStart ||
              event is MapEventDoubleTapZoomStart ||
              event is MapEventFlingAnimationStart ||
              event is MapEventScrollWheelZoom;

          if ((isGestureBegun || isGestureEnd) && !_isAnimating) {
            // Manual gesture = the user is taking the camera back.
            _exitFollowModes();
          }

          if (isGestureEnd || event is MapEventScrollWheelZoom) {
            _handleViewportChanged(event.camera);
          } else if (event is MapEventRotate) {
            // rotateRaw never fires onPositionChanged, so pure-rotation
            // changes (our compass updates included) land here instead.
            _rotationNotifier.value = event.camera.rotation;
          }
        },
        onPositionChanged: (camera, hasGesture) {
          _rotationNotifier.value = camera.rotation;
          // Fires on every drag/pinch frame — keeps the blue dot's accuracy
          // ring scaling live instead of waiting for the gesture to end.
          if (_zoomNotifier.value != camera.zoom) {
            _zoomNotifier.value = camera.zoom;
          }
        },
        onTap: (_, __) {
          setState(() => _selectedPlace = null);
        },
      ),
      children: [
        if (isSatellite) ...[
          TileLayer(
            urlTemplate:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            userAgentPackageName: 'np.com.nepalsmarttravel',
            maxZoom: 19,
            tileProvider: _satelliteTiles,
          ),
        ] else
          TileLayer(
            // OpenStreetMap - reliable, no API key needed
            // Labels will show but most reliable tile source
            urlTemplate:
                'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            userAgentPackageName: 'np.com.nepalsmarttravel',
            minZoom: 7.0,
            maxZoom: 19,
            tileProvider: _offlineTiles,
          ),
        // Nepal boundary + provinces + districts
        if (NepalBoundaryService.instance.nepalBoundary.isNotEmpty) ...[
          PolygonLayer(polygons: NepalBoundaryService.instance.buildNepalMask()),
          if (_currentZoom >= 7)
            PolygonLayer(polygons: NepalBoundaryService.instance.buildProvincePolygons()),
          if (_currentZoom >= 9)
            PolygonLayer(polygons: NepalBoundaryService.instance.buildDistrictPolygons()),
          if (_currentZoom >= 9)
            MarkerLayer(markers: NepalBoundaryService.instance.buildDistrictLabels()),
        ],
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
                        ? const Color(0xFFB45309).withOpacity(0.5)
                        : AppTheme.primaryColor.withOpacity(0.9),
                    strokeWidth: r.isTrekking ? 3 : 4,
                    pattern: r.isTrekking
                        ? StrokePattern.dashed(segments: const [10.0, 8.0])
                        : const StrokePattern.solid(),
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
        // Lives in its own listenable subtree: the chase tick, the compass
        // heading, the accuracy and the zoom all update the dot without ever
        // rebuilding the FlutterMap (which would cost a full layer diff).
        if (_currentLocation != null)
          ValueListenableBuilder<LatLng?>(
            valueListenable: _displayLocationNotifier,
            builder: (context, displayed, _) {
              final loc = displayed ?? _currentLocation!;
              return MarkerLayer(
                markers: [
                  Marker(
                    point: loc,
                    width: 200,
                    height: 200,
                    alignment: Alignment.center,
                    child: ValueListenableBuilder<double?>(
                      valueListenable: _headingNotifier,
                      builder: (context, heading, _) {
                        return ValueListenableBuilder<double>(
                          valueListenable: _zoomNotifier,
                          builder: (context, zoom, _) {
                            return ValueListenableBuilder<double>(
                              valueListenable: _accuracyNotifier,
                              builder: (context, accuracy, _) {
                                return MapBlueDot(
                                  heading: heading,
                                  accuracyMeters: accuracy,
                                  zoom: zoom,
                                  latitude: loc.latitude,
                                );
                              },
                            );
                          },
                        );
                      },
                    ),
                  ),
                ],
              );
            },
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
        // SOS markers from nearby users
        Consumer<SosProvider>(
          builder: (context, sosProvider, _) {
            if (sosProvider.nearbySos.isEmpty) return const SizedBox.shrink();
            return MarkerLayer(
              markers: [
                for (final sos in sosProvider.nearbySos)
                  Marker(
                    point: LatLng(sos.latitude, sos.longitude),
                    width: 70,
                    height: 70,
                    alignment: Alignment.center,
                    child: SosMarker(
                      latitude: sos.latitude,
                      longitude: sos.longitude,
                      distanceKm: sos.distanceKm,
                      durationSeconds: sos.durationSeconds,
                      emergencyType: sos.emergencyType,
                      onTap: () => _showSosInfoSheet(context, sos),
                    ),
                  ),
              ],
            );
          },
        ),
        if (placesVisible)
          Consumer<PlaceProvider>(
            builder: (context, provider, _) {
              final viewportPlaces = _markerPlacesForViewport(provider);
              final mapMarkers = _buildMarkers(viewportPlaces);
              return MarkerLayer(
                markers: mapMarkers,
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
    // Pointing north also ends bearing-follow: the compass FAB and the
    // blue-dot state machine must not disagree about the current mode.
    if (_compassMode) setState(() => _compassMode = false);
    _stopCompassFollow();
    if (!_mapReady) {
      _rotationNotifier.value = 0;
      _mapController.rotate(0);
      return;
    }
    _smoothMoveTo(
      _mapController.camera.center,
      duration: const Duration(milliseconds: 300),
      targetRotation: 0,
    );
    _persistCameraSoon();
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

  /// Visible map attribution for whichever tile provider is on screen:
  /// '© Esri, Maxar, Earthstar Geographics' for Satellite (Esri World Imagery)
  /// and '© OpenStreetMap contributors' for Standard mode. Tapping opens the
  /// provider's attribution page.
  Widget _buildMapAttribution() {
    return Consumer<MapViewProvider>(
      builder: (context, mapView, _) {
        final isSatellite = mapView.isSatellite;
        return Positioned(
          top: MediaQuery.of(context).padding.top + 114,
          right: 16,
          child: Material(
            color: const Color(0xE6FFFFFF),
            borderRadius: BorderRadius.circular(6),
            elevation: 2,
            child: InkWell(
              borderRadius: BorderRadius.circular(6),
              onTap: () {
                final Uri url = Uri.parse(isSatellite
                    ? 'https://www.esri.com/en-us/home'
                    : 'https://www.openstreetmap.org/copyright');
                launchUrl(url, mode: LaunchMode.externalApplication);
              },
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                child: Text(
                  isSatellite
                      ? '© Esri, Maxar, Earthstar Geographics'
                      : '© OpenStreetMap contributors',
                  style: const TextStyle(
                    fontSize: 10,
                    height: 1.2,
                    fontWeight: FontWeight.w500,
                    color: AppTheme.textSecondary,
                  ),
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
          icon: _compassMode
              ? Icons.navigation
              : Icons.my_location,
          color: _compassMode
              ? Colors.teal
              : _isTracking
                  ? AppTheme.primaryColor
                  : Colors.white,
          iconColor: _compassMode
              ? Colors.white
              : _isTracking
                  ? Colors.white
                  : AppTheme.primaryColor,
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
    final isCompassMode = icon == Icons.navigation;
    return Container(
      decoration: isCompassMode
          ? BoxDecoration(
              shape: BoxShape.rectangle,
              borderRadius: BorderRadius.circular(12),
              boxShadow: [
                BoxShadow(
                  color: Colors.teal.withOpacity(0.5),
                  blurRadius: 12,
                  spreadRadius: 2,
                ),
              ],
            )
          : null,
      child: Material(
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
      ),
    );
  }

  /// Two-stage "Where I Am" button (plus a third tap to leave compass).
  ///
  ///  1. Not centred on the dot  -> glide to it, north-up, follow ON.
  ///  2. Already centred         -> compass mode (bearing follows the phone).
  ///  3. Compass on              -> back to north-up, still centred.
  ///
  /// Any manual gesture drops back to stage 1 via [_exitFollowModes].
  void _onMyLocationTap() {
    // Centre on the dot the user can SEE (the chased display position):
    // gliding to the raw fix would land the camera ahead of the dot and then
    // get dragged back the moment the ticker resumes.
    final loc = _displayLocation ?? _currentLocation;
    if (loc == null) {
      _requestLocationAndRecenter();
      return;
    }

    final centred =
        _isTracking && _hasRecenteredWithButton && _centeredOnUser();

    if (!centred) {
      // Stage 1: take me there — smooth glide, north-up, follow on.
      setState(() {
        _isTracking = true;
        _hasRecenteredWithButton = true;
      });
      _stopCompassFollow();
      HapticFeedback.lightImpact();
      _smoothMoveTo(
        loc,
        duration: const Duration(milliseconds: 500),
        targetZoom: math.max(_currentZoom, 15.0),
        targetRotation: 0,
      );
      _showModeSnackbar(context.t('Centered on your location'));
    } else if (!_compassMode) {
      // Stage 2: already there — now rotate the map with the device.
      if (FlutterCompass.events == null) return; // no magnetometer
      setState(() => _compassMode = true);
      HapticFeedback.lightImpact();
      _startCompassFollow();
      _showModeSnackbar(context.t('Compass mode on'));
    } else {
      // Stage 3: leave compass mode, stay centred, face north again.
      setState(() => _compassMode = false);
      HapticFeedback.lightImpact();
      _stopCompassFollow();
      _smoothMoveTo(
        loc,
        duration: const Duration(milliseconds: 300),
        targetRotation: 0,
      );
      _showModeSnackbar(context.t('Compass mode off'));
    }
    _persistCameraSoon();
  }

  void _showModeSnackbar(String msg) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg, style: const TextStyle(fontSize: 13)),
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 2),
        margin: const EdgeInsets.symmetric(horizontal: 80, vertical: 80),
      ),
    );
  }

  /// Arms compass-mode bearing. The sensor listener itself lives in
  /// [_initCompass] (a single shared subscription), so this only resets the
  /// smoothing seed and applies the current heading once, so the map starts
  /// turning immediately instead of waiting for the next sensor sample.
  void _startCompassFollow() {
    _smoothedCompassHeading = null;
    final heading = _compassHeading;
    if (heading != null && _compassMode && _mapReady) {
      _applyCompassRotation(heading);
    }
  }

  void _stopCompassFollow() {
    _smoothedCompassHeading = null;
  }

  /// Explicit "get a fix now" path (first open with no fix, or a retry).
  /// Uses the status-aware resolver so a denied permission is reported as
  /// denied instead of showing a generic failure.
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
    final result = await _locationService.resolveDeviceLocation();
    if (!mounted) return;
    if (result.status != _locationStatus) {
      setState(() => _locationStatus = result.status);
    }
    if (!result.hasCoordinates) {
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
    final loc = LatLng(result.latitude!, result.longitude!);
    _applyLocationFix(
        loc, accuracy: result.accuracy, fixedAt: result.fixedAt, snap: true);
    setState(() {
      _isTracking = true;
      _hasRecenteredWithButton = true;
    });
    _startPositionTracking();
    _centerOn(loc, zoom: 15.0);
  }

  /// "Try Again" from the location status card: re-resolve, re-centre and
  /// refresh the surrounding places.
  Future<void> _retryLocation() async {
    final result = await _locationService.resolveDeviceLocation();
    if (!mounted) return;
    if (result.status != _locationStatus) {
      setState(() => _locationStatus = result.status);
    }
    if (!result.hasCoordinates) return; // the card now explains why

    final loc = LatLng(result.latitude!, result.longitude!);
    _applyLocationFix(
        loc, accuracy: result.accuracy, fixedAt: result.fixedAt, snap: true);
    setState(() => _isTracking = true);
    _startPositionTracking();

    _lastFetchLat = null;
    _lastFetchLng = null;
    _lastFetchRadius = -1;
    _cachedBoundsNorth = null;
    _cachedBoundsSouth = null;
    _cachedBoundsEast = null;
    _cachedBoundsWest = null;
    await _loadCachedPlaces();
    if (!mounted) return;
    _centerOn(loc, zoom: 15.0);
    _fetchPlacesForViewport();
  }

  /// Programmatic centre — also re-syncs the viewport bookkeeping (a plain
  /// [MapController.move] never emits MapEventMoveEnd, so the shared handler
  /// has to run explicitly).
  void _centerOn(LatLng loc, {double? zoom}) {
    if (!_mapReady) return;
    try {
      _mapController.move(loc, zoom ?? _currentZoom);
      _handleViewportChanged(_mapController.camera);
    } catch (e) {
      debugPrint('Map move failed: $e');
      setState(() {
        _lat = loc.latitude;
        _lng = loc.longitude;
      });
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
          suffixIcon: ValueListenableBuilder<String>(
              valueListenable: _searchTextNotifier,
              builder: (context, text, _) {
                return text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 20),
                        onPressed: () {
                          _searchController.clear();
                          _searchTextNotifier.value = '';
                          _debounceTimer?.cancel();
                          _lastFetchLat = null;
                          _fetchPlacesForViewport();
                        },
                      )
                    : const SizedBox.shrink();
              },
            ),
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
          // No-op: suffix icon is driven by _searchTextNotifier via controller listener.
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

  String? _getCategoryName(int categoryId) {
    for (final c in context.read<PlaceProvider>().categories) {
      if (c.id == categoryId) return c.name;
    }
    return null;
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
                      AdInlineBanner(adContext: 'explore', persistent: true),
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
    // Priority: viewportPlaces (from bbox API) > nepalPlaces (offline/cold) > places (legacy)
    final vp = _getViewportBounds();
    // Only trust the bbox result while it still covers what is on screen —
    // right after a pan it can be from the previous area, and using it would
    // make markers vanish until the debounced fetch lands.
    final useViewportPlaces = provider.viewportPlaces.isNotEmpty &&
        provider.viewportCovers(
          minLat: vp.minLat,
          maxLat: vp.maxLat,
          minLng: vp.minLng,
          maxLng: vp.maxLng,
        );

    final source = useViewportPlaces
        ? provider.viewportPlaces
        : provider.nepalPlaces.isNotEmpty
            ? provider.nepalPlaces
            : provider.places;

    final filtered = _applyPlaceFilters(source);
    if (filtered.isEmpty) return filtered;
    if (useViewportPlaces) {
      // bbox API already returned only viewport places — no extra filter needed
      return filtered;
    }

    // Fallback: client-side viewport filter for nepalPlaces/places
    const margin = 0.15;
    return filtered
        .where((p) =>
            p.latitude >= vp.minLat - margin &&
            p.latitude <= vp.maxLat + margin &&
            p.longitude >= vp.minLng - margin &&
            p.longitude <= vp.maxLng + margin)
        .toList();
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
    // Zooming into a cluster is a deliberate look elsewhere — same rule as a
    // place tap: give up follow/compass so the glide is not fought back.
    _exitFollowModes();
    try {
      _smoothMoveTo(
        LatLng(lat, lng),
        duration: const Duration(milliseconds: 350),
        targetZoom: math.min(_currentZoom + 3, 15.0),
        curve: Curves.easeInOut,
      );
    } catch (e) {
      debugPrint('Cluster zoom failed: $e');
    }
  }

  /// Plain circular category pin (no label) — shared by the cluster and the
  /// high-zoom paths.
  Widget _buildPinChild(PlaceModel place, double markerSize, bool isSelected) {
    final markerColor = _getCategoryColor(place.category);
    final pinHeight = markerSize * 1.4;
    return GestureDetector(
      onTap: () => _onPlaceTap(place),
      onDoubleTap: () => _navigateToDetails(place),
      child: SizedBox(
        width: markerSize,
        height: pinHeight,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.topCenter,
          children: [
            // Pin body (circle with icon)
            Positioned(
              top: 0,
              child: Container(
                width: markerSize,
                height: markerSize,
                decoration: BoxDecoration(
                  color: markerColor,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: isSelected ? 3 : 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: markerColor.withOpacity(isSelected ? 0.6 : 0.3),
                      blurRadius: isSelected ? 10 : 4,
                      spreadRadius: isSelected ? 3 : 1,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    Icon(_getCategoryIcon(place.category), color: Colors.white, size: isSelected ? 20 : 14),
                    if (isSelected)
                      Positioned(
                        top: -2, right: -2,
                        child: Container(
                          width: 12, height: 12,
                          decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                          child: const Icon(Icons.check_circle, size: 9, color: AppTheme.successColor),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            // Pin point (triangle poking into map)
            Positioned(
              top: markerSize - 4,
              child: CustomPaint(
                size: Size(12, 10),
                painter: _PinPointPainter(color: markerColor),
              ),
            ),
            // Shadow dot on the ground
            Positioned(
              bottom: 0,
              child: Container(
                width: 10,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.15),
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Marker> _buildMarkers(List<PlaceModel> places) {
    final markers = <Marker>[];
    if (places.isEmpty) return markers;

    final camera = _mapController.camera;
    final viewport = MediaQuery.of(context).size;

    // ── Step 1: Screen-space grid clustering ────────────────────────────
    // Grid-cell spatial hash → adjacent-cell neighbor check → BFS connected
    // components. Handles transitive chains across cell boundaries.
    // The selected place is always excluded so it renders as an individual
    // marker and is never hidden inside a cluster badge.
    final radius = _markerGridCell;
    final selectedId = _selectedPlace?.id.toString();

    // 1a. Compute screen positions and assign grid cells
    final screenPts = <Offset>[];
    final cellMap = <String, List<int>>{};
    for (var i = 0; i < places.length; i++) {
      final pt = camera.latLngToScreenPoint(
        LatLng(places[i].latitude, places[i].longitude),
      );
      screenPts.add(Offset(pt.x, pt.y));
      final cx = (pt.x / radius).floor();
      final cy = (pt.y / radius).floor();
      cellMap.putIfAbsent('$cx,$cy', () => []).add(i);
    }

    // 1b. Build neighbor graph — for each place, find all places within
    //     `radius` px in the same cell or 8 adjacent cells.
    final neighbors = <int, Set<int>>{};
    for (var i = 0; i < places.length; i++) {
      neighbors[i] = {};
    }
    for (final entry in cellMap.entries) {
      final parts = entry.key.split(',');
      final cx = int.parse(parts[0]);
      final cy = int.parse(parts[1]);

      // Collect candidates from this cell + 8 adjacent cells
      final candidateIdxs = <int>[];
      for (var dx = -1; dx <= 1; dx++) {
        for (var dy = -1; dy <= 1; dy++) {
          final adj = cellMap['${cx + dx},${cy + dy}'];
          if (adj != null) candidateIdxs.addAll(adj);
        }
      }

      for (final i in entry.value) {
        // Skip selected place — never cluster it
        if (places[i].id.toString() == selectedId) continue;
        final pi = screenPts[i];
        for (final j in candidateIdxs) {
          if (i == j) continue;
          if (places[j].id.toString() == selectedId) continue;
          final dist = (pi - screenPts[j]).distance;
          if (dist < radius) {
            neighbors[i]!.add(j);
            neighbors[j]!.add(i);
          }
        }
      }
    }

    // 1c. BFS connected components
    final used = <bool>[for (final _ in places) false];
    final individualPlaces = <PlaceModel>[];
    final clusterGroups = <List<PlaceModel>>[];

    for (var i = 0; i < places.length; i++) {
      if (used[i]) continue;
      // Selected place always goes to individual (no neighbors by design)
      if (places[i].id.toString() == selectedId) {
        used[i] = true;
        individualPlaces.add(places[i]);
        continue;
      }
      final queue = [i];
      used[i] = true;
      final group = <int>[];
      while (queue.isNotEmpty) {
        final curr = queue.removeLast();
        group.add(curr);
        for (final nb in neighbors[curr]!) {
          if (!used[nb]) {
            used[nb] = true;
            queue.add(nb);
          }
        }
      }
      if (group.length == 1) {
        individualPlaces.add(places[group[0]]);
      } else {
        clusterGroups.add(group.map((idx) => places[idx]).toList());
      }
    }

    // ── Step 2: Cluster badges (no labels) ─────────────────────────────
    for (final group in clusterGroups) {
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
    }

    // ── Step 3: Label collision for individual markers only ─────────────
    final showAddress = _currentZoom >= 16;
    final placesHash = Object.hashAll(individualPlaces.map((p) => p.id));
    final stateKey = '${_currentZoom}|${camera.center.latitude}|${camera.center.longitude}|${camera.rotation}|$selectedId|$placesHash';

    if (stateKey != _lastLabelStateKey || placesHash != _lastPlacesHash) {
      _lastLabelAssignments = _computeLabelAssignments(
        individualPlaces, showAddress, camera, viewport,
      );
      _lastLabelStateKey = stateKey;
      _lastPlacesHash = placesHash;
    }

    // ── Step 4: Build individual marker widgets with labels ─────────────
    const markerSize = 32.0;
    for (int i = 0; i < individualPlaces.length; i++) {
      final place = individualPlaces[i];
      final assignment = _lastLabelAssignments[i];
      final isSelected = selectedId == place.id.toString();

      markers.add(Marker(
        point: LatLng(place.latitude, place.longitude),
        width: markerSize,
        height: markerSize * 1.4,
        alignment: const Alignment(0, 0.7),
        rotate: true,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            _buildPinChild(place, markerSize, isSelected),
            if (assignment.side != null)
              _buildLabel(place, assignment.side!, assignment.labelWidth, markerSize, showAddress),
          ],
        ),
      ));
    }

    return markers;
  }

  Widget _buildLabel(PlaceModel place, _LabelSide side, double labelWidth, double markerSize, bool showAddress) {
    final labelH = showAddress && place.address != null ? 42.0 : 24.0;
    final m2 = markerSize / 2;
    const gap = 6.0; // label-to-marker gap

    double left, right, top, bottom;
    switch (side) {
      case _LabelSide.right:
        left = markerSize + gap;
        top = m2 - labelH / 2;
        right = double.infinity;
        bottom = double.infinity;
      case _LabelSide.left:
        right = markerSize + gap;
        top = m2 - labelH / 2;
        left = double.infinity;
        bottom = double.infinity;
      case _LabelSide.top:
        left = m2 - labelWidth / 2;
        bottom = markerSize + gap;
        top = double.infinity;
        right = double.infinity;
      case _LabelSide.bottom:
        left = m2 - labelWidth / 2;
        top = markerSize + gap;
        right = double.infinity;
        bottom = double.infinity;
      case _LabelSide.topRight:
        left = markerSize / 2 + gap;
        bottom = markerSize / 2 + gap;
        top = double.infinity;
        right = double.infinity;
      case _LabelSide.topLeft:
        right = markerSize / 2 + gap;
        bottom = markerSize / 2 + gap;
        left = double.infinity;
        top = double.infinity;
      case _LabelSide.bottomRight:
        left = markerSize / 2 + gap;
        top = markerSize / 2 + gap;
        right = double.infinity;
        bottom = double.infinity;
      case _LabelSide.bottomLeft:
        right = markerSize / 2 + gap;
        top = markerSize / 2 + gap;
        left = double.infinity;
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
    List<PlaceModel> places, bool showAddress,
    MapCamera camera, Size viewport,
  ) {
    // ── Phase 1: Build label info for all places ──────────────────────
    final infos = <_LabelInfo>[];
    const markerSize = 32.0;
    for (final place in places) {
      final isSelected = _selectedPlace?.id.toString() == place.id.toString();
      final isFeatured = place.isFeatured;
      // Labels appear much earlier than they used to: everything named from
      // zoom 13, the important (featured / selected) ones from zoom 11.
      final zoom = _currentZoom;
      final showName = zoom >= 13 || (zoom >= 11 && (isFeatured || isSelected));

      if (!showName) {
        infos.add(_LabelInfo(placeId: place.id, showLabel: false, markerSize: markerSize));
        continue;
      }

      final pt = camera.latLngToScreenPoint(LatLng(place.latitude, place.longitude));
      final labelW = _measureLabelWidth(place);
      final labelH = (showAddress && place.address != null) ? 42.0 : 24.0;

      infos.add(_LabelInfo(
        placeId: place.id,
        showLabel: true,
        markerSize: markerSize,
        screenPt: Offset(pt.x, pt.y),
        labelWidth: labelW,
        labelHeight: labelH,
      ));
    }

    // ── Phase 2: Assign priority scores (deterministic) ───────────────
    final viewCenter = Offset(viewport.width / 2, viewport.height / 2);
    for (final info in infos) {
      if (!info.showLabel) continue;
      final place = places.firstWhere((p) => p.id.toString() == info.placeId.toString());
      final isSelected = _selectedPlace?.id.toString() == info.placeId.toString();
      double priority = 0;
      if (isSelected) priority += 10000;
      if (place.isFeatured) priority += 5000;
      priority += _categoryPriority(place.category) * 100;
      // Screen-center distance tiebreaker (closer = higher priority)
      final distFromCenter = (info.screenPt - viewCenter).distance;
      final maxDist = viewport.shortestSide / 2;
      priority += (1.0 - (distFromCenter / maxDist).clamp(0.0, 1.0)) * 50;
      // Deterministic tiebreaker from place ID
      priority += (info.placeId.hashCode & 0xFFFF) / 65536.0;
      info.priority = priority;
    }

    // ── Phase 3: Sort by priority (highest first) ─────────────────────
    final labelledInfos = infos.where((i) => i.showLabel).toList();
    labelledInfos.sort((a, b) => b.priority.compareTo(a.priority));

    // ── Phase 4: Zoom-based label cap ─────────────────────────────────
    final maxLabels = _maxLabelsForZoom(_currentZoom);
    final cappedInfos = labelledInfos.take(maxLabels).toList();
    final hiddenInfos = labelledInfos.skip(maxLabels).toList();
    for (final info in hiddenInfos) {
      info.side = null;
    }

    // ── Phase 5: Priority-first placement with global collision ───────
    final placedRects = <Rect>[];
    final placedMarkerRects = <Rect>[];

    // Pre-compute marker rectangles for all visible places
    for (final info in labelledInfos) {
      placedMarkerRects.add(Rect.fromCenter(
        center: info.screenPt,
        width: info.markerSize + 12,
        height: info.markerSize + 12,
      ));
    }

    for (final info in cappedInfos) {
      final pt = info.screenPt;
      final r = info.markerSize / 2;
      final lw = info.labelWidth;
      final lh = info.labelHeight;
      const offset = 7.0; // label-to-marker offset

      // 8 candidate positions in screen coordinates
      final candidates = <_LabelSide, Rect>{
        _LabelSide.right: Rect.fromLTWH(pt.dx + r + offset, pt.dy - lh / 2, lw, lh),
        _LabelSide.left: Rect.fromLTWH(pt.dx - r - offset - lw, pt.dy - lh / 2, lw, lh),
        _LabelSide.top: Rect.fromLTWH(pt.dx - lw / 2, pt.dy - r - offset - lh, lw, lh),
        _LabelSide.bottom: Rect.fromLTWH(pt.dx - lw / 2, pt.dy + r + offset, lw, lh),
        _LabelSide.topRight: Rect.fromLTWH(pt.dx + r / 2 + offset, pt.dy - r - offset - lh, lw, lh),
        _LabelSide.topLeft: Rect.fromLTWH(pt.dx - r / 2 - offset - lw, pt.dy - r - offset - lh, lw, lh),
        _LabelSide.bottomRight: Rect.fromLTWH(pt.dx + r / 2 + offset, pt.dy + r + offset, lw, lh),
        _LabelSide.bottomLeft: Rect.fromLTWH(pt.dx - r / 2 - offset - lw, pt.dy + r + offset, lw, lh),
      };

      final isSelected = _selectedPlace?.id.toString() == info.placeId.toString();

      // Stability: try to keep previous side if still HARD-valid
      final prev = _lastLabelAssignments.where((a) => a.placeId == info.placeId).firstOrNull;
      if (prev?.side != null && candidates.containsKey(prev!.side)) {
        final prevRect = candidates[prev.side!]!;
        final clampedPrev = _clampToViewport(prevRect, viewport);
        if (_isValidLabelPosition(clampedPrev, viewport, placedRects, placedMarkerRects, pt)) {
          info.side = prev.side;
          placedRects.add(clampedPrev);
          continue;
        }
      }

      // Score all 8 positions — HARD collision reject, then soft score
      _LabelSide? bestSide;
      double bestScore = double.infinity;
      Rect? bestClamped;

      for (final entry in candidates.entries) {
        final side = entry.key;
        final clamped = _clampToViewport(entry.value, viewport);

        // HARD CONSTRAINT: label-label collision → reject immediately
        bool labelCollision = false;
        for (final placed in placedRects) {
          if (_rectsOverlap(clamped, placed, 10)) {
            labelCollision = true;
            break;
          }
        }
        if (labelCollision) continue;

        // HARD CONSTRAINT: label-marker collision → reject immediately
        bool markerCollision = false;
        for (final otherMarker in placedMarkerRects) {
          if (otherMarker.center == pt) continue;
          if (_rectsOverlap(clamped, otherMarker, 6)) {
            markerCollision = true;
            break;
          }
        }
        if (markerCollision) continue;

        // Soft scoring among collision-free candidates only
        double score = _sideRank(side);

        // Off-screen penalty (soft — still valid, just less preferred)
        if (clamped.left < 0 || clamped.right > viewport.width ||
            clamped.top < 0 || clamped.bottom > viewport.height) {
          score += 500;
        }

        // Stability bonus: prefer previous side
        if (prev?.side != null && side == prev!.side) {
          score -= 35;
        }

        if (score < bestScore) {
          bestScore = score;
          bestSide = side;
          bestClamped = clamped;
        }
      }

      if (isSelected) {
        // Selected place: always place. If all 8 candidates have hard
        // collisions, force the one with the lowest soft score.
        if (bestSide != null) {
          info.side = bestSide;
          placedRects.add(bestClamped!);
        } else {
          // All candidates collide — find least-bad one
          _forceBestCandidate(candidates, viewport, placedRects, placedMarkerRects, pt, info);
        }
      } else if (bestSide != null) {
        // Normal label: only place if a collision-free candidate was found
        info.side = bestSide;
        placedRects.add(bestClamped!);
      } else {
        // No collision-free candidate → hide
        info.side = null;
      }
    }

    // ── Phase 6: Map assignments back to original order ───────────────
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

  /// Maximum number of labels to show at each zoom level.
  ///
  /// Non-zero from zoom 10 so a mid-zoom view is never a wall of anonymous
  /// pins; the numbers stay small enough that the collision pass has room.
  int _maxLabelsForZoom(double zoom) {
    if (zoom >= 17) return 50;
    if (zoom >= 16) return 40;
    if (zoom >= 15) return 30;
    if (zoom >= 14) return 20;
    if (zoom >= 13) return 12;
    if (zoom >= 11) return 5;
    if (zoom >= 10) return 3;
    return 0;
  }

  /// Category importance score for label priority (higher = more important).
  int _categoryPriority(String? category) {
    switch (category?.toLowerCase()) {
      case 'hospital':
      case 'clinic':
      case 'health':
        return 9;
      case 'transport':
      case 'bus':
      case 'airport':
        return 8;
      case 'hotel':
      case 'accommodation':
        return 7;
      case 'restaurant':
      case 'food':
        return 6;
      case 'attraction':
      case 'monument':
      case 'museum':
        return 5;
      case 'shopping':
        return 4;
      case 'bank':
      case 'atm':
        return 3;
      case 'nature':
      case 'park':
        return 2;
      default:
        return 1;
    }
  }

  /// Check if a label position is valid (no overlap with placed labels or markers).
  bool _isValidLabelPosition(Rect rect, Size viewport, List<Rect> placedLabels,
      List<Rect> placedMarkers, Offset selfPt) {
    if (rect.left < -10 || rect.right > viewport.width + 10 ||
        rect.top < -10 || rect.bottom > viewport.height + 10) {
      return false;
    }
    for (final placed in placedLabels) {
      if (_rectsOverlap(rect, placed, 10)) return false;
    }
    for (final marker in placedMarkers) {
      if (marker.center == selfPt) continue;
      if (_rectsOverlap(rect, marker, 6)) return false;
    }
    return true;
  }

  /// Hard collision check: do two rectangles overlap when each is inflated
  /// by [padding] pixels? Used as a HARD constraint — overlapping candidates
  /// are rejected, not merely penalised.
  static bool _rectsOverlap(Rect a, Rect b, double padding) {
    return a.inflate(padding).overlaps(b.inflate(padding));
  }

  /// Force the best available candidate for the selected place when ALL 8
  /// candidates have hard collisions. Picks the candidate with the fewest
  /// label-label overlaps (minimum damage).
  void _forceBestCandidate(
    Map<_LabelSide, Rect> candidates, Size viewport,
    List<Rect> placedLabels, List<Rect> placedMarkers,
    Offset selfPt, _LabelInfo info,
  ) {
    _LabelSide? bestSide;
    int fewestOverlaps = 999;
    Rect? bestClamped;

    for (final entry in candidates.entries) {
      final clamped = _clampToViewport(entry.value, viewport);
      int overlapCount = 0;
      for (final placed in placedLabels) {
        if (_rectsOverlap(clamped, placed, 10)) overlapCount++;
      }
      for (final marker in placedMarkers) {
        if (marker.center == selfPt) continue;
        if (_rectsOverlap(clamped, marker, 6)) overlapCount++;
      }
      if (overlapCount < fewestOverlaps) {
        fewestOverlaps = overlapCount;
        bestSide = entry.key;
        bestClamped = clamped;
      }
    }
    if (bestSide != null) {
      info.side = bestSide;
      placedLabels.add(bestClamped!);
    }
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
      case _LabelSide.topRight: return 4;
      case _LabelSide.topLeft: return 5;
      case _LabelSide.bottomRight: return 6;
      case _LabelSide.bottomLeft: return 7;
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
      case 'blood_bank':
      case 'blood bank':
        return Icons.bloodtype;
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
      case 'blood_bank':
      case 'blood bank':
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

  void _showSosInfoSheet(BuildContext context, dynamic sos) {
    final emergencyLabels = {
      'medical': 'Medical Emergency',
      'accident': 'Accident',
      'flood': 'Flood',
      'other': 'Emergency',
    };
    final duration = sos.durationSeconds ?? 0;
    final mins = duration ~/ 60;
    final secs = duration % 60;
    final durationText = '${mins.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppTheme.errorColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.sos, color: AppTheme.errorColor, size: 24),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Text(
                    'SOS Nearby',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _infoTile(Icons.location_on, 'Approx. ${sos.distanceKm?.toStringAsFixed(0) ?? '?'} km away'),
            const SizedBox(height: 8),
            _infoTile(Icons.timer, 'Active for $durationText'),
            const SizedBox(height: 8),
            _infoTile(Icons.warning, emergencyLabels[sos.emergencyType] ?? 'Emergency'),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  final url = Uri.parse(
                    'https://www.google.com/maps/dir/?api=1&destination=${sos.latitude},${sos.longitude}',
                  );
                  launchUrl(url);
                },
                icon: const Icon(Icons.directions, color: Colors.white),
                label: const Text('Get Directions'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoTile(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 18, color: Colors.grey.shade600),
        const SizedBox(width: 10),
        Text(text, style: TextStyle(color: Colors.grey.shade700, fontSize: 14)),
      ],
    );
  }
}

enum _LabelSide { right, left, top, bottom, topLeft, topRight, bottomLeft, bottomRight }

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
  double labelHeight;
  double priority = 0;

  _LabelInfo({
    required this.placeId,
    required this.showLabel,
    required this.markerSize,
    this.screenPt = Offset.zero,
    this.labelWidth = 0,
    this.labelHeight = 24.0,
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

/// Draws the small triangle/point at the bottom of a pin marker,
/// making it look like it's poked into the map surface.
class _PinPointPainter extends CustomPainter {
  final Color color;
  const _PinPointPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;
    final path = ui.Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width / 2, size.height)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _PinPointPainter old) => old.color != color;
}
