import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
// latlong2 also defines a generic Path<T>, which would shadow dart:ui's.
import 'package:latlong2/latlong.dart' hide Path;

import '../../../config/themes/app_theme.dart';
import '../../../core/services/localization_service.dart';

/// Weather-code effect groups for the weather chip's ambient animation.
///
/// The WMO groupings mirror the map's weather grid codes (clear 0, cloudy
/// 1-3, fog 45-48, drizzle 51-57, rain 61-67 + 80-82, snow 71-77 + 85-86,
/// storm 95-99) so the chip always agrees with the fetched weather data.
enum WeatherFx { clear, cloudy, fog, drizzle, rain, snow, storm }

/// Maps a WMO weather code to its chip effect. Unknown codes fall back to
/// [WeatherFx.cloudy] — grey, calm, never wrong.
WeatherFx weatherFxForCode(int code) {
  if (code == 0) return WeatherFx.clear;
  if (code >= 1 && code <= 3) return WeatherFx.cloudy;
  if (code >= 45 && code <= 48) return WeatherFx.fog;
  if (code >= 51 && code <= 57) return WeatherFx.drizzle;
  if (code >= 61 && code <= 67) return WeatherFx.rain;
  if (code >= 71 && code <= 77) return WeatherFx.snow;
  if (code >= 80 && code <= 82) return WeatherFx.rain;
  if (code >= 85 && code <= 86) return WeatherFx.snow;
  if (code >= 95) return WeatherFx.storm;
  return WeatherFx.cloudy;
}

/// English key for [BuildContext.t] — English is the built-in fallback, so
/// no glossary entry is required for the chip to render.
String fxLabelKey(WeatherFx fx) {
  switch (fx) {
    case WeatherFx.clear:
      return 'Clear';
    case WeatherFx.cloudy:
      return 'Cloudy';
    case WeatherFx.fog:
      return 'Fog';
    case WeatherFx.drizzle:
      return 'Drizzle';
    case WeatherFx.rain:
      return 'Rain';
    case WeatherFx.snow:
      return 'Snow';
    case WeatherFx.storm:
      return 'Thunderstorm';
  }
}

/// One loop of the ambient effect. Slow on purpose — this chip decorates a
/// live map, it must never pull the eye.
Duration fxDuration(WeatherFx fx) {
  switch (fx) {
    case WeatherFx.clear:
      return const Duration(seconds: 4);
    case WeatherFx.cloudy:
      return const Duration(seconds: 14);
    case WeatherFx.fog:
      return const Duration(seconds: 10);
    case WeatherFx.drizzle:
      return const Duration(milliseconds: 3500);
    case WeatherFx.rain:
      return const Duration(milliseconds: 2500);
    case WeatherFx.snow:
      return const Duration(seconds: 8);
    case WeatherFx.storm:
      return const Duration(seconds: 6);
  }
}

IconData fxIcon(WeatherFx fx) {
  switch (fx) {
    case WeatherFx.clear:
      return Icons.wb_sunny;
    case WeatherFx.cloudy:
      return Icons.cloud;
    case WeatherFx.fog:
      return Icons.foggy;
    case WeatherFx.drizzle:
      return Icons.water_drop;
    case WeatherFx.rain:
      return Icons.umbrella;
    case WeatherFx.snow:
      return Icons.ac_unit;
    case WeatherFx.storm:
      return Icons.thunderstorm;
  }
}

/// 12% tint of the effect colour — the icon tile background. Alpha is baked
/// into the constant so nothing computes opacity at runtime.
Color fxTileColor(WeatherFx fx) {
  switch (fx) {
    case WeatherFx.clear:
      return const Color.fromARGB(0x1F, 0xFF, 0xC1, 0x07);
    case WeatherFx.cloudy:
      return const Color.fromARGB(0x1F, 0x9E, 0x9E, 0x9E);
    case WeatherFx.fog:
      return const Color.fromARGB(0x1F, 0xB0, 0xBE, 0xC5);
    case WeatherFx.drizzle:
      return const Color.fromARGB(0x1F, 0x87, 0xCE, 0xEB);
    case WeatherFx.rain:
      return const Color.fromARGB(0x1F, 0x41, 0x69, 0xE1);
    case WeatherFx.snow:
      return const Color.fromARGB(0x1F, 0x90, 0xCA, 0xF9);
    case WeatherFx.storm:
      return const Color.fromARGB(0x1F, 0x9C, 0x27, 0xB0);
  }
}

