import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

/// Builds the polylines for one direction route, splitting it into solid
/// road segments and dashed, slightly curved off-road legs (points where the
/// origin/destination sit away from the road network).
///
/// [points] and [offRoad] are parallel lists produced by the directions API.
/// Off-road legs are flagged on single points (the raw origin/destination),
/// so each off-road run is extended into the adjacent road point(s) to give
/// it a visible line.
List<Polyline> buildRoutePolylines(
  List<LatLng> points,
  List<bool> offRoad, {
  required Color color,
  required double strokeWidth,
}) {
  final polylines = <Polyline>[];
  if (points.length < 2) {
    if (points.length == 1) {
      polylines.add(Polyline(
        points: points,
        color: color,
        strokeWidth: strokeWidth,
      ));
    }
    return polylines;
  }

  // Maximal runs of the same flag: (start, endExclusive, isOffRoad)
  final runs = <(int, int, bool)>[];
  var i = 0;
  while (i < points.length) {
    final isOff = offRoad[i];
    var j = i + 1;
    while (j < points.length && offRoad[j] == isOff) {
      j++;
    }
    runs.add((i, j, isOff));
    i = j;
  }

  for (final (start, end, isOff) in runs) {
    var segStart = start;
    var segEnd = end;
    if (isOff) {
      if (segStart > 0) segStart--;
      if (segEnd < points.length) segEnd++;
    }
    if (segEnd - segStart > 1) {
      final segment = points.sublist(segStart, segEnd);
      if (isOff) {
        polylines.add(Polyline(
          points: _bendOffRoad(segment),
          color: color.withOpacity(0.55),
          strokeWidth: math.max(strokeWidth - 1, 2),
          pattern: StrokePattern.dashed(segments: const [12.0, 8.0]),
        ));
      } else {
        polylines.add(Polyline(
          points: segment,
          color: color,
          strokeWidth: strokeWidth,
        ));
      }
    }
  }
  return polylines;
}

/// Gives an off-road leg a gentle curve so it reads as a walking path
/// rather than a rigid straight line.
List<LatLng> _bendOffRoad(List<LatLng> segment) {
  if (segment.length < 2) return segment;
  final out = <LatLng>[segment.first];
  for (var k = 0; k < segment.length - 1; k++) {
    final a = segment[k];
    final b = segment[k + 1];
    final dLat = b.latitude - a.latitude;
    final dLng = b.longitude - a.longitude;
    final len = math.sqrt(dLat * dLat + dLng * dLng);
    if (len > 1e-9) {
      final bend = len * 0.15;
      out.add(LatLng(
        (a.latitude + b.latitude) / 2 - (dLng / len) * bend,
        (a.longitude + b.longitude) / 2 + (dLat / len) * bend,
      ));
    }
    out.add(b);
  }
  return out;
}