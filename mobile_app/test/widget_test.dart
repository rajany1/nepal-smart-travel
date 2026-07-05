import 'package:flutter_test/flutter_test.dart';
import 'package:nepal_smart_travel/main.dart';
import 'package:nepal_smart_travel/providers/auth_provider.dart';

void main() {
  testWidgets('App launches successfully', (WidgetTester tester) async {
    final authProvider = AuthProvider();
    await tester.pumpWidget(NepalSmartTravelApp(authProvider: authProvider));
    await tester.pump();
    expect(find.text('Nepal Smart Travel'), findsOneWidget);
  });
}