/// Full-strength icon colour — readable on the tinted tile and white card.
Color fxIconColor(WeatherFx fx) {
  switch (fx) {
    case WeatherFx.clear:
      return const Color(0xFFF57F17);
    case WeatherFx.cloudy:
      return const Color(0xFF757575);
    case WeatherFx.fog:
      return const Color(0xFF78909C);
    case WeatherFx.drizzle:
      return const Color(0xFF0288D1);
    case WeatherFx.rain:
      return const Color(0xFF4169E1);
    case WeatherFx.snow:
      return const Color(0xFF42A5F5);
    case WeatherFx.storm:
      return const Color(0xFF7B1FA2);
  }
}

/// Weather chip: a small white card (~160x62) showing temperature +
/// condition + area label + last-updated time, with a subtle condition-tied
/// animation painted behind the text.
///
/// Performance contract (the whole point of this widget living in its own
/// file):
/// * ONE [AnimationController]; the effect repaints via
///   `CustomPainter(repaint:)` — no per-frame widget rebuilds, no
///   [AnimatedBuilder] churn.
/// * Every [Paint]/[Color]/[Path] is pre-allocated once per painter; a
///   frame only computes coordinates.
/// * The card sits in a [RepaintBoundary] + [ClipRRect], so the 60fps
///   effect repaints nothing but the chip.
/// * Under `MediaQuery.disableAnimations` the controller stops on a
///   representative static frame (t = 0.5).
///
/// Non-interactive by construction: the placement site decides the pointer
/// behaviour ([GestureDetector] for the tap-to-reveal boundary effect,
/// [IgnorePointer] to pass taps straight through to the map beneath).
/// Chip height: three text lines (13/11/10px) stacked with breathing room,
/// and the geometry reference for both the icon-tile anchor and the
/// boundary-mode primitive scale.
const double _chipHeight = 62;

class WeatherChip extends StatefulWidget {
  const WeatherChip({
    super.key,
    required this.code,
    this.temperatureC,
    this.areaLabel,
    this.updatedAt,
  });

  /// WMO weather code (0-99).
  final int code;

  /// Degrees Celsius; null renders the condition label alone.
  final double? temperatureC;

  /// Line 2 (e.g. "Kathmandu"); null/empty omits the line entirely.
  final String? areaLabel;

  /// When the shown weather snapshot was fetched, converted for display as
  /// "Updated 2:30 PM" on line 3; null omits the line entirely.
  final DateTime? updatedAt;

  @override
  State<WeatherChip> createState() => _WeatherChipState();
}

