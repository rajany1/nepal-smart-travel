import 'package:flutter/material.dart';

/// Manages the map view mode (Standard / Satellite)
class MapViewProvider extends ChangeNotifier {
  MapViewMode _currentMode = MapViewMode.standard;
  bool _showPlaces = true;
  bool _showTraffic = false;
  bool _showClusters = true;
  bool _showWeather = false;

  MapViewMode get currentMode => _currentMode;
  bool get showPlaces => _showPlaces;
  bool get showTraffic => _showTraffic;
  bool get showClusters => _showClusters;
  bool get showWeather => _showWeather;
  bool get isSatellite => _currentMode == MapViewMode.satellite;

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
}

enum MapViewMode { standard, satellite }