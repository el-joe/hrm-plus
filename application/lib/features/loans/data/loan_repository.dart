import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

class Loan {
  const Loan({
    required this.id,
    required this.reference,
    required this.type,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    required this.requestedAmount,
    required this.requestedInstalments,
    required this.outstandingAmount,
    required this.repaidAmount,
    required this.currency,
    required this.canCancel,
    this.approvedAmount,
    this.instalmentCount,
    this.startMonth,
    this.requestedStart,
    this.reason,
    this.rejectionReason,
    this.currentStep,
    this.createdAt,
  });

  factory Loan.fromJson(Json json) => Loan(
    id: json.integer('id'),
    reference: json.str('reference'),
    type: json.str('type'),
    typeLabel: json.str('type_label'),
    status: json.str('status'),
    statusLabel: json.str('status_label'),
    requestedAmount: json.str('requested_amount', '0'),
    requestedInstalments: json.integer('requested_instalments'),
    requestedStart: json.strOrNull('requested_start'),
    approvedAmount: json.strOrNull('approved_amount'),
    instalmentCount: json.intOrNull('instalment_count'),
    startMonth: json.strOrNull('start_month'),
    outstandingAmount: json.str('outstanding_amount', '0'),
    repaidAmount: json.str('repaid_amount', '0'),
    currency: json.str('currency'),
    reason: json.strOrNull('reason'),
    rejectionReason: json.strOrNull('rejection_reason'),
    currentStep: json.strOrNull('current_step'),
    canCancel: json.boolean('can_cancel'),
    createdAt: json.date('created_at'),
  );

  final int id;
  final String reference;
  final String type;
  final String typeLabel;
  final String status;
  final String statusLabel;
  final String requestedAmount;
  final int requestedInstalments;
  final String? requestedStart;
  final String? approvedAmount;
  final int? instalmentCount;
  final String? startMonth;
  final String outstandingAmount;
  final String repaidAmount;
  final String currency;
  final String? reason;
  final String? rejectionReason;
  final String? currentStep;
  final bool canCancel;
  final DateTime? createdAt;
}

class LoanLimit {
  const LoanLimit({
    required this.type,
    required this.label,
    required this.maxAmount,
    required this.maxInstalments,
    this.maxInstalment,
    required this.minServiceMonths,
  });

  factory LoanLimit.fromJson(String type, Json json) => LoanLimit(
    type: type,
    label: json.str('label', type),
    maxAmount: json.str('max_amount', '0'),
    maxInstalments: json.integer('max_instalments', 1),
    maxInstalment: json.strOrNull('max_instalment'),
    minServiceMonths: json.integer('min_service_months'),
  );

  final String type;
  final String label;
  final String maxAmount;
  final int maxInstalments;
  final String? maxInstalment;
  final int minServiceMonths;
}

class LoanLimits {
  const LoanLimits({
    required this.limits,
    required this.enabled,
    required this.currency,
  });

  final List<LoanLimit> limits;
  final bool enabled;
  final String currency;
}

class LoanRepository {
  LoanRepository(this._api);

  final ApiClient _api;

  Future<Paged<Loan>> list({int page = 1}) async {
    final response = await _api.get('me/loans', query: {'page': page});
    return Paged(
      items: response.list.map(Loan.fromJson).toList(),
      pagination: response.pagination,
    );
  }

  Future<LoanLimits> limits() async {
    final response = await _api.get('me/loans/limits');
    final data = response.json;
    return LoanLimits(
      limits: [
        for (final entry in data.entries)
          if (entry.value is Map)
            LoanLimit.fromJson(entry.key, asJson(entry.value)),
      ],
      enabled: response.meta.boolean('requests_enabled', true),
      currency: response.meta.str('currency'),
    );
  }

  /// [amount] is a decimal string ("1500.00") — never a double.
  Future<Loan> request({
    required String type,
    required String amount,
    required int instalments,
    required String startMonth,
    String? reason,
  }) async => Loan.fromJson(
    (await _api.post(
      'me/loans',
      data: {
        'type': type,
        'amount': amount,
        'instalments': instalments,
        'start_month': startMonth,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
    )).json,
  );

  Future<void> cancel(int id) => _api.post('me/loans/$id/cancel');
}

final loanRepositoryProvider = Provider<LoanRepository>(
  (ref) => LoanRepository(ref.watch(apiClientProvider)),
);

final loansProvider = FutureProvider.autoDispose<Paged<Loan>>(
  (ref) => ref.watch(loanRepositoryProvider).list(),
);

final loanLimitsProvider = FutureProvider.autoDispose<LoanLimits>(
  (ref) => ref.watch(loanRepositoryProvider).limits(),
);
