import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/session/session.dart';
import '../../core/theme/app_typography.dart';

/// Draws the signed-in officer's Service Number faintly across every screen.
///
/// The OS takes screenshots and screen recordings itself, so the app cannot
/// stamp them after the fact. Keeping the number permanently on screen means
/// any capture of a signed-in screen carries the Service Number of the officer
/// who took it.
class ServiceNumberWatermark extends StatelessWidget {
  const ServiceNumberWatermark({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String?>(
      valueListenable: currentServiceNumber,
      child: child,
      builder: (context, serviceNumber, child) {
        if (serviceNumber == null || serviceNumber.isEmpty) return child!;
        return Stack(
          fit: StackFit.expand,
          children: [
            child!,
            IgnorePointer(
              child: CustomPaint(
                painter: WatermarkPainter('NIS  •  Service No. $serviceNumber'),
              ),
            ),
          ],
        );
      },
    );
  }
}

class WatermarkPainter extends CustomPainter {
  WatermarkPainter(this.text);

  final String text;

  // Mid grey at low opacity reads on both light and dark surfaces.
  static const _color = Color(0x26808080);

  @override
  void paint(Canvas canvas, Size size) {
    final painter = TextPainter(
      text: TextSpan(
        text: text,
        style: AppTypography.label.copyWith(color: _color, fontWeight: FontWeight.w700),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    const gapX = 56.0;
    const gapY = 90.0;
    final stepX = painter.width + gapX;
    final diagonal = math.sqrt(size.width * size.width + size.height * size.height);

    canvas.save();
    canvas.translate(size.width / 2, size.height / 2);
    canvas.rotate(-math.pi / 6);
    var row = 0;
    for (var y = -diagonal / 2; y < diagonal / 2; y += gapY) {
      final offset = (row++ % 2) * stepX / 2; // stagger alternate rows
      for (var x = -diagonal / 2 - offset; x < diagonal / 2; x += stepX) {
        painter.paint(canvas, Offset(x, y));
      }
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(WatermarkPainter oldDelegate) => oldDelegate.text != text;
}
