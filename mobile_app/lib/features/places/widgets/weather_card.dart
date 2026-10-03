import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../config/themes/app_theme.dart';
import '../../../core/services/localization_service.dart';

/// Weather-code effect groups for the weather chip's ambient animation.
///
/// The WMO groupings mirror `_weatherCodeToColor` in `nearby_map_screen.dart`
/// (clear 0, cloudy 1-3, fog 45-48, drizzle 51-57, rain 61-67 + 80-82,
/// snow 71-77 + 85-86, storm 95-99) so the chip always agrees with the
/// map's weather overlay colours.
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

/// Weather chip: a small white card (~160x48) showing temperature +
/// condition + area label, with a subtle condition-tied animation painted
/// behind the text.
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
/// Non-interactive by construction (wrap in [IgnorePointer] where it is
/// placed): map gestures fall straight through.
class WeatherChip extends StatefulWidget {
  const WeatherChip({
    super.key,
    required this.code,
    this.temperatureC,
    this.areaLabel,
  });

  /// WMO weather code (0-99).
  final int code;

  /// Degrees Celsius; null renders the condition label alone.
  final double? temperatureC;

  /// Line 2 (e.g. "Kathmandu"); null/empty omits the line entirely.
  final String? areaLabel;

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
              height: 48,
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
class _WeatherFxPainter extends CustomPainter {
  _WeatherFxPainter({required this.fx, required this.animation})
      : super(repaint: animation);

  final WeatherFx fx;
  final Animation<double> animation;

  // ── Shared, pre-allocated resources (one-time init, never mutated) ──

  /// Icon-tile centre in chip coordinates (10dp padding + half a 30dp tile).
  static const double _tileCx = 25;
  static const double _tileCy = 24;

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
    final t = animation.value;
    switch (fx) {
      case WeatherFx.clear:
        _paintClear(canvas, t);
        return;
      case WeatherFx.cloudy:
        _paintClouds(canvas, size, t, _cloudPaint);
        return;
      case WeatherFx.fog:
        _paintFog(canvas, size, t);
        return;
      case WeatherFx.drizzle:
        _paintDrops(canvas, size, t, 3, _drizzlePaint, 4, 1.5);
        return;
      case WeatherFx.rain:
        _paintDrops(canvas, size, t, 5, _rainPaint, 7, 3);
        return;
      case WeatherFx.snow:
        _paintSnow(canvas, size, t);
        return;
      case WeatherFx.storm:
        _paintStorm(canvas, size, t);
        return;
    }
  }

  /// Clear: one amber glow behind the icon tile, radius and alpha breathing
  /// together. Single layer — nothing stacks over the text.
  void _paintClear(Canvas canvas, double t) {
    final pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2);
    final step = (pulse * (_clearPulse.length - 1)).round();
    final radius = 9 + 7 * pulse;
    canvas.drawCircle(
      const Offset(_tileCx, _tileCy),
      radius,
      _clearPulse[step],
    );
  }

  /// Cloudy / storm base: two cloud silhouettes drifting across the chip on
  /// offset phases (14s loop = barely-perceptible crawl).
  void _paintClouds(Canvas canvas, Size size, double t, Paint paint) {
    for (var i = 0; i < 2; i++) {
      final phase = (t + i * 0.5) % 1.0;
      final x = -45.0 + phase * (size.width + 90);
      final y = size.height * (i == 0 ? 0.02 : 0.48);
      canvas.save();
      canvas.translate(x, y);
      canvas.drawPath(_cloudPath, paint);
      canvas.restore();
    }
  }

  /// Fog: three soft mist bands sliding gently out of phase.
  void _paintFog(Canvas canvas, Size size, double t) {
    for (var i = 0; i < 3; i++) {
      final y = size.height * (0.2 + 0.3 * i);
      final dx = math.sin((t + i * 0.33) * math.pi * 2) * 14;
      canvas.drawLine(
        Offset(-8 + dx, y),
        Offset(size.width * 0.65 + dx, y),
        _fogPaint,
      );
    }
  }

  /// Drizzle / rain: [count] drops falling on staggered phases, slightly
  /// slanted, wrapping from above the chip.
  void _paintDrops(Canvas canvas, Size size, double t, int count, Paint paint,
      double length, double slant) {
    for (var i = 0; i < count; i++) {
      final x = ((i + 0.5) / count) * size.width;
      final phase = (t + i * (0.6 / count)) % 1.0;
      final y = -10 + phase * (size.height + 20);
      canvas.drawLine(
        Offset(x, y),
        Offset(x - slant, y + length),
        paint,
      );
    }
  }

  /// Snow: four flakes sinking while swaying side to side.
  void _paintSnow(Canvas canvas, Size size, double t) {
    for (var i = 0; i < 4; i++) {
      final x = ((i + 0.5) / 4) * size.width +
          math.sin((t + i * 0.25) * math.pi * 2) * 6;
      final phase = (t + i * 0.25) % 1.0;
      final y = -8 + phase * (size.height + 16);
      canvas.drawCircle(Offset(x, y), i.isEven ? 2.2 : 1.6, _snowPaint);
    }
  }

  /// Storm: purple cloud drift plus one rare, dim flash at the loop head —
  /// a hint of lightning, never a strobe.
  void _paintStorm(Canvas canvas, Size size, double t) {
    _paintClouds(canvas, size, t, _stormCloudPaint);
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
      old.fx != fx || old.animation != animation;
}
