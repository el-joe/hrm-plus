/// An exact decimal amount parsed from the API's string money values
/// ("13396.25"). Stored as an integer number of minor units — never a double.
class Money implements Comparable<Money> {
  const Money._(this.minor, this.scale);

  /// Parses "1234.5", "-12.00", "0". Returns null for anything else.
  static Money? tryParse(String? value, {int scale = 2}) {
    if (value == null) return null;
    final text = value.trim().replaceAll(',', '');
    final match = RegExp(r'^(-)?(\d+)(?:\.(\d+))?$').firstMatch(text);
    if (match == null) return null;
    final negative = match.group(1) != null;
    final whole = match.group(2)!;
    var fraction = match.group(3) ?? '';
    if (fraction.length > scale) {
      fraction = fraction.substring(0, scale); // the API never sends more
    }
    fraction = fraction.padRight(scale, '0');
    final minor = BigInt.parse('$whole$fraction');
    return Money._(negative ? -minor : minor, scale);
  }

  static Money parse(String? value, {int scale = 2}) =>
      tryParse(value, scale: scale) ?? Money._(BigInt.zero, scale);

  static Money zero([int scale = 2]) => Money._(BigInt.zero, scale);

  final BigInt minor;
  final int scale;

  bool get isNegative => minor.isNegative;
  bool get isZero => minor == BigInt.zero;

  Money operator +(Money other) => Money._(minor + other.minor, scale);
  Money operator -(Money other) => Money._(minor - other.minor, scale);

  /// Share of [total] in 0..1 — for chart proportions only, never for amounts.
  double ratioOf(Money total) {
    if (total.minor == BigInt.zero) return 0;
    return minor.toDouble() / total.minor.toDouble();
  }

  /// Plain decimal string ("13396.25") for sending back to the API.
  String toApiString() {
    final digits = minor.abs().toString().padLeft(scale + 1, '0');
    final whole = digits.substring(0, digits.length - scale);
    final fraction = digits.substring(digits.length - scale);
    return '${isNegative ? '-' : ''}$whole${scale > 0 ? '.$fraction' : ''}';
  }

  /// Grouped with Western separators, e.g. "13,396.25".
  String grouped({int? decimals}) {
    final digits = minor.abs().toString().padLeft(scale + 1, '0');
    final whole = digits.substring(0, digits.length - scale);
    var fraction = digits.substring(digits.length - scale);
    final buffer = StringBuffer();
    for (var i = 0; i < whole.length; i++) {
      if (i > 0 && (whole.length - i) % 3 == 0) buffer.write(',');
      buffer.write(whole[i]);
    }
    final shown = decimals ?? scale;
    if (shown < fraction.length) fraction = fraction.substring(0, shown);
    final sign = isNegative ? '-' : '';
    return shown > 0 ? '$sign$buffer.$fraction' : '$sign$buffer';
  }

  @override
  int compareTo(Money other) => minor.compareTo(other.minor);

  @override
  bool operator ==(Object other) =>
      other is Money && other.minor == minor && other.scale == scale;

  @override
  int get hashCode => Object.hash(minor, scale);

  @override
  String toString() => toApiString();
}
