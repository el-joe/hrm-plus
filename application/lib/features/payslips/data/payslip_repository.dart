import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

/// Money fields are kept as the API's decimal strings.
class Payslip {
  const Payslip({
    required this.id,
    required this.number,
    required this.periodName,
    required this.periodStart,
    required this.periodEnd,
    required this.payDate,
    required this.currency,
    required this.grossPay,
    required this.totalDeductions,
    required this.netPay,
    required this.paymentStatus,
    required this.paymentStatusLabel,
    this.lines,
  });

  factory Payslip.fromJson(Json json) {
    final period = json.obj('period') ?? const {};
    final lines = json.obj('lines');
    return Payslip(
      id: json.integer('id'),
      number: json.str('number'),
      periodName: period.str('name'),
      periodStart: period.strOrNull('start_date'),
      periodEnd: period.strOrNull('end_date'),
      payDate: period.strOrNull('pay_date'),
      currency: json.str('currency'),
      grossPay: json.str('gross_pay', '0'),
      totalDeductions: json.str('total_deductions', '0'),
      netPay: json.str('net_pay', '0'),
      paymentStatus: json.str('payment_status'),
      paymentStatusLabel: json.str('payment_status_label'),
      lines: lines == null ? null : PayslipLines.fromJson(lines),
    );
  }

  final int id;
  final String number;
  final String periodName;
  final String? periodStart;
  final String? periodEnd;
  final String? payDate;
  final String currency;
  final String grossPay;
  final String totalDeductions;
  final String netPay;
  final String paymentStatus;
  final String paymentStatusLabel;
  final PayslipLines? lines;
}

class PayslipLine {
  const PayslipLine({
    required this.name,
    required this.amount,
    this.code,
    this.quantity,
    this.note,
  });

  factory PayslipLine.fromJson(Json json) => PayslipLine(
    name: json.str('name'),
    code: json.strOrNull('code'),
    amount: json.str('amount', '0'),
    quantity: json.strOrNull('quantity'),
    note: json.strOrNull('note'),
  );

  final String name;
  final String? code;
  final String amount;
  final String? quantity;
  final String? note;
}

class PayslipLines {
  const PayslipLines({
    required this.basicSalary,
    required this.earnings,
    required this.deductions,
    required this.loans,
    required this.statutory,
    required this.demoStatutory,
  });

  factory PayslipLines.fromJson(Json json) {
    List<PayslipLine> list(String key) =>
        json.objList(key).map(PayslipLine.fromJson).toList();
    return PayslipLines(
      basicSalary: json.strOrNull('basic_salary'),
      earnings: list('earnings'),
      deductions: list('deductions'),
      loans: list('loans'),
      statutory: list('statutory'),
      demoStatutory: json.boolean('demo_statutory'),
    );
  }

  final String? basicSalary;
  final List<PayslipLine> earnings;
  final List<PayslipLine> deductions;
  final List<PayslipLine> loans;
  final List<PayslipLine> statutory;

  /// Statutory rules are demo values (blocker B7) — shown as a notice.
  final bool demoStatutory;
}

/// `GET me/payslips/summary?year=`.
class PayslipSummary {
  const PayslipSummary({
    required this.year,
    required this.years,
    required this.count,
    required this.gross,
    required this.deductions,
    required this.net,
    required this.currency,
    required this.loanBalance,
    required this.structure,
  });

  factory PayslipSummary.fromJson(Json json) => PayslipSummary(
    year: json.integer('year', DateTime.now().year),
    years: [
      for (final y in (json['years'] as List<dynamic>? ?? const <dynamic>[]))
        if (y is num) y.toInt(),
    ],
    count: json.integer('count'),
    gross: json.str('gross', '0'),
    deductions: json.str('deductions', '0'),
    net: json.str('net', '0'),
    currency: json.str('currency'),
    loanBalance: json.str('loan_balance', '0'),
    structure: json.obj('structure'),
  );

  final int year;
  final List<int> years;
  final int count;
  final String gross;
  final String deductions;
  final String net;
  final String currency;
  final String loanBalance;

  /// basic, pay_basis, structure, lines[{name, kind, rule}], allowances[…]
  final Json? structure;
}

class PayslipRepository {
  PayslipRepository(this._api);

  final ApiClient _api;

  Future<Paged<Payslip>> list({int? year, int page = 1}) async {
    final response = await _api.get(
      'me/payslips',
      query: {'year': year, 'page': page},
    );
    return Paged(
      items: response.list.map(Payslip.fromJson).toList(),
      pagination: response.pagination,
    );
  }

  Future<PayslipSummary> summary({int? year}) async => PayslipSummary.fromJson(
    (await _api.get('me/payslips/summary', query: {'year': year})).json,
  );

  Future<Payslip> show(int id) async =>
      Payslip.fromJson((await _api.get('me/payslips/$id')).json);

  /// The PDF in the requested language (`lang=ar|en`).
  Future<Uint8List> pdf(int id, String language) =>
      _api.download('me/payslips/$id/pdf', query: {'lang': language});
}

final payslipRepositoryProvider = Provider<PayslipRepository>(
  (ref) => PayslipRepository(ref.watch(apiClientProvider)),
);

final payslipSummaryProvider = FutureProvider.autoDispose
    .family<PayslipSummary, int?>(
      (ref, year) => ref.watch(payslipRepositoryProvider).summary(year: year),
    );

final payslipListProvider = FutureProvider.autoDispose
    .family<Paged<Payslip>, int?>(
      (ref, year) => ref.watch(payslipRepositoryProvider).list(year: year),
    );

final payslipProvider = FutureProvider.autoDispose.family<Payslip, int>(
  (ref, id) => ref.watch(payslipRepositoryProvider).show(id),
);
