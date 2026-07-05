import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  AppTheme._();

  // === Standardized Text Sizes ===
  // Use these everywhere instead of hardcoded fontSize values
  static const double textXs = 10;    // micro labels, status badges
  static const double textSm = 12;    // captions, timestamps, secondary info
  static const double textBase = 14;  // body text, buttons, descriptions
  static const double textLg = 16;    // card titles, section headers
  static const double textXl = 18;    // screen headings
  static const double text2xl = 20;   // large headings
  static const double text3xl = 24;   // page titles

  // Colors
  static const Color primaryColor = Color(0xFF1B5E20);
  static const Color primaryLight = Color(0xFF4CAF50);
  static const Color primaryDark = Color(0xFF0D3310);
  static const Color secondaryColor = Color(0xFFFF8F00);
  static const Color accentColor = Color(0xFF00BFA5);
  static const Color errorColor = Color(0xFFD32F2F);
  static const Color warningColor = Color(0xFFFFA000);
  static const Color successColor = Color(0xFF388E3C);
  static const Color infoColor = Color(0xFF1976D2);

  // Neutral Colors
  static const Color backgroundColor = Color(0xFFF5F5F5);
  static const Color surfaceColor = Colors.white;
  static const Color darkSurfaceColor = Color(0xFF121212);
  static const Color textPrimary = Color(0xFF212121);
  static const Color textSecondary = Color(0xFF757575);
  static const Color dividerColor = Color(0xFFE0E0E0);

  // Brand Colors - Nepal Theme
  static const Color nepalRed = Color(0xFFDC143C);
  static const Color nepalBlue = Color(0xFF003893);
  static const Color himalayaWhite = Color(0xFFF5F5F5);
  static const Color forestGreen = Color(0xFF1B5E20);

  // Verification Tick Colors
  static const Color grayTick = Color(0xFF9E9E9E);
  static const Color greenTick = Color(0xFF4CAF50);
  static const Color blueTick = Color(0xFF2196F3);
  static const Color goldTick = Color(0xFFFFD700);
  static const Color diamondTick = Color(0xFFB388FF);

  // XP Level Colors
  static const Color explorerColor = Color(0xFF9E9E9E);
  static const Color contributorColor = Color(0xFFB0BEC5);
  static const Color trustedLocalColor = Color(0xFFFFD700);
  static const Color regionalGuideColor = Color(0xFFE0E0E0);
  static const Color communityExpertColor = Color(0xFFB388FF);

  // Report Category Colors
  static const Color roadColor = Color(0xFF795548);
  static const Color safetyColor = Color(0xFFD32F2F);
  static const Color weatherColor = Color(0xFF1976D2);
  static const Color transportColor = Color(0xFFF57C00);
  static const Color hiddenPlaceColor = Color(0xFF388E3C);
  static const Color serviceColor = Color(0xFF7B1FA2);
  static const Color eventColor = Color(0xFFE91E63);
  static const Color generalColor = Color(0xFF607D8B);

  // Map Markers Colors
  static const Color markerTourist = Color(0xFFE91E63);
  static const Color markerHotel = Color(0xFF9C27B0);
  static const Color markerFood = Color(0xFFFF5722);
  static const Color markerEmergency = Color(0xFFF44336);
  static const Color markerUtility = Color(0xFF3F51B5);
  static const Color markerTransport = Color(0xFFFF9800);
  static const Color markerActivity = Color(0xFF4CAF50);

  // Alert Severity Colors
  static const Color severityCritical = Color(0xFFB71C1C);
  static const Color severityHigh = Color(0xFFFF5722);
  static const Color severityMedium = Color(0xFFFF9800);
  static const Color severityLow = Color(0xFF4CAF50);
  static const Color severityInfo = Color(0xFF2196F3);

  // Emergency Service Colors
  static const Color ambulanceColor = Color(0xFFF44336);
  static const Color policeColor = Color(0xFF1565C0);
  static const Color hospitalColor = Color(0xFFE91E63);
  static const Color doctorColor = Color(0xFF4CAF50);
  static const Color sosColor = Color(0xFFFF5722);

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      primaryColor: primaryColor,
      scaffoldBackgroundColor: backgroundColor,
      colorScheme: const ColorScheme.light(
        primary: primaryColor,
        secondary: secondaryColor,
        error: errorColor,
        surface: surfaceColor,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: GoogleFonts.poppins(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: Colors.white,
        ),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: Colors.white,
        selectedItemColor: primaryColor,
        unselectedItemColor: textSecondary,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          elevation: 2,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
          textStyle: GoogleFonts.poppins(
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primaryColor,
          side: const BorderSide(color: primaryColor),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primaryColor,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: dividerColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: dividerColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: primaryColor, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: errorColor),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        labelStyle: GoogleFonts.poppins(color: textSecondary),
        hintStyle: GoogleFonts.poppins(color: textSecondary),
      ),
      cardTheme: CardTheme(
        elevation: 2,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        selectedColor: primaryLight,
        labelStyle: GoogleFonts.poppins(fontSize: 12),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
      ),
      dividerTheme: const DividerThemeData(
        color: dividerColor,
        thickness: 1,
      ),
      textTheme: GoogleFonts.poppinsTextTheme(
        const TextTheme(
          displayLarge: TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: textPrimary),
          displayMedium: TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: textPrimary),
          displaySmall: TextStyle(fontSize: text3xl, fontWeight: FontWeight.bold, color: textPrimary),
          headlineLarge: TextStyle(fontSize: text2xl, fontWeight: FontWeight.w600, color: textPrimary),
          headlineMedium: TextStyle(fontSize: text2xl, fontWeight: FontWeight.w600, color: textPrimary),
          headlineSmall: TextStyle(fontSize: textXl, fontWeight: FontWeight.w600, color: textPrimary),
          titleLarge: TextStyle(fontSize: textLg, fontWeight: FontWeight.w600, color: textPrimary),
          titleMedium: TextStyle(fontSize: textBase, fontWeight: FontWeight.w500, color: textPrimary),
          titleSmall: TextStyle(fontSize: textSm, fontWeight: FontWeight.w500, color: textPrimary),
          bodyLarge: TextStyle(fontSize: textLg, fontWeight: FontWeight.normal, color: textPrimary),
          bodyMedium: TextStyle(fontSize: textBase, fontWeight: FontWeight.normal, color: textPrimary),
          bodySmall: TextStyle(fontSize: textSm, fontWeight: FontWeight.normal, color: textSecondary),
          labelLarge: TextStyle(fontSize: textBase, fontWeight: FontWeight.w600, color: textPrimary),
          labelMedium: TextStyle(fontSize: textSm, fontWeight: FontWeight.w500, color: textPrimary),
          labelSmall: TextStyle(fontSize: textXs, fontWeight: FontWeight.w500, color: textSecondary),
        ),
      ),
    );
  }

  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      primaryColor: primaryLight,
      scaffoldBackgroundColor: darkSurfaceColor,
      colorScheme: const ColorScheme.dark(
        primary: primaryLight,
        secondary: secondaryColor,
        error: errorColor,
        surface: darkSurfaceColor,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: darkSurfaceColor,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: GoogleFonts.poppins(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: Colors.white,
        ),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: Color(0xFF1E1E1E),
        selectedItemColor: primaryLight,
        unselectedItemColor: Colors.grey,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryLight,
          foregroundColor: Colors.white,
          elevation: 2,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      ),
      cardTheme: CardTheme(
        elevation: 2,
        color: const Color(0xFF1E1E1E),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFF2C2C2C),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFF424242)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFF424242)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: primaryLight, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
    );
  }
}