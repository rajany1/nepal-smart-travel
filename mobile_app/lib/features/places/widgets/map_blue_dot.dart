import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Google-Maps-style blue location dot: pulsing accuracy ring, soft shaded
/// light cone pointing at the current heading (travel direction / phone
/// rotation), and a white-ringed blue dot.
class MapBlueDot extends StatefulWidget {
  const MapBlueDot({
    super.key,
    required this.heading,
    required this.accuracyMeters,
    required this.zoom,
    required this.latitude,
  });

  /// Degrees 0-360, clockwise from north. Null when unknown (no cone).
  final double? heading;

  final double accuracyMeters;
  final double zoom;
  final double latitude;

  @override
  State<MapBlueDot> createState() => _MapBlueDotState();
}

class _MapBlueDotState extends State<MapBlueDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 3),
  )..repeat();

  double _displayHeading = 0;
  double _targetHeading = 0;
  bool _hasHeading = false;

  @override
  void didUpdateWidget(MapBlueDot old) {
    super.didUpdateWidget(old);
    final h = widget.heading;
    if (h == null) {
      _hasHeading = false;
      return;
    }
    if (!_hasHeading) {
      // First fix: snap so the cone doesn't spin around from 0.
      _displayHeading = h;
      _hasHeading = true;
    }
    _targetHeading = h;
  }

  static double _shortestArc(double from, double to) {
    var d = (to - from) % 360;
    if (d > 180) d -= 360;
    if (d < -180) d += 360;
    return d;
  }

  static double _normalize(double deg) => (deg % 360 + 360) % 360;

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  static double _metersPerPixel(double lat, double zoom) =>
      156543.03392 * math.cos(lat * math.pi / 180) / math.pow(2, zoom);

  @override
  Widget build(BuildContext context) {
    final mpp = _metersPerPixel(widget.latitude, widget.zoom);
    final accRadius = (widget.accuracyMeters / mpp).clamp(16.0, 200.0);
    final coneLen = math.max(accRadius, 40.0) + 10.0;
    final size =
        (math.max(accRadius, coneLen) * 2 + 20).clamp(150.0, 500.0);

    return AnimatedBuilder(
      animation: _pulse,
      builder: (context, _) {
        if (_hasHeading) {
          // Per-frame exponential chase (~60fps): the light rotates smoothly
          // and small sensor jitter is damped, while fast rotations follow
          // quickly. Updates here are idempotent.
          final arc = _shortestArc(_displayHeading, _targetHeading);
          _displayHeading = _normalize(_displayHeading + arc * 0.12);
        }
        return CustomPaint(
          size: Size.square(size),
          painter: _BlueDotPainter(
            heading: _hasHeading ? _displayHeading : null,
            accuracyRadius: accRadius,
            coneLength: coneLen,
            pulseT: _pulse.value,
          ),
        );
      },
    );
  }
}

class _BlueDotPainter extends CustomPainter {
  const _BlueDotPainter({
    required this.heading,
    required this.accuracyRadius,
    required this.coneLength,
    required this.pulseT,
  });

  final double? heading;
  final double accuracyRadius;
  final double coneLength;
  final double pulseT;

  static const Color _blue = Color(0xFF4285F4);

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);

    // Pulsing accuracy ring (breathes outward like Google Maps).
    final ringR = accuracyRadius * (1 + pulseT * 0.3);
    canvas.drawCircle(
      center,
      ringR,
      Paint()
        ..color = _blue.withOpacity((1 - pulseT) * 0.28)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5,
    );

    // Accuracy fill.
    canvas.drawCircle(
      center,
      accuracyRadius,
      Paint()..color = _blue.withOpacity(0.10),
    );

    // Heading light cone: soft shaded beam like a torch light.
    final h = heading;
    if (h != null) {
      final a0 = (h - 22) * math.pi / 180 - math.pi / 2;
      final sweep = 44 * math.pi / 180;
      final coneRect = Rect.fromCircle(center: center, radius: coneLength);
      final conePath = Path()
        ..moveTo(center.dx, center.dy)
        ..arcTo(coneRect, a0, sweep, false)
        ..close();
      canvas.save();
      canvas.clipPath(conePath);
      canvas.drawCircle(
        center,
        coneLength,
        Paint()
          ..shader = RadialGradient(
            center: Alignment.center,
            radius: 1,
            colors: [
              _blue.withOpacity(0.34),
              _blue.withOpacity(0.10),
              _blue.withOpacity(0.0),
            ],
            stops: const [0.0, 0.55, 1.0],
          ).createShader(coneRect),
      );
      canvas.restore();
    }

    // White ring + blue dot.
    canvas.drawCircle(center, 11.5, Paint()..color = Colors.white);
    canvas.drawCircle(center, 9.0, Paint()..color = _blue);

    // Subtle glossy highlight so the dot reads as a "light".
    canvas.drawCircle(
      center,
      9.0,
      Paint()
        ..shader = RadialGradient(
          center: const Alignment(-0.45, -0.45),
          radius: 1.1,
          colors: [
            Colors.white.withOpacity(0.6),
            Colors.white.withOpacity(0.0),
          ],
        ).createShader(Rect.fromCircle(center: center, radius: 9.0)),
    );
  }

  @override
  bool shouldRepaint(_BlueDotPainter old) =>
      old.heading != heading ||
      old.accuracyRadius != accuracyRadius ||
      old.coneLength != coneLength ||
      old.pulseT != pulseT;
}
