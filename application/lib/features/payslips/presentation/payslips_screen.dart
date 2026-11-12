import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/payslip_repository.dart';

/// Payslips per year with the year's totals.
class PayslipsScreen extends ConsumerStatefulWidget {
  const PayslipsScreen({super.key});

  @override
  ConsumerState<PayslipsScreen> createState() => _PayslipsScreenState();
}

class _PayslipsScreenState extends ConsumerState<PayslipsScreen> {
  int? _year;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final summary = ref.watch(payslipSummaryProvider(_year));
    final list = ref.watch(payslipListProvider(_year ?? summary.value?.year));
    final years = summary.value?.years ?? const <int>[];

    return Scaffold(
      appBar: AppBar(
        title: Text(l.payslipsTitle),
        actions: [
          IconButton(
            tooltip: l.loansTitle,
            onPressed: () => context.push(Routes.loans),
            icon: const Icon(Icons.payments_outlined),
          ),
        ],
      ),
      body: AsyncView<Paged<Payslip>>(
        value: list,
        onRefresh: () async {
          ref.invalidate(payslipSummaryProvider);
          ref.invalidate(payslipListProvider);
          await ref
              .read(payslipListProvider(_year ?? summary.value?.year).future)
              .then((_) {}, onError: (_) {});
        },
        builder: (page) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            if (years.length > 1)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Wrap(
                  spacing: 8,
                  children: [
                    for (final y in years)
                      ChoiceChip(
                        label: Text(f.d('$y')),
                        selected: (_year ?? summary.value?.year) == y,
                        onSelected: (_) => setState(() => _year = y),
                      ),
                  ],
                ),
              ),
            if (summary.value != null) _YearSummary(summary: summary.value!),
            const SizedBox(height: AppSizes.gap),
            if (page.items.isEmpty)
              EmptyState(
                icon: Icons.receipt_long_outlined,
                title: l.noPayslips,
                message: l.noPayslipsHint,
              ),
            for (final payslip in page.items) ...[
              AppListTile(
                key: Key('payslip-${payslip.id}'),
                leadingIcon: Icons.receipt_long_outlined,
                title: payslip.periodName,
                subtitle:
                    '${payslip.number} · ${f.date(Formatter.parseDate(payslip.payDate))}',
                trailing: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      f.money(payslip.netPay, payslip.currency),
                      style: AppType.numericMd,
                    ),
                    StatusBadge(
                      label: payslip.paymentStatusLabel,
                      status: payslip.paymentStatus,
                    ),
                  ],
                ),
                onTap: () => context.push(Routes.payslip(payslip.id)),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    );
  }
}

class _YearSummary extends ConsumerWidget {
  const _YearSummary({required this.summary});

  final PayslipSummary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    return AppCard(
      color: t.surfaceBrand,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.netThisYear(f.d('${summary.year}')),
            style: AppType.labelMd.copyWith(color: t.textOnBrandMuted),
          ),
          const SizedBox(height: 4),
          Text(
            f.money(summary.net, summary.currency),
            style: AppType.numericXl.copyWith(color: t.textOnPrimary),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _Mini(label: l.gross, value: f.money(summary.gross)),
              ),
              Expanded(
                child: _Mini(
                  label: l.deductions,
                  value: f.money(summary.deductions),
                ),
              ),
              Expanded(
                child: _Mini(
                  label: l.loanBalance,
                  value: f.money(summary.loanBalance),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Mini extends StatelessWidget {
  const _Mini({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppType.captionMd.copyWith(color: t.textOnBrandMuted),
        ),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            value,
            style: AppType.numericMd.copyWith(color: t.textOnPrimary),
          ),
        ),
      ],
    );
  }
}
