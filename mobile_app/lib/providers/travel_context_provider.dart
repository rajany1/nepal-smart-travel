import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/api/api_client.dart';
import '../core/models/travel_context_model.dart';

/// Owns the user's travel context (origin → checkpoints → destination),
/// the resolved corridor, ambiguity state, and ranked route intelligence.
///
/// Guest-first: no login required. Context lives in memory for the current
/// session. Only authenticated sessions may mirror the route to local
/// storage (SharedPreferences) so it survives a cold start; guests never
/// read or write that key.
class TravelContextProvider extends ChangeNotifier {
  static const String _savedRouteKey = 'saved_travel_context_route';

  final ApiClient _api = ApiClient.instance;

  String _originInput = '';
  String _destinationInput = '';
  final List<String> _checkpointInputs = [];

  TravelContextModel? _context;
  RouteIntelligence? _intelligence;

  bool _isResolving = false;
  bool _isLoadingIntel = false;
  String? _error;
  String? _intelError;
  bool _persistRoute = false;

  // ── Getters ──────────────────────────────────────────────────────

  String get originInput => _originInput;
  String get destinationInput => _destinationInput;
  List<String> get checkpointInputs => List.unmodifiable(_checkpointInputs);
  TravelContextModel? get context => _context;
  RouteIntelligence? get intelligence => _intelligence;
  bool get isResolving => _isResolving;
  bool get isLoadingIntel => _isLoadingIntel;
  String? get error => _error;
  String? get intelError => _intelError;

  bool get hasContext => _context != null && _context!.hasCorridor;
  bool get needsClarification => _context?.needsClarification ?? false;
  bool get hasIntelligence => _intelligence != null && !_intelligence!.isEmpty;
  bool get isRoutePersisted => _persistRoute;

  // ── Session persistence (authenticated only) ─────────────────────

  /// Enable/disable mirroring the active route to local storage.
  /// Guests keep this off so their route is session-only.
  void setPersistRoute(bool enabled) {
    _persistRoute = enabled;
    notifyListeners();
  }

