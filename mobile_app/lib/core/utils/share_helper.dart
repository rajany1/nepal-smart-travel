import 'dart:io';
import 'package:share_plus/share_plus.dart';

class ShareHelper {
  ShareHelper._();

  static const String _baseUrl = 'https://nepalsmarttravel.com';
  static const String _androidPackage = 'com.example.nepal_smart_travel';
  static const String _iosAppId = '6749064898';

  static String get _appStoreLink => 'https://apps.apple.com/app/id=$_iosAppId';
  static String get _playStoreLink => 'https://play.google.com/store/apps/details?id=$_androidPackage';

  static String get _downloadLink => Platform.isIOS ? _appStoreLink : _playStoreLink;
  static String get _downloadLabel => Platform.isIOS ? 'App Store' : 'Play Store';

  /// Share a report with dynamic content
  static Future<void> shareReport({
    required String reportId,
    required String title,
    required String description,
  }) async {
    final link = '$_baseUrl/reports/$reportId';
    final text = '📍 $title\n\n$description\n\n🔗 View in ORIPORI App: $link\n\n📱 Download ORIPORI on $_downloadLabel:\n$_downloadLink';
    await Share.share(text);
  }

  /// Share a place with dynamic content
  static Future<void> sharePlace({
    required String placeId,
    required String name,
    required String category,
    String? address,
  }) async {
    final link = '$_baseUrl/places/$placeId';
    final addrText = address != null ? '\n📍 $address' : '';
    final text = '🏛️ $name\n📂 $category$addrText\n\n🔗 View in ORIPORI App: $link\n\n📱 Download ORIPORI on $_downloadLabel:\n$_downloadLink';
    await Share.share(text);
  }

  /// Share an alert with dynamic content
  static Future<void> shareAlert({
    required String title,
    required String description,
    required String severity,
    String? district,
  }) async {
    final districtText = district != null ? '\n📍 $district' : '';
    final text = '⚠️ [$severity] $title\n\n$description$districtText\n\n📱 Download ORIPORI for real-time alerts:\n$_downloadLink';
    await Share.share(text);
  }

  /// Share an offer/reward code
  static Future<void> shareOfferCode({
    required String code,
    required String offerTitle,
    String? businessName,
  }) async {
    final bizText = businessName != null ? '\n📍 Valid at: $businessName' : '';
    final text = '🎁 ORIPORI Reward Code: $code\n\n🏷️ Offer: $offerTitle$bizText\n\n📱 Get more rewards on ORIPORI:\n$_downloadLink';
    await Share.share(text);
  }

  /// Share a generic image
  static Future<void> shareImage({required String imageUrl}) async {
    final text = '📸 Check this out on ORIPORI!\n\n$imageUrl\n\n📱 Download ORIPORI on $_downloadLabel:\n$_downloadLink';
    await Share.share(text);
  }
}
