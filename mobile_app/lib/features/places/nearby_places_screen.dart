import 'dart:async';
import "../../core/services/localization_service.dart";
import 'dart:convert';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:geolocator/geolocator.dart';
import '../../config/constants/app_constants.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../core/models/place.dart';
import '../../core/models/ad_campaign.dart';
import '../../providers/place_provider.dart';
import '../../providers/ad_provider.dart';
import '../../widgets/ad_cards.dart';
import '../../widgets/ad_inline_banner.dart';
import 'place_details_screen.dart';
import 'add_place_screen.dart';
import 'filter_places_sheet.dart';
import '../../core/widgets/shimmer_loading.dart';

class NearbyPlacesScreen extends StatefulWidget {
  final double? destinationLat;
  final double? destinationLng;
  final String? destinationName;

  const NearbyPlacesScreen({
    super.key,
    this.destinationLat,
    this.destinationLng,
    this.destinationName,
  });

  factory NearbyPlacesScreen.withDestination({
    Key? key,
    required double destinationLat,
    required double destinationLng,
    String? destinationName,
  }) {
    return NearbyPlacesScreen(
      key: key,
      destinationLat: destinationLat,
      destinationLng: destinationLng,
      destinationName: destinationName,
    );
  }

  @override
  State<NearbyPlacesScreen> createState() => _NearbyPlacesScreenState();
}

class _NearbyPlacesScreenState extends State<NearbyPlacesScreen> {
  final LocationService _locationService = LocationService();
  final MapController _mapController = MapController();
  final DraggableScrollableController _sheetController = DraggableScrollableController();
  final TextEditingController _searchController = TextEditingController();

  double? _lat;
  double? _lng;
  double _searchRadiusKm = AppConstants.defaultRadiusKm;
  bool _isSatellite = false;
  double _currentZoom = AppConstants.defaultMapZoom;
  PlaceModel? _selectedPlace;
  bool _isSearching = false;

  // Compass rotation (degrees, clockwise positive)
  final ValueNotifier<double> _rotationNotifier = ValueNotifier<double>(0);

  // Current location tracking
  LatLng? _currentLocation;
  StreamSubscription<Position>? _positionSub;

  // Route / Directions (multi-route support)
  List<Map<String, dynamic>> _routes = [];
  bool _isLoadingRoute = false;
  bool _showFeaturedOnly = false;
  PlaceFilter? _activeFilter;

  // Destination (from sponsors/store)
  double? _destinationLat;
  double? _destinationLng;
  String? _destinationName;

  @override
  void initState() {
    super.initState();
    _destinationLat = widget.destinationLat;
    _destinationLng = widget.destinationLng;
    _destinationName = widget.destinationName;
    WidgetsBinding.instance.addPostFrameCallback((_) => _initData());
  }