  /// Load a previously saved authenticated route into memory.
  /// No-ops for guests or when a route is already active (e.g. guest set a
  /// route this session, then logged in — keep the live route).
  Future<void> restoreSavedRouteIfAny() async {
    if (!_persistRoute || hasContext) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_savedRouteKey);
      if (raw == null || raw.isEmpty) return;
      final map = jsonDecode(raw) as Map<String, dynamic>;
      final origin = (map['origin'] ?? '').toString();
      final destination = (map['destination'] ?? '').toString();
      if (origin.isEmpty || destination.isEmpty) return;
      _originInput = origin;
      _destinationInput = destination;
      _checkpointInputs
        ..clear()
        ..addAll(List<String>.from(map['checkpoints'] as List? ?? const []));
      final ctx = map['context'];
      if (ctx is Map<String, dynamic>) {
        _context = TravelContextModel.fromJson(ctx);
      }
      notifyListeners();
    } catch (_) {
      // Corrupt/unsupported snapshot — leave in-memory state untouched.
    }
  }

  /// Persist the current route when persistence is enabled (auth only).
  Future<void> persistNow() async {
    if (!_persistRoute) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      if (_originInput.isEmpty || _destinationInput.isEmpty) {
        await prefs.remove(_savedRouteKey);
        return;
      }
      await prefs.setString(
        _savedRouteKey,
        jsonEncode({
          'origin': _originInput,
          'destination': _destinationInput,
          'checkpoints': _checkpointInputs,
          if (_context != null)
            'context': _context!.toPayload(includeGeometry: true),
        }),
      );
    } catch (_) {
      // Best-effort local save; never block the UX.
    }
  }

  Future<void> _clearPersistedRoute() async {
    if (!_persistRoute) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_savedRouteKey);
    } catch (_) {}
  }

  /// User became authenticated (login or session restore mid-app).
  /// Keep any live in-memory route and start mirroring it; otherwise
  /// restore the last saved authenticated route.
  Future<void> handleLoggedIn() async {
    _persistRoute = true;
    if (hasContext) {
      await persistNow();
    } else {
      await restoreSavedRouteIfAny();
    }
    notifyListeners();
  }

  /// User logged out: stop persisting and drop the disk snapshot so a
  /// later guest cold start cannot recover it. The in-memory route stays
  /// for the rest of this app session only.
  Future<void> handleLoggedOut() async {
    _persistRoute = false;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_savedRouteKey);
    } catch (_) {}
    notifyListeners();
  }

  // ── Input ────────────────────────────────────────────────────────

  void setOrigin(String value) {
    _originInput = value.trim();
    notifyListeners();
  }

  void setDestination(String value) {
    _destinationInput = value.trim();
    notifyListeners();
  }

  void addCheckpoint(String value) {
    final v = value.trim();
    if (v.isEmpty || _checkpointInputs.contains(v)) return;
    _checkpointInputs.add(v);
    notifyListeners();
  }

  void removeCheckpoint(int index) {
    if (index >= 0 && index < _checkpointInputs.length) {
      _checkpointInputs.removeAt(index);
      notifyListeners();
    }
  }

  void clearCheckpoints() {
    _checkpointInputs.clear();
    notifyListeners();
  }

  /// Replace checkpoints from a selected route option's via points.
  void applyRouteOption(RouteOption option) {
    _checkpointInputs
      ..clear()
      ..addAll(option.via.map((v) => '${v.lat},${v.lng}'));
    notifyListeners();
  }

  void clear() {
    _originInput = '';
    _destinationInput = '';
    _checkpointInputs.clear();
    _context = null;
    _intelligence = null;
    _error = null;
    _intelError = null;
    _isResolving = false;
    _isLoadingIntel = false;
    unawaited(_clearPersistedRoute());
    notifyListeners();
  }

  void clearContextOnly() {
    _context = null;
    _intelligence = null;
    _error = null;
    _intelError = null;
    notifyListeners();
  }

  // ── Resolve corridor ─────────────────────────────────────────────

  /// Resolve origin → [checkpoints] → destination into a corridor.
  /// Returns true when a usable corridor was produced.
  Future<bool> resolve({double? currentLat, double? currentLng}) async {
    if (_originInput.isEmpty || _destinationInput.isEmpty) {
      _error = 'Enter where you are starting and where you are going.';
      notifyListeners();
      return false;
    }

    _isResolving = true;
    _error = null;
    _intelError = null;
    notifyListeners();

    try {
      final res = await _api.resolveTravelContext(
        origin: _originInput,
        destination: _destinationInput,
        checkpoints: List.of(_checkpointInputs),
        currentLat: currentLat,
        currentLng: currentLng,
      );

      final body = res.data is Map<String, dynamic> ? res.data as Map<String, dynamic> : null;
      if (body == null || body['success'] != true) {
        _error = body?['message']?.toString() ?? 'Could not resolve this route.';
        _context = null;
        return false;
      }

      final data = body['data'] as Map<String, dynamic>? ?? const {};
      final tc = data['travel_context'] as Map<String, dynamic>?;
      if (tc == null) {
        _error = 'Could not resolve this route.';
        _context = null;
        return false;
      }

      _context = TravelContextModel.fromJson(tc);
      if (!_context!.hasCorridor) {
        _error = _context!.unresolved ?? 'Could not resolve one or more locations.';
        _context = null;
        return false;
      }
      unawaited(persistNow());
      return true;
    } catch (e) {
      _error = _messageFromError(e, 'Could not resolve this route.');
      _context = null;
      return false;
    } finally {
      _isResolving = false;
      notifyListeners();
    }
  }

  // ── Intelligence ─────────────────────────────────────────────────

  /// Fetch ranked intelligence for the current (or freshly resolved) context.
  Future<bool> fetchIntelligence({double? currentLat, double? currentLng}) async {
    final ctx = _context;
    if (ctx == null || !ctx.hasCorridor) {
      final ok = await resolve(currentLat: currentLat, currentLng: currentLng);
      if (!ok) return false;
    }

    _isLoadingIntel = true;
    _intelError = null;
    notifyListeners();

    try {
      final active = _context!;
      final res = await _api.getTravelIntelligence(
        travelContext: active.toPayload(includeGeometry: true),
        origin: _originInput,
        destination: _destinationInput,
        checkpoints: List.of(_checkpointInputs),
        currentLat: currentLat,
        currentLng: currentLng,
      );

      final body = res.data is Map<String, dynamic> ? res.data as Map<String, dynamic> : null;
      if (body == null || body['success'] != true) {
        _intelError = body?['message']?.toString() ?? 'Could not load route updates.';
        _intelligence = null;
        return false;
      }

      final data = body['data'] as Map<String, dynamic>? ?? const {};
      final intel = data['intelligence'] as Map<String, dynamic>?;
      if (intel != null) {
        _intelligence = RouteIntelligence.fromJson(intel);
      }

      // Refresh context summary (journey progress etc.) when returned.
      final tc = data['travel_context'] as Map<String, dynamic>?;
      if (tc != null && tc['geometry'] != null) {
        _context = TravelContextModel.fromJson(tc);
      }
      unawaited(persistNow());

      return true;
    } catch (e) {
      _intelError = _messageFromError(e, 'Could not load route updates.');
      _intelligence = null;
      return false;
    } finally {
      _isLoadingIntel = false;
      notifyListeners();
    }
  }

  /// One-shot: resolve + fetch intelligence (typical "Show my route" tap).
  Future<bool> planRoute({double? currentLat, double? currentLng}) async {
    final ok = await resolve(currentLat: currentLat, currentLng: currentLng);
    if (!ok) return false;
    return fetchIntelligence(currentLat: currentLat, currentLng: currentLng);
  }

  String _messageFromError(Object e, String fallback) {
    if (e is Exception) {
      final s = e.toString();
      // DioException message often embeds the API message.
      final apiMsg = RegExp(r'"message":"([^"]+)"').firstMatch(s);
      if (apiMsg != null) return apiMsg.group(1)!;
      if (s.contains('422')) {
        final err = RegExp(r'"error":"([^"]+)"').firstMatch(s);
        if (err != null) return 'Could not resolve: ${err.group(1)}';
      }
    }
    return fallback;
  }
}
