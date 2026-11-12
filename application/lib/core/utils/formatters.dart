import 'package:intl/intl.dart';

import '../storage/preferences.dart';
import 'money.dart';

/// Locale-aware display formatting. Dates and times use the UI language;
/// digits follow the user's digit style (Western by default) regardless of
/// what intl would pick for the locale.
class Formatter {
  const Formatter({required this.languageCode, required this.digits});

  final String languageCode;
  final DigitStyle digits;

  static const _arabicIndic = [
    '٠',
    '١',
    '٢',
    '٣',
    '٤',
    '٥',
    '٦',
    '٧',
    '٨',
    '٩',
  ];

  /// Rewrites every digit of [text] to the selected style.
  String d(String text) {
    final buffer = StringBuffer();
    for (final rune in text.runes) {
      final char = String.fromCharCode(rune);
      final western = rune >= 0x30 && rune <= 0x39;
      final eastern = rune >= 0x660 && rune <= 0x669;
      if (digits == DigitStyle.arabicIndic && western) {
        buffer.write(_arabicIndic[rune - 0x30]);
      } else if (digits == DigitStyle.western && eastern) {
        buffer.write(String.fromCharCode(0x30 + rune - 0x660));
      } else if (char == '٫' && digits == DigitStyle.western) {
        buffer.write('.');
      } else if (char == '٬' && digits == DigitStyle.western) {
        buffer.write(',');
      } else {
        buffer.write(char);
      }
    }
    return buffer.toString();
  }

  String number(num value) =>
      d(NumberFormat.decimalPattern('en').format(value));

  /// API decimal strings ("23.00", "0.50") without trailing zeros.
  String decimal(String? value) {
    if (value == null || value.isEmpty) return d('0');
    var text = value;
    if (text.contains('.')) {
      text = text.replaceFirst(RegExp(r'0+$'), '');
      if (text.endsWith('.')) text = text.substring(0, text.length - 1);
    }
    return d(text);
  }

  /// Money from an API string, grouped, with the currency code.
  String money(String? amount, [String? currency]) {
    final value = Money.tryParse(amount);
    if (value == null) return d(amount ?? '—');
    final text = d(value.grouped());
    return currency == null || currency.isEmpty ? text : '$text $currency';
  }

  String date(DateTime? value) =>
      value == null ? '—' : d(DateFormat.yMMMd(languageCode).format(value));

  String dateLong(DateTime? value) => value == null
      ? '—'
      : d(DateFormat.yMMMMEEEEd(languageCode).format(value));

  String weekdayShort(DateTime value) =>
      DateFormat.E(languageCode).format(value);

  String dayMonth(DateTime value) =>
      d(DateFormat.MMMd(languageCode).format(value));

  String monthYear(DateTime value) =>
      d(DateFormat.yMMMM(languageCode).format(value));

  /// Device-local wall clock (24h, as the backend's time_format "H:i").
  String time(DateTime? value) =>
      value == null ? '--:--' : d(DateFormat.Hm(languageCode).format(value));

  String dateTime(DateTime? value) => value == null
      ? '—'
      : d(DateFormat.yMMMd(languageCode).add_Hm().format(value.toLocal()));

  /// "HH:mm" strings the API already localised to the branch timezone.
  String clock(String? hhmm) =>
      hhmm == null || hhmm.isEmpty ? '--:--' : d(hhmm);

  /// Minutes as "h:mm".
  String duration(int minutes) {
    final h = minutes ~/ 60;
    final m = (minutes % 60).toString().padLeft(2, '0');
    return d('$h:$m');
  }

  String fileSize(int bytes) {
    if (bytes < 1024) return d('$bytes B');
    if (bytes < 1024 * 1024) {
      return d('${(bytes / 1024).toStringAsFixed(0)} KB');
    }
    return d('${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB');
  }

  /// Parses an API date ("2026-11-12").
  static DateTime? parseDate(String? value) =>
      value == null || value.isEmpty ? null : DateTime.tryParse(value);

  /// "2026-11-12" for query strings and request bodies.
  static String apiDate(DateTime value) =>
      DateFormat('yyyy-MM-dd', 'en').format(value);

  static String apiMonth(DateTime value) =>
      DateFormat('yyyy-MM', 'en').format(value);

  /// ISO-8601 with the device offset (the API expects an offset in device_time).
  static String isoWithOffset(DateTime value) {
    final local = value.toLocal();
    final offset = local.timeZoneOffset;
    final sign = offset.isNegative ? '-' : '+';
    final abs = offset.abs();
    final hh = abs.inHours.toString().padLeft(2, '0');
    final mm = (abs.inMinutes % 60).toString().padLeft(2, '0');
    final base = DateFormat("yyyy-MM-dd'T'HH:mm:ss", 'en').format(local);
    return '$base$sign$hh:$mm';
  }
}
