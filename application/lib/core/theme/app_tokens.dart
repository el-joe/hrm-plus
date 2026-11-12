import 'package:flutter/material.dart';

import 'palette.dart';

/// A feedback / status colour triple.
@immutable
class ToneColors {
  const ToneColors({required this.bg, required this.fg, required this.solid});

  final Color bg;
  final Color fg;
  final Color solid;
}

enum Tone { success, warning, danger, info, neutral, brand, violet, orange }

/// Semantic tokens (DESIGN_REFERENCE "Semantic tokens"), light and dark.
@immutable
class AppTokens extends ThemeExtension<AppTokens> {
  const AppTokens({
    required this.surfacePage,
    required this.surfaceDefault,
    required this.surfaceSubtle,
    required this.surfaceMuted,
    required this.surfaceRaised,
    required this.surfaceBrand,
    required this.surfaceBrandStrong,
    required this.borderSubtle,
    required this.borderDefault,
    required this.borderStrong,
    required this.borderFocus,
    required this.textPrimary,
    required this.textSecondary,
    required this.textTertiary,
    required this.textDisabled,
    required this.textOnPrimary,
    required this.textLink,
    required this.textOnBrandMuted,
    required this.actionPrimary,
    required this.actionPrimarySubtle,
    required this.actionDanger,
    required this.accent,
    required this.accentSubtle,
    required this.chart,
    required this.tones,
  });

  final Color surfacePage;
  final Color surfaceDefault;
  final Color surfaceSubtle;
  final Color surfaceMuted;
  final Color surfaceRaised;
  final Color surfaceBrand;
  final Color surfaceBrandStrong;
  final Color borderSubtle;
  final Color borderDefault;
  final Color borderStrong;
  final Color borderFocus;
  final Color textPrimary;
  final Color textSecondary;
  final Color textTertiary;
  final Color textDisabled;
  final Color textOnPrimary;
  final Color textLink;
  final Color textOnBrandMuted;
  final Color actionPrimary;
  final Color actionPrimarySubtle;
  final Color actionDanger;
  final Color accent;
  final Color accentSubtle;
  final List<Color> chart;
  final Map<Tone, ToneColors> tones;

  ToneColors tone(Tone tone) => tones[tone]!;

  /// `chart-1` … `chart-6` (leave type colours from the API).
  Color chartColor(String? key, [int fallbackIndex = 0]) {
    final match = RegExp(r'chart-(\d)').firstMatch(key ?? '');
    final index = match == null
        ? fallbackIndex
        : (int.parse(match.group(1)!) - 1);
    return chart[index.clamp(0, chart.length - 1)];
  }

  static const light = AppTokens(
    surfacePage: Palette.neutral50,
    surfaceDefault: Palette.neutral0,
    surfaceSubtle: Palette.neutral50,
    surfaceMuted: Palette.neutral100,
    surfaceRaised: Palette.neutral0,
    surfaceBrand: Palette.primary700,
    surfaceBrandStrong: Palette.primary900,
    borderSubtle: Palette.neutral100,
    borderDefault: Palette.neutral200,
    borderStrong: Palette.neutral300,
    borderFocus: Palette.primary500,
    textPrimary: Palette.neutral900,
    textSecondary: Palette.neutral600,
    textTertiary: Palette.neutral500,
    textDisabled: Palette.neutral400,
    textOnPrimary: Palette.neutral0,
    textLink: Palette.primary700,
    textOnBrandMuted: Palette.primary100,
    actionPrimary: Palette.primary600,
    actionPrimarySubtle: Palette.primary50,
    actionDanger: Palette.red600,
    accent: Palette.accent500,
    accentSubtle: Palette.accent50,
    chart: [
      Palette.primary500,
      Palette.accent400,
      Palette.blue500,
      Palette.violet500,
      Palette.orange500,
      Palette.neutral400,
    ],
    tones: {
      Tone.success: ToneColors(
        bg: Palette.green50,
        fg: Palette.green700,
        solid: Palette.green600,
      ),
      Tone.warning: ToneColors(
        bg: Palette.accent50,
        fg: Palette.accent700,
        solid: Palette.accent600,
      ),
      Tone.danger: ToneColors(
        bg: Palette.red50,
        fg: Palette.red700,
        solid: Palette.red600,
      ),
      Tone.info: ToneColors(
        bg: Palette.blue50,
        fg: Palette.blue700,
        solid: Palette.blue600,
      ),
      Tone.neutral: ToneColors(
        bg: Palette.neutral100,
        fg: Palette.neutral700,
        solid: Palette.neutral500,
      ),
      Tone.brand: ToneColors(
        bg: Palette.primary50,
        fg: Palette.primary700,
        solid: Palette.primary500,
      ),
      Tone.violet: ToneColors(
        bg: Palette.violet50,
        fg: Palette.violet700,
        solid: Palette.violet500,
      ),
      Tone.orange: ToneColors(
        bg: Palette.orange50,
        fg: Palette.orange700,
        solid: Palette.orange500,
      ),
    },
  );

