import 'package:flutter/material.dart';

import 'app_tokens.dart';
import 'palette.dart';

/// Radii, sizes and spacing from DESIGN_REFERENCE "Sizes" / "Flutter (390×844)".
abstract final class AppSizes {
  static const radiusXs = 4.0;
  static const radiusSm = 6.0;
  static const radiusMd = 8.0;
  static const radiusLg = 12.0;
  static const radiusXl = 16.0;
  static const radius2xl = 24.0;

  static const controlSm = 32.0;
  static const controlMd = 40.0;
  static const controlLg = 48.0;

  static const pagePadding = 16.0;
  static const gap = 16.0;
  static const appBarHeight = 56.0;
  static const bottomNavHeight = 72.0;

  static const attendanceButton = 200.0;
  static const attendanceHalo = 248.0;
}

/// Cairo type scale (size / line height / weight).
abstract final class AppType {
  static const family = 'Cairo';

  static TextStyle _s(double size, double height, FontWeight weight) =>
      TextStyle(
        fontFamily: family,
        fontSize: size,
        height: height / size,
        fontWeight: weight,
      );

  static final headingH1 = _s(30, 46, FontWeight.w700);
  static final headingH2 = _s(24, 38, FontWeight.w600);
  static final headingH3 = _s(20, 32, FontWeight.w600);
  static final headingH4 = _s(18, 30, FontWeight.w600);
  static final headingH5 = _s(16, 26, FontWeight.w600);
  static final bodyLg = _s(16, 28, FontWeight.w400);
  static final bodyMd = _s(14, 24, FontWeight.w400);
  static final bodySm = _s(13, 22, FontWeight.w400);
  static final labelLg = _s(16, 26, FontWeight.w500);
  static final labelMd = _s(14, 22, FontWeight.w500);
  static final labelSm = _s(12, 18, FontWeight.w500);
  static final captionMd = _s(12, 18, FontWeight.w400);
  static final captionSm = _s(11, 16, FontWeight.w400);
  static final numericXl = _s(32, 44, FontWeight.w700);
  static final numericLg = _s(24, 34, FontWeight.w600);
  static final numericMd = _s(14, 22, FontWeight.w600);
}

/// Shadows in rgb(14,19,28).
abstract final class AppShadows {
  static List<BoxShadow> xs = [
    BoxShadow(
      color: Palette.shadow.withValues(alpha: 0.06),
      offset: const Offset(0, 1),
      blurRadius: 2,
    ),
  ];
  static List<BoxShadow> md = [
    BoxShadow(
      color: Palette.shadow.withValues(alpha: 0.08),
      offset: const Offset(0, 4),
      blurRadius: 12,
    ),
    BoxShadow(
      color: Palette.shadow.withValues(alpha: 0.04),
      offset: const Offset(0, 2),
      blurRadius: 4,
    ),
  ];
}

abstract final class AppTheme {
  static ThemeData light() => _build(AppTokens.light, Brightness.light);
  static ThemeData dark() => _build(AppTokens.dark, Brightness.dark);

