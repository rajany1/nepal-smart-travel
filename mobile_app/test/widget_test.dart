import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nepal_smart_travel/main.dart';
import 'package:nepal_smart_travel/providers/auth_provider.dart';
import 'package:nepal_smart_travel/core/services/localization_service.dart';

void main() {
  testWidgets('App launches successfully', (WidgetTester tester) async {
    final authProvider = AuthProvider();
    final localizationService = LocalizationService();
    await tester.pumpWidget(NepalSmartTravelApp(
      authProvider: authProvider,
      localizationService: localizationService,
      navigatorKey: GlobalKey<NavigatorState>(),
    ));
    await tester.pump();
    expect(find.text('Nepal Smart Travel'), findsOneWidget);
  });
}