class _WeatherChipState extends State<WeatherChip>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: fxDuration(weatherFxForCode(widget.code)),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncMotion();
  }

  /// Honour the OS "remove animations" setting: freeze on a representative
  /// static frame instead of looping. Called on mount and whenever
  /// [MediaQuery] changes.
  void _syncMotion() {
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
      _controller.value = 0.5;
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void didUpdateWidget(covariant WeatherChip old) {
    super.didUpdateWidget(old);
    if (old.code != widget.code) {
      _controller.duration = fxDuration(weatherFxForCode(widget.code));
      if (MediaQuery.disableAnimationsOf(context)) {
        _controller.stop();
        _controller.value = 0.5;
      } else {
        // Repeats with the new duration from the top (a fresh storm switch
        // gets its opening flash exactly once).
        _controller.repeat();
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final fx = weatherFxForCode(widget.code);
    final label = context.t(fxLabelKey(fx));
    final temp = widget.temperatureC;
    final line1 = temp == null ? label : '${temp.round()}°C $label';

    return RepaintBoundary(
      child: Material(
        color: Colors.white,
        elevation: 2,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 160),
            child: SizedBox(
              height: _chipHeight,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // The effect: repaints with the controller, never with
                  // the widget tree.
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _WeatherFxPainter(
                        fx: fx,
                        animation: _controller,
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 30,
                          height: 30,
                          decoration: BoxDecoration(
                            color: fxTileColor(fx),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Icon(
                            fxIcon(fx),
                            size: 17,
                            color: fxIconColor(fx),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                line1,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppTheme.textPrimary,
                                  height: 1.15,
                                ),
                              ),
                              if (widget.areaLabel != null &&
                                  widget.areaLabel!.isNotEmpty)
                                Text(
                                  widget.areaLabel!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppTheme.textSecondary,
                                    height: 1.15,
                                  ),
                                ),
                              if (widget.updatedAt != null)
                                Text(
                                  '${context.t('Updated')} '
                                  '${TimeOfDay.fromDateTime(widget.updatedAt!.toLocal()).format(context)}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: AppTheme.textSecondary,
                                    height: 1.15,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Canvas geometry for boundary mode ([_WeatherFxPainter.boundary]).
///
/// [clip] is the cell quad in coordinates relative to the CustomPaint's
/// origin (the projected bbox's top-left), so clipping happens against the
/// boundary's own geometry — the effect can never paint outside it.
/// [primScale] thickens the fixed-size primitives to the cell's size, so
/// the effect reads the same at map scale as it does on the chip.
class _BoundaryFxLayout {
  const _BoundaryFxLayout({required this.clip, required this.primScale});

  /// The cell polygon, local to the CustomPaint box.
  final Path clip;

  /// Primitive scale (cell bbox / chip height), clamped by the caller.
  final double primScale;
}

/// The ambient effect painter.
///
/// Driven by `super(repaint: animation)` — every tick repaints this
/// [CustomPaint] alone. All paints, the cloud path and the flash ramp are
/// `static final`, so a frame allocates nothing beyond a handful of
/// coordinate [Offset]s (fewer allocations than the existing blue-dot
/// painter, which is the on-screen precedent for this pattern).
///
/// Every colour bakes its alpha at construction time; the maximum canvas
/// alpha anywhere is 0x2E / 255 ≈ 0.176 — inside the ≤0.18 budget, so the
/// text on top always stays fully legible.
///
/// Two canvas modes share this one [paint] routine:
/// * **Card mode** ([boundary] null): full canvas, primitives at scale 1.0,
///   clear-glow anchored to the icon tile — the chip exactly as always.
/// * **Boundary mode** ([boundary] set): clip to the cell polygon, then
///   draw the same effect over the cell bbox with primitives scaled by
///   `primScale` and the clear-glow re-anchored to the cell centre. Same
///   visual, spatial form — no effect is ever redefined here.
class _WeatherFxPainter extends CustomPainter {
  _WeatherFxPainter({
    required this.fx,
    required this.animation,
    this.boundary,
  }) : super(repaint: animation);

  final WeatherFx fx;
  final Animation<double> animation;

  /// Null = chip-card mode (full canvas, unit primitives, tile anchor).
  final _BoundaryFxLayout? boundary;

  // ── Shared, pre-allocated resources (one-time init, never mutated) ──

  /// Icon-tile centre in chip coordinates: 10dp padding + half a 30dp tile
  /// horizontally; vertically the row is centred in the chip.
  static const double _tileCx = 25;
  static const double _tileCy = _chipHeight / 2;

  /// Boundary-mode copies of the stroked paints (strokeWidth × primScale),
  /// built lazily once per painter instance. The card path keeps using the
  /// untouched static paints; colours/caps are copied from them so the two
  /// modes can never diverge.
  Paint? _fogX;
  Paint? _drizzleX;
  Paint? _rainX;

  static Paint _scaledStroke(Paint base, double s) => Paint()
    ..color = base.color
    ..strokeWidth = base.strokeWidth * s
    ..strokeCap = base.strokeCap;

  Paint _fogFor(double s) =>
      s == 1.0 ? _fogPaint : (_fogX ??= _scaledStroke(_fogPaint, s));

  Paint _drizzleFor(double s) =>
      s == 1.0 ? _drizzlePaint : (_drizzleX ??= _scaledStroke(_drizzlePaint, s));

  Paint _rainFor(double s) =>
      s == 1.0 ? _rainPaint : (_rainX ??= _scaledStroke(_rainPaint, s));

  /// Clear: amber glow behind the icon, breathing through 5 alpha steps.
  static final List<Paint> _clearPulse = <Paint>[
    Paint()..color = const Color.fromARGB(0x0D, 0xFF, 0xC1, 0x07),
    Paint()..color = const Color.fromARGB(0x14, 0xFF, 0xC1, 0x07),
    Paint()..color = const Color.fromARGB(0x1F, 0xFF, 0xC1, 0x07),
    Paint()..color = const Color.fromARGB(0x26, 0xFF, 0xC1, 0x07),
    Paint()..color = const Color.fromARGB(0x2E, 0xFF, 0xC1, 0x07),
  ];

  /// One soft cloud silhouette (non-zero winding: the ovals union into a
  /// single fill, so the alpha applies once — no stacked-transparency
  /// darkening).
  static final Path _cloudPath = () {
    final p = Path();
    p.addOval(Rect.fromCircle(center: const Offset(12, 14), radius: 9));
    p.addOval(Rect.fromCircle(center: const Offset(23, 9), radius: 11));
    p.addOval(Rect.fromCircle(center: const Offset(34, 15), radius: 8));
    p.addRRect(RRect.fromRectAndRadius(
      Rect.fromLTWH(8, 14, 28, 9),
      const Radius.circular(4),
    ));
    return p;
  }();

  static final Paint _cloudPaint =
      Paint()..color = const Color.fromARGB(0x24, 0x9E, 0x9E, 0x9E);
  static final Paint _stormCloudPaint =
      Paint()..color = const Color.fromARGB(0x1A, 0x9C, 0x27, 0xB0);

  static final Paint _fogPaint = Paint()
    ..color = const Color.fromARGB(0x26, 0xB0, 0xBE, 0xC5)
    ..strokeWidth = 4
    ..strokeCap = StrokeCap.round;

  static final Paint _drizzlePaint = Paint()
    ..color = const Color.fromARGB(0x26, 0x87, 0xCE, 0xEB)
    ..strokeWidth = 2
    ..strokeCap = StrokeCap.round;

  static final Paint _rainPaint = Paint()
    ..color = const Color.fromARGB(0x2B, 0x41, 0x69, 0xE1)
    ..strokeWidth = 2
    ..strokeCap = StrokeCap.round;

  static final Paint _snowPaint =
      Paint()..color = const Color.fromARGB(0x2E, 0x90, 0xCA, 0xF9);

  /// Storm flash ramp: bright at the top of the flash, gone in 7% of the
  /// loop (~0.4s of a 6s cycle — rare enough to feel like weather, dim
  /// enough to stay decorative).
  static final List<Paint> _flashRamp = <Paint>[
    Paint()..color = const Color.fromARGB(0x2E, 0x9C, 0x27, 0xB0),
    Paint()..color = const Color.fromARGB(0x26, 0x9C, 0x27, 0xB0),
    Paint()..color = const Color.fromARGB(0x1C, 0x9C, 0x27, 0xB0),
    Paint()..color = const Color.fromARGB(0x14, 0x9C, 0x27, 0xB0),
    Paint()..color = const Color.fromARGB(0x0D, 0x9C, 0x27, 0xB0),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final b = boundary;
    if (b == null) {
      // Card mode: identical op sequence to the original painter —
      // scale 1.0 (`x * 1.0 == x` exactly) and the icon-tile anchor.
      _drawFx(canvas, size, 1.0, const Offset(_tileCx, _tileCy));
      return;
    }
    // Boundary mode: the caller's box IS the cell bbox, so clipping to the
    // local quad is clipping to the boundary, and size is the cell size.
    canvas.save();
    canvas.clipPath(b.clip);
    _drawFx(canvas, size, b.primScale,
        Offset(size.width / 2, size.height / 2));
    // The ≤0.18 alpha budget is about text legibility on the chip; on the
    // map the same colours read too faint, so the effect is painted a
    // second time — identical geometry at the same `t`, so the alphas
    // compound (~1.8×) into a slightly darker boundary presence.
    _drawFx(canvas, size, b.primScale,
        Offset(size.width / 2, size.height / 2));
    canvas.restore();
  }

  void _drawFx(Canvas canvas, Size size, double s, Offset clearAnchor) {
    final t = animation.value;
    switch (fx) {
      case WeatherFx.clear:
        _paintClear(canvas, t, s, clearAnchor);
        return;
      case WeatherFx.cloudy:
        _paintClouds(canvas, size, t, _cloudPaint, s);
        return;
      case WeatherFx.fog:
        _paintFog(canvas, size, t, _fogFor(s), s);
        return;
      case WeatherFx.drizzle:
        _paintDrops(canvas, size, t, 3, _drizzleFor(s), 4, 1.5, s);
        return;
      case WeatherFx.rain:
        _paintDrops(canvas, size, t, 5, _rainFor(s), 7, 3, s);
        return;
      case WeatherFx.snow:
        _paintSnow(canvas, size, t, s);
        return;
      case WeatherFx.storm:
        _paintStorm(canvas, size, t, s);
        return;
    }
  }

  /// Clear: one amber glow behind the icon tile (card) or at the cell
  /// centre (boundary), radius and alpha breathing together. Single layer —
  /// nothing stacks over the text.
  void _paintClear(Canvas canvas, double t, double s, Offset anchor) {
    final pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2);
    final step = (pulse * (_clearPulse.length - 1)).round();
    final radius = (9 + 7 * pulse) * s;
    canvas.drawCircle(anchor, radius, _clearPulse[step]);
  }

  /// Cloudy / storm base: two cloud silhouettes drifting across the canvas
  /// on offset phases (14s loop = barely-perceptible crawl). The silhouette
  /// is a fixed-size path, so it scales as a whole in boundary mode.
  void _paintClouds(
      Canvas canvas, Size size, double t, Paint paint, double s) {
    for (var i = 0; i < 2; i++) {
      final phase = (t + i * 0.5) % 1.0;
      final x = -45.0 * s + phase * (size.width + 90 * s);
      final y = size.height * (i == 0 ? 0.02 : 0.48);
      canvas.save();
      canvas.translate(x, y);
      if (s != 1.0) canvas.scale(s);
      canvas.drawPath(_cloudPath, paint);
      canvas.restore();
    }
  }

  /// Fog: three soft mist bands sliding gently out of phase.
  void _paintFog(Canvas canvas, Size size, double t, Paint paint, double s) {
    for (var i = 0; i < 3; i++) {
      final y = size.height * (0.2 + 0.3 * i);
      final dx = math.sin((t + i * 0.33) * math.pi * 2) * 14 * s;
      canvas.drawLine(
        Offset(-8 * s + dx, y),
        Offset(size.width * 0.65 + dx, y),
        paint,
      );
    }
  }

  /// Drizzle / rain: [count] drops falling on staggered phases, slightly
  /// slanted, wrapping from above the canvas.
  void _paintDrops(Canvas canvas, Size size, double t, int count, Paint paint,
      double length, double slant, double s) {
    for (var i = 0; i < count; i++) {
      final x = ((i + 0.5) / count) * size.width;
      final phase = (t + i * (0.6 / count)) % 1.0;
      final y = -10 * s + phase * (size.height + 20 * s);
      canvas.drawLine(
        Offset(x, y),
        Offset(x - slant * s, y + length * s),
        paint,
      );
    }
  }

  /// Snow: four flakes sinking while swaying side to side.
  void _paintSnow(Canvas canvas, Size size, double t, double s) {
    for (var i = 0; i < 4; i++) {
      final x = ((i + 0.5) / 4) * size.width +
          math.sin((t + i * 0.25) * math.pi * 2) * 6 * s;
      final phase = (t + i * 0.25) % 1.0;
      final y = -8 * s + phase * (size.height + 16 * s);
      canvas.drawCircle(
          Offset(x, y), (i.isEven ? 2.2 : 1.6) * s, _snowPaint);
    }
  }

  /// Storm: purple cloud drift plus one rare, dim flash at the loop head —
  /// a hint of lightning, never a strobe. The flash covers the whole
  /// canvas (chip tile or full cell).
  void _paintStorm(Canvas canvas, Size size, double t, double s) {
    _paintClouds(canvas, size, t, _stormCloudPaint, s);
    if (t < 0.07) {
      final idx = ((t / 0.07) * (_flashRamp.length - 1))
          .floor()
          .clamp(0, _flashRamp.length - 1);
      canvas.drawRect(
        Rect.fromLTWH(0, 0, size.width, size.height),
        _flashRamp[idx],
      );
    }
  }

  @override
  bool shouldRepaint(covariant _WeatherFxPainter old) =>
      old.fx != fx ||
      old.animation != animation ||
      old.boundary != boundary;
}

/// The weather chip's effect rendered at the scale of its map weather cell:
/// the same [_WeatherFxPainter] (same colours, same loop, same reduced-
/// motion freeze), clipped to the exact boundary polygon of [corners] and
/// stretched over its projected bbox — a larger spatial form of the card
/// visual that lives strictly inside the boundary.
///
/// Placement contract (`nearby_map_screen.dart`):
/// * Inserted as a `FlutterMap` child (a Stack child), so it returns a
///   [Positioned] box over the cell bbox and reprojects on camera change
///   through [MapCamera.maybeOf]'s inherited camera dependency.
/// * Caller wraps it in [TickerMode] (map ticker gate) and [IgnorePointer]
///   here steals no map gestures; [RepaintBoundary] keeps its 60fps
///   repaints to this box alone.
/// * Mounted only while toggled on (≤15s) and only while the cell still
///   exists in the weather grid — same lifetime rules as the chip's tap.
class WeatherBoundaryFx extends StatefulWidget {
  const WeatherBoundaryFx({
    super.key,
    required this.code,
    required this.corners,
  });

  /// WMO weather code of the cell — the same visual the chip shows.
  final int code;

  /// The cell's four corners (see `weatherCellCorners`), map order.
  final List<LatLng> corners;

  @override
  State<WeatherBoundaryFx> createState() => _WeatherBoundaryFxState();
}

class _WeatherBoundaryFxState extends State<WeatherBoundaryFx>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: fxDuration(weatherFxForCode(widget.code)),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncMotion();
  }

  /// Honour the OS "remove animations" setting — same contract as the
  /// chip: freeze on a representative static frame instead of looping.
  void _syncMotion() {
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
      _controller.value = 0.5;
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void didUpdateWidget(covariant WeatherBoundaryFx old) {
    super.didUpdateWidget(old);
    if (old.code != widget.code) {
      _controller.duration = fxDuration(weatherFxForCode(widget.code));
      if (MediaQuery.disableAnimationsOf(context)) {
        _controller.stop();
        _controller.value = 0.5;
      } else {
        _controller.repeat();
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final camera = MapCamera.maybeOf(context);
    if (camera == null || widget.corners.length != 4) {
      return const SizedBox.shrink();
    }

    // Project the cell corners through the live camera: handles zoom, pan
    // and map rotation, so the clip quad always matches the drawn boundary.
    var minX = double.infinity;
    var maxX = double.negativeInfinity;
    var minY = double.infinity;
    var maxY = double.negativeInfinity;
    final pts = <Offset>[];
    for (final c in widget.corners) {
      final p = camera.latLngToScreenPoint(c);
      pts.add(Offset(p.x, p.y));
      if (p.x < minX) minX = p.x;
      if (p.x > maxX) maxX = p.x;
      if (p.y < minY) minY = p.y;
      if (p.y > maxY) maxY = p.y;
    }
    final w = maxX - minX;
    final h = maxY - minY;
    if (!w.isFinite || !h.isFinite || w <= 0 || h <= 0) {
      return const SizedBox.shrink();
    }

    // Clip path = the cell quad in box-local coordinates (bbox origin).
    final clip = Path()..moveTo(pts[0].dx - minX, pts[0].dy - minY);
    for (var i = 1; i < pts.length; i++) {
      clip.lineTo(pts[i].dx - minX, pts[i].dy - minY);
    }
    clip.close();

    // Primitive scale: relative to the chip's own height; clamp keeps a
    // far-zoomed cell from turning drops into bars or flakes into blobs.
    final s = (math.min(w, h) / _chipHeight).clamp(1.0, 5.0);

    return Positioned(
      left: minX,
      top: minY,
      width: w,
      height: h,
      child: RepaintBoundary(
        child: IgnorePointer(
          child: CustomPaint(
            painter: _WeatherFxPainter(
              fx: weatherFxForCode(widget.code),
              animation: _controller,
              boundary: _BoundaryFxLayout(clip: clip, primScale: s),
            ),
          ),
        ),
      ),
    );
  }
}
