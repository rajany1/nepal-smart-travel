import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";

/// Manages the map view mode (Standard / Satellite) and intelligence layers
class MapViewProvider extends ChangeNotifier {
  MapViewMode _currentMode = MapViewMode.standard;
  bool _showPlaces = true;
  bool _showTraffic = false;
  bool _showClusters = true;
  bool _showWeather = false;
  bool _showRoutes = false;

  // Intelligence layers
  bool _showEmergency = true;
  bool _showAlerts = true;
  bool _showReports = true;
  bool _showServices = true;

  MapViewMode get currentMode => _currentMode;
  bool get showPlaces => _showPlaces;
  bool get showTraffic => _showTraffic;
  bool get showClusters => _showClusters;
  bool get showWeather => _showWeather;
  bool get showRoutes => _showRoutes;
  bool get showEmergency => _showEmergency;
  bool get showAlerts => _showAlerts;
  bool get showReports => _showReports;
  bool get showServices => _showServices;
  bool get isSatellite => _currentMode == MapViewMode.satellite;

  List<String> get activeLayers {
    final layers = <String>[];
    if (_showEmergency) layers.add('emergency');
    if (_showAlerts) layers.add('alerts');
    if (_showReports) layers.add('reports');
    if (_showServices) layers.add('places');
    return layers;
  }

  void toggleMapMode() {
    _currentMode = _currentMode == MapViewMode.standard
        ? MapViewMode.satellite
        : MapViewMode.standard;
    notifyListeners();
  }

  void setMapMode(MapViewMode mode) {
    _currentMode = mode;
    notifyListeners();
  }

  void togglePlaces() {
    _showPlaces = !_showPlaces;
    notifyListeners();
  }

  void toggleTraffic() {
    _showTraffic = !_showTraffic;
    notifyListeners();
  }

  void toggleClusters() {
    _showClusters = !_showClusters;
    notifyListeners();
  }

  void toggleWeather() {
    _showWeather = !_showWeather;
    notifyListeners();
  }

  void toggleRoutes() {
    _showRoutes = !_showRoutes;
    notifyListeners();
  }

  void toggleEmergency() {
    _showEmergency = !_showEmergency;
    notifyListeners();
  }

  void toggleAlerts() {
    _showAlerts = !_showAlerts;
    notifyListeners();
  }

  void toggleReports() {
    _showReports = !_showReports;
    notifyListeners();
  }

  void toggleServices() {
    _showServices = !_showServices;
    notifyListeners();
  }
}

enum MapViewMode { standard, satellite }