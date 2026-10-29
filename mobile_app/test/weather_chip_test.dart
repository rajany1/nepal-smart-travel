// Widget tests for the WeatherChip "last updated" line.
//
// The chip watches LocalizationService for `context.t` and reads
// MediaQuery.disableAnimations to freeze its effect on a static frame —
// the harness provides both, so no tickers run and the assertions are
// deterministic.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:nepal_smart_travel/core/services/localization_service.dart';
import 'package:nepal_smart_travel/features/places/widgets/weather_card.dart';

Widget _host(Widget child, {bool use24HourFormat = false}) =>
    ChangeNotifierProvider<LocalizationService>(
      create: (_) => LocalizationService(),
      child: MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(
            disableAnimations: true,
            alwaysUse24HourFormat: use24HourFormat,
          ),
          child: child!,
        ),
        home: Scaffold(body: Center(child: child)),
      ),
    );

void main() {
  testWidgets('renders temp, area and updated time', (tester) async {
    await tester.pumpWidget(_host(WeatherChip(
      code: 0,
      temperatureC: 25,
      areaLabel: 'Kathmandu',
      updatedAt: DateTime(2026, 10, 17, 14, 30),
    )));

    expect(find.text('25°C Clear'), findsOneWidget);
    expect(find.text('Kathmandu'), findsOneWidget);
    expect(find.text('Updated 2:30 PM'), findsOneWidget);
  });

  testWidgets('omits the updated line without a timestamp', (tester) async {
    await tester.pumpWidget(_host(const WeatherChip(
      code: 0,
      temperatureC: 25,
      areaLabel: 'Kathmandu',
    )));

    expect(find.text('Kathmandu'), findsOneWidget);
    expect(find.textContaining('Updated'), findsNothing);
  });

  testWidgets('follows the device 24-hour clock setting', (tester) async {
    await tester.pumpWidget(_host(
      WeatherChip(
        code: 0,
        temperatureC: 25,
        updatedAt: DateTime(2026, 10, 17, 14, 30),
      ),
      use24HourFormat: true,
    ));

    expect(find.text('Updated 14:30'), findsOneWidget);
  });
}
