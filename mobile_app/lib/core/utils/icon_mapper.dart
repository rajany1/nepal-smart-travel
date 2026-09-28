import 'package:flutter/material.dart';
import '../../../config/themes/app_theme.dart';

/// Utility class for mapping string icon names to Flutter IconData
/// Supports Material Icons, custom icons, and emoji
class IconMapper {
  static final Map<String, IconData> _materialIcons = <String, IconData>{
    'info': Icons.info_outline,
    'info_outline': Icons.info_outline,
    'road': Icons.directions_car,
    'traffic': Icons.traffic,
    'warning': Icons.warning_amber,
    'warning_amber': Icons.warning_amber,
    'ac_unit': Icons.ac_unit,
    'directions_bus': Icons.directions_bus,
    'explore': Icons.explore,
    'local_gas_station': Icons.local_gas_station,
    'event': Icons.event,
    'directions_car': Icons.directions_car,
    'groups': Icons.groups,
    'local_hospital': Icons.local_hospital,
    'pothole': Icons.terrain,
    'block': Icons.block,
    'terrain': Icons.terrain,
    'construction': Icons.construction,
    'help': Icons.help_outline,
    'hazard': Icons.warning,
    'home_repair_service': Icons.home_repair_service,
    'landscape': Icons.landscape,
    'rain': Icons.grain,
    'flood': Icons.water_drop,
    'air': Icons.air,
    'flash_on': Icons.flash_on,
    'local_taxi': Icons.local_taxi,
    'flight': Icons.flight,
    'hiking': Icons.hiking,
    'visibility': Icons.visibility,
    'star': Icons.star,
    'celebration': Icons.celebration,
    'announcement': Icons.announcement,
    'find_in_page': Icons.find_in_page,
    'bolt': Icons.bolt,
    'water_drop': Icons.water_drop,
    'wifi_off': Icons.wifi_off,
    'atm': Icons.atm,
    'assignment': Icons.assignment,
    'description': Icons.description,
    'photo_camera': Icons.photo_camera,
    'camera': Icons.camera_alt,
    'location_on': Icons.location_on,
    'gps_fixed': Icons.gps_fixed,
    'gps_off': Icons.gps_off,
    'visibility_off': Icons.visibility_off,
    'directions_bike': Icons.directions_bike,
    'hotel': Icons.hotel,
    'restaurant': Icons.restaurant,
    'tour': Icons.tour,
    'bloodtype': Icons.bloodtype,
    'medication': Icons.medication,
    'account_balance': Icons.account_balance,
    'local_hospital': Icons.local_hospital,
    'local_gas_station': Icons.local_gas_station,
    'directions_bike': Icons.directions_bike,
  };

  static final Map<String, IconData> _customIcons = <String, IconData>{};

  /// Get IconData from icon name string
  static IconData getIconData(String iconName) {
    if (_materialIcons.containsKey(iconName)) {
      return _materialIcons[iconName]!;
    }

    if (_customIcons.containsKey(iconName)) {
      return _customIcons[iconName]!;
    }

    try {
      final parts = iconName.split('.');
      if (parts.length == 2) {
        return Icons.help_outline;
      }
    } catch (_) {}

    return Icons.help_outline;
  }

  /// Get category color from icon name
  static Color getCategoryColor(String? iconName) {
    if (iconName == null) return AppTheme.primaryColor;

    switch (iconName) {
      case 'road':
      case 'traffic':
      case 'directions_car':
      case 'directions_bus':
      case 'local_taxi':
        return const Color(0xFFF39C12);
      case 'warning':
      case 'warning_amber':
      case 'hazard':
      case 'block':
        return AppTheme.errorColor;
      case 'ac_unit':
      case 'rain':
      case 'flood':
      case 'air':
      case 'flash_on':
        return const Color(0xFF5C6BC0);
      case 'directions_bike':
      case 'hiking':
      case 'explore':
        return const Color(0xFF8E44AD);
      case 'local_gas_station':
      case 'bolt':
      case 'water_drop':
      case 'local_hospital':
      case 'bloodtype':
      case 'medication':
        return const Color(0xFF16A085);
      case 'event':
      case 'celebration':
      case 'announcement':
        return const Color(0xFFE91E63);
      case 'info':
      case 'info_outline':
      case 'help':
      case 'help_outline':
      case 'assignment':
        return AppTheme.infoColor;
      default:
        return AppTheme.primaryColor;
    }
  }
}