  @override
  void dispose() {
    _positionSub?.cancel();
    _rotationNotifier.dispose();
    _sheetController.dispose();
    _searchController.dispose();
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _initData() async {
    final provider = context.read<PlaceProvider>();
    await provider.fetchCategories();

    final loc = await _locationService.getCurrentLocation();
    if (loc != null && mounted) {
      setState(() {
        _lat = loc.latitude;
        _lng = loc.longitude;
        _currentLocation = LatLng(loc.latitude, loc.longitude);
      });
      _startTracking();
    } else if (mounted) {
      setState(() {
        _lat = null;
        _lng = null;
      });
    }

if (mounted && _lat != null && _lng != null) {
        provider.fetchNearbyPlaces(lat: _lat!, lng: _lng!, radiusKm: _searchRadiusKm);
        provider.fetchFeaturedPlaces(lat: _lat, lng: _lng);
        unawaited(context.read<AdProvider>().fetchActiveAds(adContext: 'nearby', limit: 6));
        WidgetsBinding.instance.addPostFrameCallback((_) async {
          await Future.delayed(const Duration(milliseconds: 200));
          if (mounted && _lat != null && _lng != null) {
            try {
              _mapController.move(LatLng(_lat!, _lng!), 14.0);
            } catch (e) {
              debugPrint('Failed to move map: $e');
            }
          }
        });
      // Auto-fetch route to destination if set
      if (_destinationLat != null && _destinationLng != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _fetchDestinationRoute();
        });
      }
    }
  }

  void _doSearch(String query) {
    if (_lat == null || _lng == null) return;
    final provider = context.read<PlaceProvider>();
    provider.fetchNearbyPlaces(
      lat: _lat!,
      lng: _lng!,
      radiusKm: _searchRadiusKm,
      categoryId: provider.selectedCategoryId == 0 ? null : provider.selectedCategoryId,
      search: query.isNotEmpty ? query : null,
    );
  }

  void _onPlaceTap(PlaceModel place) {
    setState(() {
      _selectedPlace = place;
    });
    try {
      _mapController.move(LatLng(place.latitude, place.longitude), 15.0);
      _sheetController.animateTo(
        0.28,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeOut,
      );
    } catch (e) {
      debugPrint('MapController move failed: $e');
    }
  }

  void _navigateToDetails(PlaceModel place) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PlaceDetailsScreen(place: place.toPlace()),
      ),
    );
  }

  void _startTracking() {
    _positionSub?.cancel();
    _positionSub = _locationService
        .getPositionStream(intervalMs: 3000, distanceFilterM: 5)
        .listen((pos) {
      if (mounted) {
        setState(() {
          _currentLocation = LatLng(pos.latitude, pos.longitude);
        });
      }
    });
  }

  Future<void> _getDirections(PlaceModel place) async {
    final originLat = _currentLocation?.latitude ?? _lat;
    final originLng = _currentLocation?.longitude ?? _lng;
    if (originLat == null || originLng == null) return;
    setState(() => _isLoadingRoute = true);
    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '$originLng,$originLat;${place.longitude},${place.latitude}'
        '?geometries=geojson&overview=full&steps=true&alternatives=3',
      );
      final response = await http.get(url, headers: {
        'User-Agent': 'NepalSmartTravel/1.0',
      });
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['code'] == 'Ok') {
          final routes = data['routes'] as List;
          final parsed = <Map<String, dynamic>>[];
          for (int i = 0; i < routes.length; i++) {
            final r = routes[i];
            final coords = (r['geometry']['coordinates'] as List)
                .map((c) => LatLng(c[1], c[0]))
                .toList();
            if (coords.length > 1) {
              parsed.add({
                'points': coords,
                'distance': (r['distance'] as num) / 1000,
                'duration': (r['duration'] as num) / 60,
              });
            }
          }
          if (parsed.isNotEmpty) {
            setState(() => _routes = parsed);
            final allPoints = parsed.expand((r) => r['points'] as List<LatLng>).toList();
            final bounds = _latLngBoundsFromPoints(allPoints);
            _mapController.fitCamera(
              CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(60)),
            );
          } else {
            _showRouteError('No valid routes found');
          }
        } else {
          _showRouteError('Route server error: ${data['message'] ?? 'unknown'}');
        }
      } else {
        _showRouteError('Server returned ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('Route error: $e');
      _showRouteError('Could not fetch route. Please try again.');
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
    setState(() => _routes = []);
  }

  Future<void> _fetchDestinationRoute() async {
    if (_destinationLat == null || _destinationLng == null) return;
    final originLat = _currentLocation?.latitude ?? _lat;
    final originLng = _currentLocation?.longitude ?? _lng;
    if (originLat == null || originLng == null) return;
    setState(() => _isLoadingRoute = true);
    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '$originLng,$originLat;$_destinationLng,$_destinationLat'
        '?geometries=geojson&overview=full&steps=true&alternatives=3',
      );
      final response = await http.get(url, headers: {
        'User-Agent': 'NepalSmartTravel/1.0',
      });
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['code'] == 'Ok') {
          final routes = data['routes'] as List;
          final parsed = <Map<String, dynamic>>[];
          for (int i = 0; i < routes.length; i++) {
            final r = routes[i];
            final coords = (r['geometry']['coordinates'] as List)
                .map((c) => LatLng(c[1], c[0]))
                .toList();
            if (coords.length > 1) {
              parsed.add({
                'points': coords,
                'distance': (r['distance'] as num) / 1000,
                'duration': (r['duration'] as num) / 60,
              });
            }
          }
          if (parsed.isNotEmpty) {
            setState(() => _routes = parsed);
            final allPoints = parsed.expand((r) => r['points'] as List<LatLng>).toList()
              ..add(LatLng(_destinationLat!, _destinationLng!))
              ..add(LatLng(originLat, originLng));
            final bounds = _latLngBoundsFromPoints(allPoints);
            _mapController.fitCamera(
              CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(80)),
            );
          } else {
            _showRouteError('No valid routes found');
          }
        } else {
          _showRouteError('Route server error: ${data['message'] ?? 'unknown'}');
        }
      } else {
        _showRouteError('Server returned ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('Destination route error: $e');
      _showRouteError('Could not fetch route. Please try again.');
    }
    if (mounted) setState(() => _isLoadingRoute = false);
  }

  void _openInMaps(PlaceModel place) async {
    final originLat = _currentLocation?.latitude ?? _lat;
    final originLng = _currentLocation?.longitude ?? _lng;
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1'
      '&origin=$originLat,$originLng'
      '&destination=${place.latitude},${place.longitude}'
      '&travelmode=driving',
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
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
          if (_lat != null && _lng != null) {
            final provider = context.read<PlaceProvider>();
            provider.fetchNearbyPlaces(
              lat: _lat!,
              lng: _lng!,
              radiusKm: filters.radiusKm ?? _searchRadiusKm,
              categoryId: filters.categoryId,
              search: _searchController.text.isNotEmpty
                  ? _searchController.text
                  : null,
            );
          }
        },
      ),
    );
  }

  List<PlaceModel> _applyPlaceFilters(List<PlaceModel> places) {
    var result = places;
    final f = _activeFilter;
    final showFeatured = _showFeaturedOnly || (f?.onlyFeatured ?? false);
    if (showFeatured) {
      result = result.where((p) => p.isFeatured).toList();
    }
    if (f?.onlyVerified == true) {
      result = result.where((p) => p.isVerified).toList();
    }
    return result;
  }

  Future<void> _onMyLocationTap() async {
    final loc = _currentLocation;
    if (loc != null) {
      try {
        _mapController.move(loc, 15.0);
      } catch (e) {
        debugPrint('Map move failed: $e');
      }
      return;
    }
    if (_lat != null && _lng != null) {
      try {
        _mapController.move(LatLng(_lat!, _lng!), 15.0);
      } catch (e) {
        debugPrint('Map move failed: $e');
      }
      return;
    }
    final fresh = await _locationService.getCurrentLocation();
    if (!mounted) return;
    if (fresh == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
              'Could not get your location. Please enable GPS and allow location permission, then try again.'),
          backgroundColor: AppTheme.errorColor,
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: 4),
        ),
      );
      return;
    }
    setState(() {
      _lat = fresh.latitude;
      _lng = fresh.longitude;
      _currentLocation = LatLng(fresh.latitude, fresh.longitude);
    });
    _startTracking();
    try {
      _mapController.move(
        LatLng(fresh.latitude, fresh.longitude),
        15.0,
      );
    } catch (e) {
      debugPrint('Map move failed: $e');
    }
  }

  Future<void> _retryLocation() async {
    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;
    if (loc == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
              'Could not get your location. Please enable GPS and allow location permission, then try again.'),
          backgroundColor: AppTheme.errorColor,
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: 4),
        ),
      );
      return;
    }
    setState(() {
      _lat = loc.latitude;
      _lng = loc.longitude;
      _currentLocation = LatLng(loc.latitude, loc.longitude);
    });
    _startTracking();
    final provider = context.read<PlaceProvider>();
    provider.fetchNearbyPlaces(lat: _lat!, lng: _lng!, radiusKm: _searchRadiusKm);
    provider.fetchFeaturedPlaces(lat: _lat, lng: _lng);
    try {
      _mapController.move(LatLng(_lat!, _lng!), 14.0);
    } catch (e) {
      debugPrint('Map move failed: $e');
    }
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
        return AppTheme.markerHotel;
      case 'restaurant':
      case 'food':
      case 'cafe':
        return AppTheme.markerFood;
      case 'emergency':
      case 'hospital':
      case 'clinic':
        return AppTheme.markerEmergency;
      case 'transport':
      case 'bus':
      case 'airport':
        return AppTheme.markerTransport;
      case 'attraction':
      case 'landmark':
      case 'sightseeing':
        return AppTheme.markerTourist;
      case 'activity':
      case 'adventure':
        return AppTheme.markerActivity;
      default:
        return AppTheme.markerUtility;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          _buildMap(),

          SafeArea(
            child: Container(
              color: Colors.transparent,
              height: 0,
            ),
          ),
          Positioned(
            top: MediaQuery.of(context).padding.top + 4,
            left: 0,
            right: 0,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildSearchBar(),
                const SizedBox(height: 6),
                _buildCategoryChips(),
              ],
            ),
          ),

          // Compass (N) indicator - top right, shows north direction
          Positioned(
            top: MediaQuery.of(context).padding.top + 62,
            right: 16,
            child: _buildCompass(),
          ),

          Positioned(
            left: 16,
            bottom: 140,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _mapControlButton(
                  icon: Icons.filter_list,
                  color: AppTheme.primaryColor,
                  iconColor: Colors.white,
                  onTap: _onFilterTap,
                ),
                const SizedBox(height: 4),
                _mapControlButton(
                  icon: Icons.add_location,
                  color: AppTheme.secondaryColor,
                  iconColor: Colors.white,
                  onTap: _onAddPlaceTap,
                ),
                const SizedBox(height: 4),
              ],
            ),
          ),
          Positioned(
            right: 16,
            bottom: 140,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _mapControlButton(
                  icon: _isSatellite ? Icons.map : Icons.satellite,
                  color: _isSatellite ? Colors.black87 : Colors.white,
                  iconColor: _isSatellite ? Colors.white70 : AppTheme.textPrimary,
                  onTap: () => setState(() => _isSatellite = !_isSatellite),
                ),
                const SizedBox(height: 4),
                _mapControlButton(
                  icon: Icons.add,
                  onTap: () {
                    final z = _mapController.camera.zoom;
                    _mapController.move(
                      _mapController.camera.center,
                      (z + 1).clamp(AppConstants.minMapZoom, AppConstants.maxMapZoom),
                    );
                  },
                ),
                const SizedBox(height: 4),
                _mapControlButton(
                  icon: Icons.remove,
                  onTap: () {
                    final z = _mapController.camera.zoom;
                    _mapController.move(
                      _mapController.camera.center,
                      (z - 1).clamp(AppConstants.minMapZoom, AppConstants.maxMapZoom),
                    );
                  },
                ),
                const SizedBox(height: 4),
                _mapControlButton(
                  icon: Icons.my_location,
                  color: AppTheme.primaryColor,
                  iconColor: Colors.white,
                  onTap: _onMyLocationTap,
                ),
                const SizedBox(height: 4),
                _mapControlButton(
                  icon: Icons.radar,
                  onTap: () => _showRadiusPicker(),
                ),
              ],
            ),
          ),

          _buildLoadingOverlay(),

          _buildBottomSheet(),

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
                        children: const [
                          Icon(Icons.my_location, color: AppTheme.errorColor),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'Waiting for your current location... Please enable GPS and allow location permission.',
                              style: TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                            ),
                          ),
                        ],
                      ),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(
                          onPressed: _retryLocation,
                          icon: const Icon(Icons.refresh, size: 16),
                          label: const Text('Try Again'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildMap() {
    return Consumer<PlaceProvider>(
      builder: (context, provider, _) {
        return FlutterMap(
          mapController: _mapController,
          options: MapOptions(
            initialCenter: LatLng(
              _lat ?? AppConstants.defaultLatitude,
              _lng ?? AppConstants.defaultLongitude,
            ),
            initialZoom: AppConstants.defaultMapZoom,
            minZoom: AppConstants.minMapZoom,
            maxZoom: AppConstants.maxMapZoom,
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
                _currentZoom = event.camera.zoom;
              }
            },
            onPositionChanged: (camera, hasGesture) {
              _rotationNotifier.value = camera.rotation;
            },
            onTap: (_, __) {
              setState(() {
                _selectedPlace = null;
              });
            },
          ),
          children: [
            if (_isSatellite) ...[
              TileLayer(
                urlTemplate: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                userAgentPackageName: 'np.com.nepalsmarttravel',
                maxZoom: 19,
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
                ),
              ),
            ] else
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'np.com.nepalsmarttravel',
                maxZoom: 19,
              ),
            if (_currentLocation != null)
              MarkerLayer(
                markers: [
                  _buildYouAreHereMarker(),
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
            if (_routes.isNotEmpty)
              PolylineLayer(
                polylines: [
                  for (int i = 0; i < _routes.length; i++)
                    Polyline(
                      points: _routes[i]['points'] as List<LatLng>,
                      color: i == 0
                          ? const Color(0xFF4285F4).withOpacity(0.85)
                          : Colors.grey.withOpacity(0.5),
                      strokeWidth: i == 0 ? 5 : 3,
                    ),
                ],
              ),
            MarkerLayer(
              markers: _buildMarkers(_applyPlaceFilters(provider.places)),
            ),
          ],
        );
      },
    );
  }

  Marker _buildYouAreHereMarker() {
    return Marker(
      point: _currentLocation!,
      width: 24,
      height: 24,
      alignment: Alignment.center,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.blue.withOpacity(0.25),
          shape: BoxShape.circle,
        ),
        child: Center(
          child: Container(
            width: 14,
            height: 14,
            decoration: const BoxDecoration(
              color: Colors.blue,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(color: Colors.black26, blurRadius: 4, spreadRadius: 1),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Material(
        elevation: 4,
        borderRadius: BorderRadius.circular(28),
        shadowColor: Colors.black26,
        child: TextField(
          controller: _searchController,
          decoration: InputDecoration(
            hintText: 'Search nearby places...',
            hintStyle: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textLg),
            prefixIcon: Icon(Icons.search, color: AppTheme.primaryColor, size: 22),
            suffixIcon: _searchController.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear, size: 20),
                    onPressed: () {
                      _searchController.clear();
                      setState(() {});
                      _doSearch('');
                    },
                  )
                : null,
            filled: true,
            fillColor: AppTheme.surfaceColor,
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
          style: const TextStyle(fontSize: AppTheme.textLg),
          textInputAction: TextInputAction.search,
          onChanged: (value) {
            setState(() {});
            _isSearching = value.isNotEmpty;
          },
          onSubmitted: (value) {
            _doSearch(value);
            FocusScope.of(context).unfocus();
          },
        ),
      ),
    );
  }

  Widget _buildCategoryChips() {
    return Consumer<PlaceProvider>(
      builder: (context, provider, _) {
        return SizedBox(
          height: 38,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            itemCount: provider.categories.length + 1,
            itemBuilder: (context, index) {
              if (index == 0) {
                return Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: ChoiceChip(
                    label: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.star, size: 13, color: _showFeaturedOnly ? Colors.white : const Color(0xFFFFA000)),
                        const SizedBox(width: 3),
                        Text('Featured', style: TextStyle(
                          fontSize: AppTheme.textSm,
                          fontWeight: FontWeight.w600,
                          color: _showFeaturedOnly ? AppTheme.surfaceColor : AppTheme.textPrimary,
                        )),
                      ],
                    ),
                    selected: _showFeaturedOnly,
                    selectedColor: const Color(0xFFFFA000),
                    backgroundColor: Colors.white,
                    side: BorderSide(
                      color: _showFeaturedOnly ? const Color(0xFFFFA000) : AppTheme.dividerColor,
                      width: 1,
                    ),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    visualDensity: VisualDensity.compact,
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    labelPadding: EdgeInsets.zero,
                    elevation: _showFeaturedOnly ? 2 : 0,
                    onSelected: (selected) {
                      setState(() => _showFeaturedOnly = selected);
                    },
                  ),
                );
              }
              final cat = provider.categories[index - 1];
              final isSelected = provider.selectedCategoryId == cat.id;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 3),
                child: ChoiceChip(
                  label: Text(
                    cat.name,
                    style: TextStyle(
                      fontSize: AppTheme.textSm,
                      fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                      color: isSelected ? AppTheme.surfaceColor : AppTheme.textPrimary,
                    ),
                  ),
                  selected: isSelected,
                  selectedColor: AppTheme.primaryColor,
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: isSelected ? AppTheme.primaryColor : AppTheme.dividerColor,
                    width: 1,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  visualDensity: VisualDensity.compact,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  labelPadding: EdgeInsets.zero,
                  elevation: isSelected ? 2 : 0,
                  pressElevation: 3,
                  shadowColor: AppTheme.primaryColor.withOpacity(0.3),
                  onSelected: (selected) {
                    provider.setCategory(cat.id);
                    setState(() {
                      _selectedPlace = null;
                      _showFeaturedOnly = false;
                    });
                    if (_lat != null && _lng != null) {
                      provider.fetchNearbyPlaces(
                        lat: _lat!,
                        lng: _lng!,
                        radiusKm: _searchRadiusKm,
                        categoryId: cat.id == 0 ? null : cat.id,
                        search: _searchController.text.isNotEmpty ? _searchController.text : null,
                      );
                    }
                    context.read<AdProvider>().fetchActiveAds(
                      adContext: adContextForCategory(cat.name),
                      category: adCategorySlugForCategory(cat.name),
                      limit: 6,
                    );
                  },
                ),
              );
            },
          ),
        );
      },
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

  Widget _buildLoadingOverlay() {
    return Consumer<PlaceProvider>(
      builder: (context, provider, _) {
        if (!provider.isLoading) return const SizedBox.shrink();
        return Positioned(
          top: 120,
          left: 0,
          right: 0,
          child: Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
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
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  SizedBox(width: 10),
                  Text('Finding places...', style: TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary)),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _mapControlButton({
    required IconData icon,
    required VoidCallback onTap,
    Color? color,
    Color? iconColor,
  }) {
    return Material(
      elevation: 3,
      color: color ?? AppTheme.surfaceColor,
      borderRadius: BorderRadius.circular(12),
      shadowColor: Colors.black26,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          child: Icon(
            icon,
            size: 22,
            color: iconColor ?? AppTheme.textPrimary,
          ),
        ),
      ),
    );
  }

  void _showRadiusPicker() {
    final provider = context.read<PlaceProvider>();
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: AppTheme.dividerColor,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Search Radius',
                        style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.w600),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppTheme.primaryColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Text(
                          '${_searchRadiusKm.toStringAsFixed(0)} km',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: AppTheme.primaryColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Slider.adaptive(
                    min: 1.0,
                    max: 50.0,
                    divisions: 49,
                    value: _searchRadiusKm,
                    activeColor: AppTheme.primaryColor,
                    label: '${_searchRadiusKm.toStringAsFixed(0)} km',
                    onChanged: (value) {
                      setModalState(() {
                        _searchRadiusKm = value;
                      });
                    },
                  ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('1 km', style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                      Text('50 km', style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);
                        if (_lat != null && _lng != null) {
                          provider.fetchNearbyPlaces(
                            lat: _lat!,
                            lng: _lng!,
                            radiusKm: _searchRadiusKm,
                            categoryId: provider.selectedCategoryId == 0 ? null : provider.selectedCategoryId,
                            search: _searchController.text.isNotEmpty ? _searchController.text : null,
                          );
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Apply', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600)),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildBottomSheet() {
    return Consumer<PlaceProvider>(
      builder: (context, provider, _) {
        return _buildBottomSheetContent(provider);
      },
    );
  }

  Widget _buildBottomSheetContent(PlaceProvider provider) {
    final displayPlaces = _applyPlaceFilters(provider.places);
    return DraggableScrollableSheet(
      controller: _sheetController,
      initialChildSize: 0.0,
      minChildSize: 0.0,
      maxChildSize: 0.55,
      builder: (context, scrollController) {
        return SafeArea(
          top: false,
          child: Container(
            decoration: const BoxDecoration(
              color: AppTheme.surfaceColor,
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 10,
                  offset: Offset(0, -2),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 12, bottom: 6),
                  child: Container(
                    width: 48,
                    height: 6,
                    decoration: BoxDecoration(
                      color: AppTheme.dividerColor,
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ),
                // Selected place preview when a marker is tapped
                if (_selectedPlace != null)
                  _buildSheetSelectedPreview(_selectedPlace!, () {
                    _navigateToDetails(_selectedPlace!);
                  }),
                // Destination preview when navigating to a sponsor
                if (_selectedPlace == null && _destinationName != null)
                  _buildDestinationPreview(),
                const Divider(height: 1, thickness: 1),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Nearby Places',
                        style: TextStyle(
                          fontSize: AppTheme.textXl,
                          fontWeight: FontWeight.w600,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                      Row(
                        children: [
                          if (displayPlaces.where((p) => p.isFeatured).isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: AppTheme.goldTick.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  '${displayPlaces.where((p) => p.isFeatured).length} featured',
                                  style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.secondaryColor, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ),
                          if (displayPlaces.isNotEmpty)
                            Text(
                              '${displayPlaces.length} found',
                              style: TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: provider.isLoading
                      ? _PlacesListShimmer()
                      : displayPlaces.isEmpty
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.map_outlined, size: 48, color: AppTheme.dividerColor),
                                  const SizedBox(height: 8),
                                  Text(
                                    _showFeaturedOnly ? 'No featured places' : 'No places found nearby',
                                    style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textBase),
                                  ),
                                ],
                              ),
                            )
                          : _AdPlacesList(
                              places: displayPlaces,
                              ads: context.watch<AdProvider>().placeAds,
                              scrollController: scrollController,
                              selectedPlaceId: _selectedPlace?.id,
                              onPlaceTap: _navigateToDetails,
                              buildPlaceItem: _buildPlaceListItem,
                            ),
                ),
              ],
            ),
          ),
        );
      },
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
                            if (place.distanceKm != null)
                              Text('${place.distanceKm!.toStringAsFixed(1)} km', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
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
                      label: Text(_isLoadingRoute ? 'Loading...' : 'Directions', style: const TextStyle(fontSize: 12)),
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
                      tooltip: 'Clear route',
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
                                  'Route ${i + 1}',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: isFirst ? Colors.white : AppTheme.textPrimary,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '· $dur min ($dist km)',
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
              width: 48, height: 48,
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
                  Text(_destinationName ?? 'Destination', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                  const SizedBox(height: 2),
                  Text('Tap Get Directions for route', style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            if (_routes.isEmpty)
              SizedBox(
                height: 36,
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.directions, size: 16),
                  label: Text(_isLoadingRoute ? '...' : 'Go'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE91E63),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                  ),
                  onPressed: _isLoadingRoute ? null : _fetchDestinationRoute,
                ),
              )
            else
              SizedBox(
                height: 36,
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.map, size: 16),
                  label: const Text('Open Maps'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF4285F4),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                  ),
                  onPressed: () async {
                    final originLat = _currentLocation?.latitude ?? _lat;
                    final originLng = _currentLocation?.longitude ?? _lng;
                    final uri = Uri.parse(
                      'https://www.google.com/maps/dir/?api=1'
                      '&origin=$originLat,$originLng'
                      '&destination=$_destinationLat,$_destinationLng'
                      '&travelmode=driving',
                    );
                    await launchUrl(uri, mode: LaunchMode.externalApplication);
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlaceListItem(PlaceModel place, bool isSelected) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: isSelected ? AppTheme.primaryColor.withOpacity(0.05) : AppTheme.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _onPlaceTap(place),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: place.isFeatured
                    ? AppTheme.goldTick.withOpacity(0.4)
                    : (isSelected ? AppTheme.primaryColor.withOpacity(0.3) : AppTheme.dividerColor),
                width: place.isFeatured ? 1.5 : (isSelected ? 1.5 : 1),
              ),
            ),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: SizedBox(
                    width: 60,
                    height: 60,
                    child: place.images.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: place.images.first,
                            width: 60,
                            height: 60,
                            fit: BoxFit.cover,
                            placeholder: (_, __) => _placePlaceholder(place),
                            errorWidget: (_, __, ___) => _placePlaceholder(place),
                          )
                        : _placePlaceholder(place),
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
                          if (place.isFeatured)
                            const Padding(
                              padding: EdgeInsets.only(right: 4),
                              child: Icon(Icons.star, size: 14, color: Color(0xFFFFA000)),
                            ),
                          Expanded(
                            child: Text(
                              place.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: AppTheme.textBase,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (place.isVerified)
                            const Padding(
                              padding: EdgeInsets.only(left: 4),
                              child: Icon(Icons.verified, size: 14, color: AppTheme.primaryColor),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          if (place.isFeatured)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
colors: [AppTheme.goldTick, AppTheme.warningColor],
                                ),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: const Text(
                                'Featured',
                                style: TextStyle(fontSize: AppTheme.textXs, color: AppTheme.surfaceColor, fontWeight: FontWeight.w600),
                              ),
                            ),
                          if (place.isFeatured && place.category != null)
                            const SizedBox(width: 4),
                          if (place.category != null)
Text(
                            place.category!,
                            style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          if (place.averageRating != null) ...[
                            const Icon(Icons.star, size: 14, color: AppTheme.secondaryColor),
                            const SizedBox(width: 3),
                            Text(
                              place.averageRating!.toStringAsFixed(1),
                              style: const TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(width: 8),
                          ],
                          if (place.distanceKm != null)
Text(
                              '${place.distanceKm!.toStringAsFixed(1)} km',
                              style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                            ),
                          if (place.totalReviews > 0) ...[
                            const SizedBox(width: 8),
                            Text(
                              '(${place.totalReviews})',
                              style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                IconButton(
                  icon: const Icon(Icons.arrow_forward_ios, size: 14),
                  color: AppTheme.textSecondary,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                  onPressed: () => _navigateToDetails(place),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _placePlaceholder(PlaceModel place) {
    return Container(
      width: 60,
      height: 60,
      decoration: BoxDecoration(
        color: _getCategoryColor(place.category).withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(
        _getCategoryIcon(place.category),
        color: _getCategoryColor(place.category),
        size: 24,
      ),
    );
  }

  Widget _buildSelectedPlaceCard() {
    final place = _selectedPlace!;
    return Material(
      elevation: 6,
      borderRadius: BorderRadius.circular(16),
      shadowColor: Colors.black26,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _navigateToDetails(place),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppTheme.surfaceColor,
            borderRadius: BorderRadius.circular(16),
            border: place.isFeatured
                ? Border.all(color: AppTheme.goldTick.withOpacity(0.5), width: 1.5)
                : null,
          ),
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
                          placeholder: (_, __) => _placePlaceholderSmall(place),
                          errorWidget: (_, __, ___) => _placePlaceholderSmall(place),
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
                        if (place.isFeatured)
                          const Padding(
                            padding: EdgeInsets.only(right: 4),
                            child: Icon(Icons.star, size: 14, color: Color(0xFFFFA000)),
                          ),
                        Expanded(
                          child: Text(
                            place.name,
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              fontSize: AppTheme.textBase,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (place.isVerified)
                          const Padding(
                            padding: EdgeInsets.only(left: 4),
                            child: Icon(Icons.verified, size: 14, color: AppTheme.primaryColor),
                          ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        if (place.averageRating != null) ...[
                          const Icon(Icons.star, size: 13, color: AppTheme.secondaryColor),
                          const SizedBox(width: 2),
                          Text(
                            place.averageRating!.toStringAsFixed(1),
                            style: const TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(width: 6),
                        ],
                        if (place.distanceKm != null)
                          Text(
                            '${place.distanceKm!.toStringAsFixed(1)} km',
                            style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                          ),
                        if (place.category != null) ...[
                          const SizedBox(width: 6),
                          Text(
                            '• ${place.category}',
                            style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              Icon(Icons.chevron_right, size: 20, color: AppTheme.textSecondary),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placePlaceholderSmall(PlaceModel place) {
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        color: _getCategoryColor(place.category).withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(
        _getCategoryIcon(place.category),
        color: _getCategoryColor(place.category),
        size: 22,
      ),
    );
  }

  List<Marker> _buildMarkers(List<PlaceModel> places) {
    final markers = <Marker>[];
    for (final place in places) {
      final markerColor = _getCategoryColor(place.category);
      final isSelected = _selectedPlace?.id.toString() == place.id.toString();
      final markerSize = isSelected ? 40.0 : 30.0;
      markers.add(Marker(
        point: LatLng(place.latitude, place.longitude),
        width: markerSize,
        height: markerSize,
        alignment: Alignment.center,
        child: GestureDetector(
          onTap: () => _onPlaceTap(place),
          child: Container(
            decoration: BoxDecoration(
              color: markerColor,
              shape: BoxShape.circle,
              border: Border.all(
                color: Colors.white,
                width: isSelected ? 3 : 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: markerColor.withOpacity(isSelected ? 0.5 : 0.3),
                  blurRadius: isSelected ? 8 : 3,
                  spreadRadius: isSelected ? 2 : 0.5,
                ),
              ],
            ),
            child: Icon(
              _getCategoryIcon(place.category),
              color: Colors.white,
              size: isSelected ? 20 : 15,
            ),
          ),
        ),
      ));
    }
    return markers;
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
}

// ============ PLACES LIST SHIMMER ============
class _PlacesListShimmer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const SliverToBoxAdapter(
      child: Center(
        child: CircularProgressIndicator(),
      ),
    );
  }
}

// ============ AD-ENABLED PLACES LIST ============
class _AdPlacesList extends StatefulWidget {
  final List<PlaceModel> places;
  final List<AdCampaignModel> ads;
  final ScrollController scrollController;
  final int? selectedPlaceId;
  final void Function(PlaceModel) onPlaceTap;
  final Widget Function(PlaceModel, bool) buildPlaceItem;

  const _AdPlacesList({
    required this.places,
    required this.ads,
    required this.scrollController,
    this.selectedPlaceId,
    required this.onPlaceTap,
    required this.buildPlaceItem,
  });

  @override
  State<_AdPlacesList> createState() => _AdPlacesListState();
}

class _AdPlacesListState extends State<_AdPlacesList> {
  // Random ad slot interval per screen load (3-6 places between ads)
  late final int _adInterval = 3 + math.Random().nextInt(4);

  List<dynamic> _buildFeed() {
    final feed = <dynamic>[];
    int p = 0, a = 0;
    while (p < widget.places.length) {
      for (int i = 0; i < _adInterval && p < widget.places.length; i++) feed.add(widget.places[p++]);
      if (a < widget.ads.length && p < widget.places.length) feed.add(widget.ads[a++]);
    }
    return feed;
  }

  @override
  Widget build(BuildContext context) {
    final feed = _buildFeed();
    return ListView.builder(
      controller: widget.scrollController,
      padding: const EdgeInsets.only(left: 12, right: 12, top: 4, bottom: 8),
      itemCount: feed.length,
      itemBuilder: (context, index) {
        final item = feed[index];
        if (item is AdCampaignModel) return AdPlaceCard(ad: item);
        final place = item as PlaceModel;
        return widget.buildPlaceItem(place, widget.selectedPlaceId == place.id);
      },
    );
  }
}
