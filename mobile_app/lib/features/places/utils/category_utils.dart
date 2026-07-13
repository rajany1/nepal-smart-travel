import 'package:flutter/material.dart';
import '../../../config/themes/app_theme.dart';

IconData getCategoryIcon(String? category) {
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

Color getCategoryColor(String? category) {
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
    case 'pharmacy':
      return AppTheme.markerEmergency;
    case 'transport':
    case 'bus':
    case 'bus_station':
    case 'airport':
      return AppTheme.markerTransport;
    case 'attraction':
    case 'museum':
    case 'landmark':
    case 'sightseeing':
      return AppTheme.markerTourist;
    case 'activity':
    case 'adventure':
      return AppTheme.markerActivity;
    case 'nature':
    case 'viewpoint':
      return AppTheme.markerActivity;
    case 'shopping':
    case 'market':
      return AppTheme.markerUtility;
    case 'atm':
    case 'bank':
      return AppTheme.markerUtility;
    default:
      return AppTheme.markerUtility;
  }
}
