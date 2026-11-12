import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/money.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/payslip_repository.dart';

/// Salary breakdown of one payslip: earnings, deductions, loans, statutory
/// lines, a net/deductions chart and the PDF.
class PayslipDetailScreen extends ConsumerStatefulWidget {
  const PayslipDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<PayslipDetailScreen> createState() =>
      _PayslipDetailScreenState();
}

class _PayslipDetailScreenState extends ConsumerState<PayslipDetailScreen> {
  bool _downloading = false;

  Future<void> _pdf(Payslip payslip) async {
    final l = context.l10n;
    final language = ref.read(settingsProvider).locale.languageCode;
    setState(() => _downloading = true);
    try {
      final bytes = await ref
          .read(payslipRepositoryProvider)
          .pdf(payslip.id, language);
      final opened = await ref
          .read(fileOpenerProvider)
          .saveAndOpen(bytes, '${payslip.number}-$language.pdf');
      if (mounted && !opened) {
        showSnack(context, l.cannotOpenFile, tone: SnackTone.error);
      }
    } catch (error) {
      if (mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final value = ref.watch(payslipProvider(widget.id));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.salaryBreakdown),
        actions: [
          if (value.hasValue)
            IconButton(
              key: const Key('payslip-pdf'),
              tooltip: l.downloadPdf,
              onPressed: _downloading ? null : () => _pdf(value.requireValue),
              icon: _downloading
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined),
            ),
        ],
      ),
      body: AsyncView<Payslip>(
        value: value,
        onRefresh: () =>
            ref.refresh(payslipProvider(widget.id).future).then((_) {}),
        builder: (payslip) {
          final lines = payslip.lines;
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              _Header(payslip: payslip),
              const SizedBox(height: AppSizes.gap),
              _Chart(payslip: payslip),
              if (lines != null) ...[
                if (lines.demoStatutory) ...[
                  const SizedBox(height: AppSizes.gap),
                  InlineBanner(
                    message: l.demoStatutoryNotice,
                    tone: Tone.warning,
                  ),
                ],
                _Lines(
                  title: l.earnings,
                  lines: lines.earnings,
                  currency: payslip.currency,
                ),
                _Lines(
                  title: l.deductions,
                  lines: lines.deductions,
                  currency: payslip.currency,
                  negative: true,
                ),
                _Lines(
                  title: l.statutory,
                  lines: lines.statutory,
                  currency: payslip.currency,
                  negative: true,
                ),
                _Lines(
                  title: l.loanRepayments,
                  lines: lines.loans,
                  currency: payslip.currency,
                  negative: true,
                ),
              ],
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.totals,
                children: [
                  InfoRow(
                    label: l.gross,
                    value: f.money(payslip.grossPay, payslip.currency),
                  ),
                  InfoRow(
                    label: l.totalDeductions,
                    value: f.money(payslip.totalDeductions, payslip.currency),
                  ),
                  const Divider(),
                  InfoRow(
                    label: l.netPay,
                    value: f.money(payslip.netPay, payslip.currency),
                    valueStyle: AppType.numericMd.copyWith(
                      color: context.tokens.actionPrimary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSizes.gap),
              LoadingButton(
                label: l.downloadPdf,
                icon: Icons.picture_as_pdf_outlined,
                loading: _downloading,
                onPressed: () => _pdf(payslip),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Header extends ConsumerWidget {
  const _Header({required this.payslip});

  final Payslip payslip;

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
          Row(
            children: [
              Expanded(
                child: Text(
                  payslip.periodName,
                  style: AppType.labelMd.copyWith(color: t.textOnBrandMuted),
                ),
              ),
              StatusBadge(
                label: payslip.paymentStatusLabel,
                status: payslip.paymentStatus,
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            f.money(payslip.netPay, payslip.currency),
            style: AppType.numericXl.copyWith(color: t.textOnPrimary),
          ),
          Text(
            '${payslip.number} · ${l.payDate}: ${f.date(Formatter.parseDate(payslip.payDate))}',
            style: AppType.captionMd.copyWith(color: t.textOnBrandMuted),
          ),
        ],
      ),
    );
  }
}

/// Net vs deductions share of gross (ratios only — amounts stay strings).
class _Chart extends ConsumerWidget {
  const _Chart({required this.payslip});

  final Payslip payslip;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final t = context.tokens;
    final gross = Money.parse(payslip.grossPay);
    if (gross.isZero) return const SizedBox.shrink();
    final net = Money.parse(payslip.netPay).ratioOf(gross).clamp(0.0, 1.0);
    final deductions = Money.parse(payslip.totalDeductions)
        .ratioOf(gross)
        .clamp(0.0, 1.0);

    Widget legend(Color color, String label, double share) => Row(
      children: [
        Container(width: 10, height: 10, color: color),
        const SizedBox(width: 6),
        Text(f.d('$label · ${(share * 100).round()}%')),
      ],
    );

    return AppCard(
      child: Row(
        children: [
          SizedBox(
            width: 120,
            height: 120,
            child: PieChart(
              PieChartData(
                sectionsSpace: 2,
                centerSpaceRadius: 30,
                sections: [
                  PieChartSectionData(
                    value: net,
                    color: t.chart[0],
                    showTitle: false,
                    radius: 24,
                  ),
                  PieChartSectionData(
                    value: deductions,
                    color: t.chart[1],
                    showTitle: false,
                    radius: 24,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                legend(t.chart[0], l.netPay, net),
                const SizedBox(height: 8),
                legend(t.chart[1], l.totalDeductions, deductions),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Lines extends ConsumerWidget {
  const _Lines({
    required this.title,
    required this.lines,
    required this.currency,
    this.negative = false,
  });

  final String title;
  final List<PayslipLine> lines;
  final String currency;
  final bool negative;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (lines.isEmpty) return const SizedBox.shrink();
    final f = ref.watch(formatterProvider);
    final t = context.tokens;
    return Padding(
      padding: const EdgeInsets.only(top: AppSizes.gap),
      child: SectionCard(
        title: title,
        children: [
          for (final line in lines)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(line.name, style: AppType.labelMd),
                        if (line.note != null)
                          Text(
                            f.d(line.note!),
                            style: AppType.captionMd.copyWith(
                              color: t.textTertiary,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Text(
                    '${negative ? '−' : ''}${f.money(line.amount)}',
                    style: AppType.numericMd.copyWith(
                      color: negative ? t.actionDanger : t.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
