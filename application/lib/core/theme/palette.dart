import 'package:flutter/painting.dart';

/// Colour primitives from DESIGN_REFERENCE.md (Figma file F6GhAOTTYlI93nUtDf1255).
/// Do not add colours that are not in the design reference.
abstract final class Palette {
  // primary
  static const primary50 = Color(0xFFEEFBF8);
  static const primary100 = Color(0xFFD5F5EE);
  static const primary200 = Color(0xFFADEADF);
  static const primary300 = Color(0xFF77D8CB);
  static const primary400 = Color(0xFF41BDB0);
  static const primary500 = Color(0xFF1FA198);
  static const primary600 = Color(0xFF12827D);
  static const primary700 = Color(0xFF116866);
  static const primary800 = Color(0xFF125353);
  static const primary900 = Color(0xFF134545);
  static const primary950 = Color(0xFF06292B);

  // neutral
  static const neutral0 = Color(0xFFFFFFFF);
  static const neutral25 = Color(0xFFFAFBFC);
  static const neutral50 = Color(0xFFF5F7F9);
  static const neutral100 = Color(0xFFECEFF3);
  static const neutral200 = Color(0xFFDDE2E9);
  static const neutral300 = Color(0xFFC4CCD7);
  static const neutral400 = Color(0xFF97A3B4);
  static const neutral500 = Color(0xFF6B788C);
  static const neutral600 = Color(0xFF4D596C);
  static const neutral700 = Color(0xFF3A4455);
  static const neutral800 = Color(0xFF252D3B);
  static const neutral900 = Color(0xFF171D29);
  static const neutral950 = Color(0xFF0E131C);

  // accent
  static const accent50 = Color(0xFFFFF8EB);
  static const accent200 = Color(0xFFFDD68A);
  static const accent300 = Color(0xFFFBBB4D);
  static const accent400 = Color(0xFFF8A024);
  static const accent500 = Color(0xFFEE820C);
  static const accent600 = Color(0xFFD26107);
  static const accent700 = Color(0xFFAE440A);
  static const accent800 = Color(0xFF8D350F);
  static const accent950 = Color(0xFF431605);

  // green
  static const green50 = Color(0xFFECFDF3);
  static const green200 = Color(0xFFA6F4C5);
  static const green300 = Color(0xFF6CE9A6);
  static const green400 = Color(0xFF32D583);
  static const green500 = Color(0xFF12B76A);
  static const green600 = Color(0xFF039855);
  static const green700 = Color(0xFF027A48);
  static const green800 = Color(0xFF05603A);
  static const green950 = Color(0xFF053321);

  // red
  static const red50 = Color(0xFFFEF3F2);
  static const red200 = Color(0xFFFECDCA);
  static const red300 = Color(0xFFFDA29B);
  static const red400 = Color(0xFFF97066);
  static const red500 = Color(0xFFF04438);
  static const red600 = Color(0xFFD92D20);
  static const red700 = Color(0xFFB42318);
  static const red800 = Color(0xFF912018);
  static const red950 = Color(0xFF55160C);

  // blue
  static const blue50 = Color(0xFFEFF8FF);
  static const blue200 = Color(0xFFB2DDFF);
  static const blue300 = Color(0xFF84CAFF);
  static const blue400 = Color(0xFF53B1FD);
  static const blue500 = Color(0xFF2E90FA);
  static const blue600 = Color(0xFF1570EF);
  static const blue700 = Color(0xFF175CD3);
  static const blue800 = Color(0xFF1849A9);
  static const blue950 = Color(0xFF102A56);

  // orange
  static const orange50 = Color(0xFFFFF6ED);
  static const orange300 = Color(0xFFFEB273);
  static const orange400 = Color(0xFFFD853A);
  static const orange500 = Color(0xFFFB6514);
  static const orange700 = Color(0xFFC4320A);
  static const orange950 = Color(0xFF511C10);

  // violet
  static const violet50 = Color(0xFFF4F3FF);
  static const violet300 = Color(0xFFBDB4FE);
  static const violet400 = Color(0xFF9B8AFB);
  static const violet500 = Color(0xFF7A5AF8);
  static const violet700 = Color(0xFF5925DC);
  static const violet950 = Color(0xFF27115F);

  /// Shadow colour rgb(14,19,28).
  static const shadow = Color(0xFF0E131C);
}
