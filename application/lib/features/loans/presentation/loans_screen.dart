import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_response.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/money.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/loan_repository.dart';

/// Loans and salary advances: limits, own requests, new request, cancel.
class LoansScreen extends ConsumerWidget {
  const LoansScreen({super.key});

  Future<void> _new(
    BuildContext context,
    WidgetRef ref,
    LoanLimits limits,
  ) async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _LoanForm(limits: limits),
    );
    if (created == true) {
      ref.invalidate(loansProvider);
      if (context.mounted) {
        showSnack(context, context.l10n.loanSubmitted, tone: SnackTone.success);
      }
    }
  }

  Future<void> _cancel(BuildContext context, WidgetRef ref, Loan loan) async {
    final l = context.l10n;
    final ok = await confirmDialog(
      context,
      title: l.cancelRequest,
      message: l.cancelLoanConfirm,
      confirmLabel: l.cancelRequest,
      danger: true,
    );
    if (!ok) return;
    try {
      await ref.read(loanRepositoryProvider).cancel(loan.id);
      ref.invalidate(loansProvider);
    } catch (error) {
      if (context.mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final loans = ref.watch(loansProvider);
    final limits = ref.watch(loanLimitsProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l.loansTitle)),
      floatingActionButton: limits != null && limits.enabled
          ? FloatingActionButton.extended(
              onPressed: () => _new(context, ref, limits),
              icon: const Icon(Icons.add_rounded),
              label: Text(l.newLoanRequest),
            )
          : null,
      body: AsyncView<Paged<Loan>>(
        value: loans,
        onRefresh: () async {
          ref.invalidate(loanLimitsProvider);
          await ref.refresh(loansProvider.future).then((_) {});
        },
        builder: (page) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            if (limits != null)
              SectionCard(
                title: l.yourLimits,
                children: [
                  for (final limit in limits.limits)
                    InfoRow(
                      label: limit.label,
                      value: l.loanLimitValue(
                        f.money(limit.maxAmount, limits.currency),
                        f.number(limit.maxInstalments),
                      ),
                    ),
                  if (!limits.enabled)
                    InlineBanner(message: l.loansDisabled, tone: Tone.info),
                ],
              ),
            const SizedBox(height: AppSizes.gap),
            if (page.items.isEmpty)
              EmptyState(icon: Icons.payments_outlined, title: l.noLoans),
            for (final loan in page.items) ...[
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${loan.typeLabel} · ${loan.reference}',
                            style: AppType.labelMd,
                          ),
                        ),
                        StatusBadge(
                          label: loan.statusLabel,
                          status: loan.status,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    InfoRow(
                      label: l.requestedAmount,
                      value: f.money(loan.requestedAmount, loan.currency),
                    ),
                    if (loan.approvedAmount != null)
                      InfoRow(
                        label: l.approvedAmount,
                        value: f.money(loan.approvedAmount, loan.currency),
                      ),
                    InfoRow(
                      label: l.instalments,
                      value: f.number(
                        loan.instalmentCount ?? loan.requestedInstalments,
                      ),
                    ),
                    InfoRow(
                      label: l.outstanding,
                      value: f.money(loan.outstandingAmount, loan.currency),
                    ),
                    if (loan.currentStep != null)
                      InfoRow(label: l.currentStep, value: loan.currentStep),
                    if (loan.rejectionReason != null)
                      InfoRow(
                        label: l.rejectionReason,
                        value: loan.rejectionReason,
                      ),
                    if (loan.canCancel)
                      Align(
                        alignment: AlignmentDirectional.centerEnd,
                        child: TextButton(
                          onPressed: () => _cancel(context, ref, loan),
                          child: Text(l.cancelRequest),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _LoanForm extends ConsumerStatefulWidget {
  const _LoanForm({required this.limits});

  final LoanLimits limits;

  @override
  ConsumerState<_LoanForm> createState() => _LoanFormState();
}

class _LoanFormState extends ConsumerState<_LoanForm> {
  final _form = GlobalKey<FormState>();
  final _amount = TextEditingController();
  final _instalments = TextEditingController(text: '1');
  final _reason = TextEditingController();
  late LoanLimit _limit = widget.limits.limits.first;
  DateTime _start = DateTime(DateTime.now().year, DateTime.now().month + 1);
  bool _busy = false;
  ValidationFailure? _errors;
  String? _error;

  @override
  void dispose() {
    _amount.dispose();
    _instalments.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = null;
      _error = null;
    });
    try {
      await ref
          .read(loanRepositoryProvider)
          .request(
            type: _limit.type,
            amount: Money.parse(_amount.text).toApiString(),
            instalments: int.parse(_instalments.text),
            startMonth: Formatter.apiMonth(_start),
            reason: _reason.text,
          );
      if (mounted) Navigator.of(context).pop(true);
    } on ValidationFailure catch (failure) {
      setState(() => _errors = failure);
    } catch (error) {
      setState(() => _error = failureMessage(context.l10n, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final months = [
      for (var i = 0; i < 6; i++)
        DateTime(DateTime.now().year, DateTime.now().month + i),
    ];

    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
      ),
      child: Form(
        key: _form,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l.newLoanRequest, style: AppType.headingH4),
              const SizedBox(height: 16),
              if (_error != null) ...[
                InlineBanner(message: _error!, tone: Tone.danger),
                const SizedBox(height: 12),
              ],
              SegmentedButton<LoanLimit>(
                segments: [
                  for (final limit in widget.limits.limits)
                    ButtonSegment(value: limit, label: Text(limit.label)),
                ],
                selected: {_limit},
                showSelectedIcon: false,
                onSelectionChanged: (value) => setState(() {
                  _limit = value.first;
                  if (_limit.maxInstalments == 1) _instalments.text = '1';
                }),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,2}')),
                ],
                decoration: InputDecoration(
                  labelText: l.amount,
                  suffixText: widget.limits.currency,
                  helperText: l.maxAmount(
                    f.money(_limit.maxAmount, widget.limits.currency),
                  ),
                  errorText: _errors?.first('amount'),
                ),
                validator: (value) {
                  final amount = Money.tryParse(value);
                  if (amount == null || amount.isZero) {
                    return l.validationAmount;
                  }
                  final max = Money.tryParse(_limit.maxAmount);
                  if (max != null && !max.isZero && amount.compareTo(max) > 0) {
                    return l.validationMaxAmount;
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _instalments,
                enabled: _limit.maxInstalments > 1,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: l.instalments,
                  helperText: l.maxInstalments(f.number(_limit.maxInstalments)),
                  errorText: _errors?.first('instalments'),
                ),
                validator: (value) {
                  final n = int.tryParse(value ?? '');
                  if (n == null || n < 1 || n > _limit.maxInstalments) {
                    return l.validationInstalments(
                      f.number(_limit.maxInstalments),
                    );
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<DateTime>(
                initialValue: _start,
                decoration: InputDecoration(
                  labelText: l.startMonth,
                  errorText: _errors?.first('start_month'),
                ),
                items: [
                  for (final m in months)
                    DropdownMenuItem(value: m, child: Text(f.monthYear(m))),
                ],
                onChanged: (value) => setState(() => _start = value ?? _start),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _reason,
                maxLines: 2,
                decoration: InputDecoration(
                  labelText: l.reasonOptional,
                  errorText: _errors?.first('reason'),
                ),
              ),
              if (_errors != null) ...[
                const SizedBox(height: 12),
                InlineBanner(
                  message: _errors!.all.isEmpty
                      ? (_errors!.message ?? l.errorValidation)
                      : _errors!.all.join('\n'),
                  tone: Tone.danger,
                ),
              ],
              const SizedBox(height: 20),
              LoadingButton(
                label: l.submitRequest,
                loading: _busy,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
