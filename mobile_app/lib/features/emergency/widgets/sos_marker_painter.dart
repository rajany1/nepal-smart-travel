import 'package:flutter/material.dart';

class SosMarkerPainter extends CustomPainter {
  final double animationValue;
  final double size;

  SosMarkerPainter({
    required this.animationValue,
    this.size = 60,
  });

  @override
  void paint(Canvas canvas, Size canvasSize) {
    final center = Offset(canvasSize.width / 2, canvasSize.height / 2);
    final dotRadius = size * 0.12;

    // Expanding wave ring
    final waveRadius = dotRadius + (size * 0.4) * animationValue;
    final waveOpacity = (1.0 - animationValue) * 0.6;
    final wavePaint = Paint()
      ..color = Colors.red.withOpacity(waveOpacity)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.5 - animationValue * 1.5;
    canvas.drawCircle(center, waveRadius, wavePaint);

    // Second wave (delayed phase)
    final wave2Value = (animationValue + 0.5) % 1.0;
    final wave2Radius = dotRadius + (size * 0.4) * wave2Value;
    final wave2Opacity = (1.0 - wave2Value) * 0.4;
    final wave2Paint = Paint()
      ..color = Colors.red.withOpacity(wave2Opacity)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.0 - wave2Value * 1.5;
    canvas.drawCircle(center, wave2Radius, wave2Paint);

    // Background glow
    final glowPaint = Paint()
      ..shader = RadialGradient(
        colors: [
          Colors.red.withOpacity(0.3),
          Colors.red.withOpacity(0.0),
        ],
      ).createShader(Rect.fromCircle(center: center, radius: dotRadius * 3));
    canvas.drawCircle(center, dotRadius * 3, glowPaint);

    // Center solid dot
    final dotPaint = Paint()
      ..color = Colors.red
      ..style = PaintingStyle.fill;
    canvas.drawCircle(center, dotRadius, dotPaint);

    // White border ring
    final borderPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawCircle(center, dotRadius, borderPaint);

    // SOS text
    final textPainter = TextPainter(
      text: const TextSpan(
        text: 'SOS',
        style: TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
        ),
      ),
      textDirection: TextDirection.ltr,
    );
    textPainter.layout();
    textPainter.paint(
      canvas,
      Offset(
        center.dx - textPainter.width / 2,
        center.dy - textPainter.height / 2,
      ),
    );
  }

  @override
  bool shouldRepaint(SosMarkerPainter oldDelegate) {
    return oldDelegate.animationValue != animationValue;
  }
}

class SosMarker extends StatefulWidget {
  final double latitude;
  final double longitude;
  final double? distanceKm;
  final int? durationSeconds;
  final String emergencyType;
  final VoidCallback? onTap;

  const SosMarker({
    super.key,
    required this.latitude,
    required this.longitude,
    this.distanceKm,
    this.durationSeconds,
    this.emergencyType = 'other',
    this.onTap,
  });

  @override
  State<SosMarker> createState() => _SosMarkerState();
}

class _SosMarkerState extends State<SosMarker>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: widget.onTap,
      child: SizedBox(
        width: 70,
        height: 70,
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            return CustomPaint(
              painter: SosMarkerPainter(
                animationValue: _controller.value,
                size: 70,
              ),
            );
          },
        ),
      ),
    );
  }
}
