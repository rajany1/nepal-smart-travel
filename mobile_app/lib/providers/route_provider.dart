import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/route_model.dart';

class RouteProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<CuratedRouteModel> _routes = [];
  CuratedRouteModel? _selectedRoute;
  RouteGeometryResponse? _selectedGeometry;
  bool _isLoading = false;
  bool _isLoadingDetail = false;
  bool _isLoadingGeometry = false;
  String? _errorMessage;
  String? _activeTypeFilter;
  String? _activeDifficultyFilter;

  List<CuratedRouteModel> get routes => _routes;
  CuratedRouteModel? get selectedRoute => _selectedRoute;
  RouteGeometryResponse? get selectedGeometry => _selectedGeometry;
  bool get isLoading => _isLoading;
  bool get isLoadingDetail => _isLoadingDetail;
  bool get isLoadingGeometry => _isLoadingGeometry;
  String? get errorMessage => _errorMessage;
  String? get activeTypeFilter => _activeTypeFilter;
  String? get activeDifficultyFilter => _activeDifficultyFilter;

  Future<void> fetchRoutes({String? type, String? difficulty, String? search}) async {
    _activeTypeFilter = type;
    _activeDifficultyFilter = difficulty;
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await _api.getRoutes(
        type: type,
        difficulty: difficulty,
        search: search,
      );
      final data = (res.data['routes'] as List<dynamic>?) ?? [];
      _routes = data
          .map((e) => CuratedRouteModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (e) {
      debugPrint('RouteProvider: fetch routes failed: $e');
      _errorMessage = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchRouteDetail(int id) async {
    _isLoadingDetail = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final res = await _api.getRouteById(id);
      final data = (res.data['route'] as Map<String, dynamic>?) ?? {};
      _selectedRoute = CuratedRouteModel.fromJson(data);
    } catch (e) {
      debugPrint('RouteProvider: fetch detail failed: $e');
      _errorMessage = e.toString();
    }

    _isLoadingDetail = false;
    notifyListeners();
  }

  Future<void> fetchRouteGeometry(int id) async {
    _isLoadingGeometry = true;
    notifyListeners();

    try {
      final res = await _api.getRouteGeometry(id);
      final data = res.data['geometry'] as Map<String, dynamic>?;
      _selectedGeometry =
          data != null ? RouteGeometryResponse.fromJson(data) : null;
    } catch (e) {
      debugPrint('RouteProvider: fetch geometry failed: $e');
      _selectedGeometry = null;
    }

    _isLoadingGeometry = false;
    notifyListeners();
  }

  void clear() {
    _routes = [];
    _selectedRoute = null;
    _selectedGeometry = null;
    _errorMessage = null;
    notifyListeners();
  }
}