  static const dark = AppTokens(
    surfacePage: Palette.neutral950,
    surfaceDefault: Palette.neutral900,
    surfaceSubtle: Palette.neutral800,
    surfaceMuted: Palette.neutral700,
    surfaceRaised: Palette.neutral800,
    surfaceBrand: Palette.primary900,
    surfaceBrandStrong: Palette.primary950,
    borderSubtle: Palette.neutral800,
    borderDefault: Palette.neutral700,
    borderStrong: Palette.neutral600,
    borderFocus: Palette.primary400,
    textPrimary: Palette.neutral25,
    textSecondary: Palette.neutral300,
    textTertiary: Palette.neutral400,
    textDisabled: Palette.neutral600,
    textOnPrimary: Palette.neutral0,
    textLink: Palette.primary300,
    textOnBrandMuted: Palette.primary200,
    actionPrimary: Palette.primary500,
    actionPrimarySubtle: Palette.primary950,
    actionDanger: Palette.red500,
    accent: Palette.accent400,
    accentSubtle: Palette.accent950,
    chart: [
      Palette.primary400,
      Palette.accent300,
      Palette.blue400,
      Palette.violet400,
      Palette.orange400,
      Palette.neutral500,
    ],
    tones: {
      Tone.success: ToneColors(
        bg: Palette.green950,
        fg: Palette.green300,
        solid: Palette.green500,
      ),
      Tone.warning: ToneColors(
        bg: Palette.accent950,
        fg: Palette.accent300,
        solid: Palette.accent500,
      ),
      Tone.danger: ToneColors(
        bg: Palette.red950,
        fg: Palette.red300,
        solid: Palette.red500,
      ),
      Tone.info: ToneColors(
        bg: Palette.blue950,
        fg: Palette.blue300,
        solid: Palette.blue500,
      ),
      Tone.neutral: ToneColors(
        bg: Palette.neutral700,
        fg: Palette.neutral200,
        solid: Palette.neutral400,
      ),
      Tone.brand: ToneColors(
        bg: Palette.primary950,
        fg: Palette.primary300,
        solid: Palette.primary400,
      ),
      Tone.violet: ToneColors(
        bg: Palette.violet950,
        fg: Palette.violet300,
        solid: Palette.violet400,
      ),
      Tone.orange: ToneColors(
        bg: Palette.orange950,
        fg: Palette.orange300,
        solid: Palette.orange400,
      ),
    },
  );

  @override
  AppTokens copyWith() => this;

  @override
  AppTokens lerp(covariant AppTokens? other, double t) =>
      t < 0.5 ? this : (other ?? this);
}

extension AppTokensContext on BuildContext {
  AppTokens get tokens => Theme.of(this).extension<AppTokens>()!;
}

/// Maps API status values to the design's status badge tones.
Tone toneForStatus(String? status) {
  switch (status) {
    case 'present':
    case 'accepted':
    case 'paid':
    case 'completed':
    case 'resolved':
    case 'active':
    case 'enrolled':
      return Tone.success;
    case 'approved':
    case 'finalized':
      return Tone.brand;
    case 'absent':
    case 'rejected':
    case 'cancelled':
    case 'expired':
    case 'failed':
      return Tone.danger;
    case 'late':
    case 'incomplete':
      return Tone.orange;
    case 'on_leave':
      return Tone.violet;
    case 'pending':
    case 'flagged':
    case 'awaiting_employee':
    case 'in_progress':
    case 'submitted':
    case 'partially_paid':
    case 'cancellation_requested':
      return Tone.warning;
    case 'holiday':
    case 'open':
    case 'info':
      return Tone.info;
    default:
      return Tone.neutral;
  }
}
