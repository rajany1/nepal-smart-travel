import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import '../../config/constants/app_constants.dart';

/// Local SQLite-based offline database for caching places, map tiles, and sync queue.
class OfflineDbService {
  static OfflineDbService? _instance;
  Database? _db;

  OfflineDbService._();

  static OfflineDbService get instance {
    _instance ??= OfflineDbService._();
    return _instance!;
  }

  Future<Database> get database async {
    _db ??= await _initDb();
    return _db!;
  }

  Future<Database> _initDb() async {
    final dbPath = await getDatabasesPath();
    final path = p.join(dbPath, AppConstants.offlineDbName);

    return openDatabase(
      path,
      version: AppConstants.offlineDbVersion,
      onCreate: _onCreate,
      onUpgrade: _onUpgrade,
    );
  }

  Future<void> _onCreate(Database db, int version) async {
    // Places cache
    await db.execute('''
      CREATE TABLE cached_places (
        id TEXT PRIMARY KEY,
        json_data TEXT NOT NULL,
        geohash TEXT,
        lat REAL NOT NULL,
        lng REAL NOT NULL,
        category TEXT,
        source TEXT DEFAULT 'admin',
        is_verified INTEGER DEFAULT 0,
        is_featured INTEGER DEFAULT 0,
        fetched_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL
      )
    ''');

    // Offline sync queue
    await db.execute('''
      CREATE TABLE sync_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operation TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT,
        payload TEXT NOT NULL,
        media_paths TEXT,
        status TEXT DEFAULT 'pending',
        created_at INTEGER NOT NULL,
        retry_count INTEGER DEFAULT 0,
        last_error TEXT
      )
    ''');

    // Map tile cache index
    await db.execute('''
      CREATE TABLE tile_cache (
        z INTEGER NOT NULL,
        x INTEGER NOT NULL,
        y INTEGER NOT NULL,
        tile_type TEXT NOT NULL,
        blob_data BLOB,
        cached_at INTEGER NOT NULL,
        PRIMARY KEY (z, x, y, tile_type)
      )
    ''');

    // Geo-fence regions for offline areas
    await db.execute('''
      CREATE TABLE offline_regions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        min_lat REAL NOT NULL,
        max_lat REAL NOT NULL,
        min_lng REAL NOT NULL,
        max_lng REAL NOT NULL,
        zoom_min INTEGER DEFAULT 8,
        zoom_max INTEGER DEFAULT 16,
        downloaded_at INTEGER NOT NULL,
        size_bytes INTEGER DEFAULT 0,
        is_expired INTEGER DEFAULT 0
      )
    ''');

    // Recently viewed places
    await db.execute('''
      CREATE TABLE recently_viewed (
        place_id TEXT NOT NULL,
        viewed_at INTEGER NOT NULL,
        PRIMARY KEY (place_id)
      )
    ''');

    // Indexes
    await db.execute('CREATE INDEX idx_places_geohash ON cached_places(geohash)');
    await db.execute('CREATE INDEX idx_places_lat_lng ON cached_places(lat, lng)');
    await db.execute('CREATE INDEX idx_sync_status ON sync_queue(status)');
  }

  Future<void> _onUpgrade(Database db, int oldVersion, int newVersion) async {
    // Future migration logic
  }

  // ===================== PLACES CACHE =====================