  static ThemeData _build(AppTokens t, Brightness brightness) {
    final scheme = ColorScheme(
      brightness: brightness,
      primary: t.actionPrimary,
      onPrimary: t.textOnPrimary,
      primaryContainer: t.actionPrimarySubtle,
      onPrimaryContainer: t.textLink,
      secondary: t.accent,
      onSecondary: Palette.neutral900,
      secondaryContainer: t.accentSubtle,
      onSecondaryContainer: t.textPrimary,
      error: t.actionDanger,
      onError: Palette.neutral0,
      errorContainer: t.tone(Tone.danger).bg,
      onErrorContainer: t.tone(Tone.danger).fg,
      surface: t.surfaceDefault,
      onSurface: t.textPrimary,
      onSurfaceVariant: t.textSecondary,
      surfaceContainerLowest: t.surfaceDefault,
      surfaceContainerLow: t.surfaceSubtle,
      surfaceContainer: t.surfaceSubtle,
      surfaceContainerHigh: t.surfaceMuted,
      surfaceContainerHighest: t.surfaceMuted,
      outline: t.borderDefault,
      outlineVariant: t.borderSubtle,
      inverseSurface: brightness == Brightness.light
          ? Palette.neutral900
          : Palette.neutral0,
      onInverseSurface: brightness == Brightness.light
          ? Palette.neutral0
          : Palette.neutral900,
      shadow: Palette.shadow,
      scrim: Palette.neutral950,
    );

    final text = TextTheme(
      displayLarge: AppType.numericXl,
      headlineLarge: AppType.headingH1,
      headlineMedium: AppType.headingH2,
      headlineSmall: AppType.headingH3,
      titleLarge: AppType.headingH4,
      titleMedium: AppType.headingH5,
      titleSmall: AppType.labelMd,
      bodyLarge: AppType.bodyLg,
      bodyMedium: AppType.bodyMd,
      bodySmall: AppType.captionMd,
      labelLarge: AppType.labelMd,
      labelMedium: AppType.labelSm,
      labelSmall: AppType.captionSm,
    ).apply(bodyColor: t.textPrimary, displayColor: t.textPrimary);

    OutlineInputBorder border(Color color, [double width = 1]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusMd),
          borderSide: BorderSide(color: color, width: width),
        );

    final buttonShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppSizes.radiusMd),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      fontFamily: AppType.family,
      textTheme: text,
      scaffoldBackgroundColor: t.surfacePage,
      extensions: [t],
      appBarTheme: AppBarTheme(
        backgroundColor: t.surfaceDefault,
        foregroundColor: t.textPrimary,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: true,
        toolbarHeight: AppSizes.appBarHeight,
        titleTextStyle: AppType.headingH5.copyWith(color: t.textPrimary),
      ),
      cardTheme: CardThemeData(
        color: t.surfaceDefault,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusXl),
          side: BorderSide(color: t.borderSubtle),
        ),
      ),
      dividerTheme: DividerThemeData(color: t.borderSubtle, space: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: t.surfaceDefault,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 12,
          vertical: 12,
        ),
        border: border(t.borderDefault),
        enabledBorder: border(t.borderDefault),
        focusedBorder: border(t.borderFocus, 1.5),
        errorBorder: border(t.actionDanger),
        focusedErrorBorder: border(t.actionDanger, 1.5),
        labelStyle: AppType.labelMd.copyWith(color: t.textSecondary),
        hintStyle: AppType.bodyMd.copyWith(color: t.textTertiary),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, AppSizes.controlLg),
          shape: buttonShape,
          textStyle: AppType.labelLg,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, AppSizes.controlLg),
          shape: buttonShape,
          side: BorderSide(color: t.borderDefault),
          foregroundColor: t.textPrimary,
          textStyle: AppType.labelLg,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: t.textLink,
          shape: buttonShape,
          textStyle: AppType.labelMd,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: AppSizes.bottomNavHeight,
        backgroundColor: t.surfaceDefault,
        surfaceTintColor: Colors.transparent,
        indicatorColor: t.actionPrimarySubtle,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => AppType.labelSm.copyWith(
            color: states.contains(WidgetState.selected)
                ? t.actionPrimary
                : t.textTertiary,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            size: 24,
            color: states.contains(WidgetState.selected)
                ? t.actionPrimary
                : t.textTertiary,
          ),
        ),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusLg),
        ),
        iconColor: t.textSecondary,
        titleTextStyle: AppType.labelMd.copyWith(color: t.textPrimary),
        subtitleTextStyle: AppType.captionMd.copyWith(color: t.textSecondary),
      ),
      chipTheme: ChipThemeData(
        labelStyle: AppType.labelSm.copyWith(color: t.textPrimary),
        side: BorderSide(color: t.borderDefault),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusMd),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        contentTextStyle: AppType.bodyMd.copyWith(
          color: scheme.onInverseSurface,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusLg),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: t.surfaceDefault,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppSizes.radiusXl),
          ),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: t.surfaceDefault,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppSizes.radiusXl),
        ),
      ),
    );
  }
}
