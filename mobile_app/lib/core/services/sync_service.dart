import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../../config/constants/app_constants.dart';
import '../api/api_client.dart';
import 'offline_db_service.dart';

/// Background sync service that processes the offline sync queue
/// when internet connectivity is available.
class SyncService {
  static SyncService? _instance;
  final OfflineDbService _offlineDb = OfflineDbService.instance;
  final ApiClient _api = ApiClient.instance;
  StreamSubscription? _connectivitySub;
  Timer? _syncTimer;
  bool _isSyncing = false;

  SyncService._();

  static SyncService get instance {
    _instance ??= SyncService._();
    return _instance!;
  }

  /// Start background sync monitoring
  void startMonitoring() {
    _connectivitySub = Connectivity()
        .onConnectivityChanged
        .listen((List<ConnectivityResult> results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        _attemptSync();
      }
    });

    // Periodic sync every 5 minutes
    _syncTimer = Timer.periodic(const Duration(minutes: 5), (_) {
      _attemptSync();
    });
  }

  /// Stop monitoring
  void stopMonitoring() {
    _connectivitySub?.cancel();
    _syncTimer?.cancel();
  }

  /// Attempt to process all pending sync items
  Future<void> _attemptSync() async {
    if (_isSyncing) return;

    final connectivity = await Connectivity().checkConnectivity();
    if (connectivity.every((r) => r == ConnectivityResult.none)) return;

    _isSyncing = true;

    try {
      final pendingItems = await _offlineDb.getPendingSyncItems(limit: 20);
      for (final item in pendingItems) {
        await _processSyncItem(item);
      }
    } catch (e) {
      print('Sync error: $e');
    }

    _isSyncing = false;
  }

  Future<void> _processSyncItem(Map<String, dynamic> item) async {
    final id = item['id'] as int;
    final operation = item['operation'] as String;
    final entityType = item['entity_type'] as String;
    final payloadStr = item['payload'] as String;

    try {
      if (entityType == 'place' && operation == 'create') {
        final decoded = jsonDecode(payloadStr);
        final payload = Map<String, dynamic>.from(
          decoded is Map ? (decoded['data'] ?? decoded) : {},
        );
        // TODO: Implement actual API call for place creation
        // await _api.createPlace(payload);
        await _offlineDb.markSyncCompleted(id);
      }
    } catch (e) {
      await _offlineDb.markSyncFailed(id, e.toString());
    }
  }

  /// Force sync now
  Future<void> syncNow() => _attemptSync();
}