  Future<void> cachePlace(Map<String, dynamic> placeJson) async {
    final db = await database;
    final id = placeJson['id']?.toString() ?? '';
    if (id.isEmpty) return;

    final lat = double.tryParse((placeJson['latitude'] ?? 0).toString()) ?? 0.0;
    final lng = double.tryParse((placeJson['longitude'] ?? 0).toString()) ?? 0.0;
    final geohash = _encodeGeohash(lat, lng, 6);
    final now = DateTime.now().millisecondsSinceEpoch;

    await db.insert(
      'cached_places',
      {
        'id': id,
        'json_data': jsonEncode(placeJson),
        'geohash': geohash,
        'lat': lat,
        'lng': lng,
        'category': placeJson['category'],
        'source': placeJson['source'] ?? 'admin',
        'is_verified': (placeJson['is_verified'] == true) ? 1 : 0,
        'is_featured': (placeJson['is_featured'] == true) ? 1 : 0,
        'fetched_at': now,
        'expires_at': now + AppConstants.cacheDuration.inMilliseconds,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  Future<void> cachePlacesBulk(List<Map<String, dynamic>> places) async {
    final batch = (await database).batch();
    final now = DateTime.now().millisecondsSinceEpoch;

    for (final place in places) {
      final id = place['id']?.toString() ?? '';
      if (id.isEmpty) continue;

      final lat = double.tryParse((place['latitude'] ?? 0).toString()) ?? 0.0;
      final lng = double.tryParse((place['longitude'] ?? 0).toString()) ?? 0.0;
      final geohash = _encodeGeohash(lat, lng, 6);

      batch.insert(
        'cached_places',
        {
          'id': id,
          'json_data': jsonEncode(place),
          'geohash': geohash,
          'lat': lat,
          'lng': lng,
          'category': place['category'],
          'source': place['source'] ?? 'admin',
          'is_verified': (place['is_verified'] == true) ? 1 : 0,
          'is_featured': (place['is_featured'] == true) ? 1 : 0,
          'fetched_at': now,
          'expires_at': now + AppConstants.cacheDuration.inMilliseconds,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }

    await batch.commit(noResult: true);
  }

  Future<List<Map<String, dynamic>>> getCachedPlacesInBounds({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    String? category,
  }) async {
    final db = await database;
    final now = DateTime.now().millisecondsSinceEpoch;

    String where = 'lat >= ? AND lat <= ? AND lng >= ? AND lng <= ? AND expires_at > ?';
    List<dynamic> whereArgs = [minLat, maxLat, minLng, maxLng, now];

    if (category != null && category.isNotEmpty) {
      where += ' AND category = ?';
      whereArgs.add(category);
    }

    final rows = await db.query(
      'cached_places',
      where: where,
      whereArgs: whereArgs,
      orderBy: 'is_featured DESC, fetched_at DESC',
      limit: 200,
    );

    return rows.map((row) {
      final json = jsonDecode(row['json_data'] as String) as Map<String, dynamic>;
      return json;
    }).toList();
  }

  Future<Map<String, dynamic>?> getCachedPlace(String id) async {
    final db = await database;
    final now = DateTime.now().millisecondsSinceEpoch;
    final rows = await db.query(
      'cached_places',
      where: 'id = ? AND expires_at > ?',
      whereArgs: [id, now],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return jsonDecode(rows.first['json_data'] as String) as Map<String, dynamic>;
  }

  Future<void> clearExpiredCache() async {
    final db = await database;
    final now = DateTime.now().millisecondsSinceEpoch;
    await db.delete('cached_places', where: 'expires_at <= ?', whereArgs: [now]);
  }

  // ===================== SYNC QUEUE =====================

  Future<int> addToSyncQueue({
    required String operation,
    required String entityType,
    String? entityId,
    required Map<String, dynamic> payload,
    List<String>? mediaPaths,
  }) async {
    final db = await database;
    return db.insert('sync_queue', {
      'operation': operation,
      'entity_type': entityType,
      'entity_id': entityId,
      'payload': jsonEncode(payload),
      'media_paths': mediaPaths != null ? jsonEncode(mediaPaths) : null,
      'status': 'pending',
      'created_at': DateTime.now().millisecondsSinceEpoch,
      'retry_count': 0,
    });
  }

  Future<List<Map<String, dynamic>>> getPendingSyncItems({int limit = 20}) async {
    final db = await database;
    return db.query(
      'sync_queue',
      where: 'status = ?',
      whereArgs: ['pending'],
      orderBy: 'created_at ASC',
      limit: limit,
    );
  }

  Future<void> markSyncCompleted(int queueId) async {
    final db = await database;
    await db.delete('sync_queue', where: 'id = ?', whereArgs: [queueId]);
  }

  Future<void> markSyncFailed(int queueId, String error) async {
    final db = await database;
    final row = await db.query('sync_queue', where: 'id = ?', whereArgs: [queueId], limit: 1);
    if (row.isEmpty) return;

    final retryCount = (row.first['retry_count'] as int?) ?? 0;
    if (retryCount >= AppConstants.apiRetryCount) {
      await db.update(
        'sync_queue',
        {'status': 'failed', 'last_error': error},
        where: 'id = ?',
        whereArgs: [queueId],
      );
    } else {
      await db.update(
        'sync_queue',
        {'status': 'pending', 'retry_count': retryCount + 1, 'last_error': error},
        where: 'id = ?',
        whereArgs: [queueId],
      );
    }
  }

  Future<int> getPendingSyncCount() async {
    final db = await database;
    final result = await db.rawQuery('SELECT COUNT(*) as count FROM sync_queue WHERE status = ?', ['pending']);
    return Sqflite.firstIntValue(result) ?? 0;
  }

  // ===================== RECENTLY VIEWED =====================

  Future<void> addRecentlyViewed(String placeId) async {
    final db = await database;
    final now = DateTime.now().millisecondsSinceEpoch;
    await db.insert(
      'recently_viewed',
      {'place_id': placeId, 'viewed_at': now},
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    // Keep only last 50
    await db.rawQuery('''
      DELETE FROM recently_viewed WHERE place_id NOT IN (
        SELECT place_id FROM recently_viewed ORDER BY viewed_at DESC LIMIT 50
      )
    ''');
  }

  Future<List<String>> getRecentlyViewedIds({int limit = 20}) async {
    final db = await database;
    final rows = await db.query(
      'recently_viewed',
      orderBy: 'viewed_at DESC',
      limit: limit,
    );
    return rows.map((r) => r['place_id'] as String).toList();
  }

  // ===================== UTILITIES =====================

  /// Simple geohash encoder for bounding box queries
  String _encodeGeohash(double lat, double lng, int precision) {
    const base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    String geohash = '';
    bool isEven = true;
    double minLat = -90.0, maxLat = 90.0;
    double minLng = -180.0, maxLng = 180.0;
    int bit = 0, ch = 0;

    while (geohash.length < precision) {
      if (isEven) {
        final mid = (minLng + maxLng) / 2;
        if (lng > mid) {
          ch |= (1 << (4 - bit));
          minLng = mid;
        } else {
          maxLng = mid;
        }
      } else {
        final mid = (minLat + maxLat) / 2;
        if (lat > mid) {
          ch |= (1 << (4 - bit));
          minLat = mid;
        } else {
          maxLat = mid;
        }
      }

      bit++;
      isEven = !isEven;

      if (bit > 4) {
        geohash += base32[ch];
        bit = 0;
        ch = 0;
      }
    }
    return geohash;
  }

  /// Clear all cache data
  Future<void> clearAll() async {
    final db = await database;
    await db.delete('cached_places');
    await db.delete('sync_queue');
    await db.delete('tile_cache');
    await db.delete('recently_viewed');
  }
}