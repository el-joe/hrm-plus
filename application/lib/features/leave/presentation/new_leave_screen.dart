import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/files/upload_file.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/leave_repository.dart';

/// New leave request: type, dates, day part, reason, attachments, a server
/// preview (days counted, balance after) and submission.
class NewLeaveScreen extends ConsumerStatefulWidget {
  const NewLeaveScreen({super.key});

  @override
  ConsumerState<NewLeaveScreen> createState() => _NewLeaveScreenState();
}

class _NewLeaveScreenState extends ConsumerState<NewLeaveScreen> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();
  final _hours = TextEditingController();
  LeaveType? _type;
  DateTime? _start;
  DateTime? _end;
  String _dayPart = 'full';
  List<UploadFile> _files = [];
  LeavePreview? _preview;
  ValidationFailure? _errors;
  String? _error;
  bool _previewing = false;
  bool _submitting = false;

  @override
  void dispose() {
    _reason.dispose();
    _hours.dispose();
    super.dispose();
  }

  LeaveDraft _draft() => LeaveDraft(
    typeId: _type!.id,
    startDate: Formatter.apiDate(_start!),
    endDate: _dayPart == 'full' && _end != null
        ? Formatter.apiDate(_end!)
        : null,
    dayPart: _dayPart,
    hours: _hours.text.trim().isEmpty ? null : _hours.text.trim(),
    reason: _reason.text,
  );

  void _changed() => setState(() {
    _preview = null;
    _errors = null;
    _error = null;
  });

  Future<void> _pickDate({required bool start}) async {
    final now = DateUtils.dateOnly(DateTime.now());
    final allowPast = _type?.allowsPastDates ?? false;
    final initial = start ? (_start ?? now) : (_end ?? _start ?? now);
    final first = allowPast ? DateTime(now.year - 1) : now;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(first) ? first : initial,
      firstDate: start ? first : (_start ?? first),
      lastDate: DateTime(now.year + 2, 12, 31),
    );
    if (picked == null) return;
    setState(() {
      if (start) {
        _start = picked;
        if (_end != null && _end!.isBefore(picked)) _end = picked;
      } else {
        _end = picked;
      }
    });
    _changed();
  }

  bool _validate() {
    final ok = _form.currentState!.validate();
    if (_start == null) {
      setState(() => _error = context.l10n.validationStartDate);
      return false;
    }
    return ok;
  }

  Future<void> _runPreview() async {
    if (!_validate()) return;
    setState(() {
      _previewing = true;
      _error = null;
      _errors = null;
    });
    try {
      final preview = await ref.read(leaveRepositoryProvider).preview(_draft());
      setState(() => _preview = preview);
    } on ValidationFailure catch (failure) {
      setState(() => _errors = failure);
    } catch (error) {
      setState(() => _error = failureMessage(context.l10n, error));
    } finally {
      if (mounted) setState(() => _previewing = false);
    }
  }

  Future<void> _submit() async {
    if (!_validate()) return;
    final l = context.l10n;
    if ((_preview?.needsAttachment ?? false) && _files.isEmpty) {
      setState(() => _error = l.attachmentRequired);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
      _errors = null;
    });
    try {
      final request = await ref
          .read(leaveRepositoryProvider)
          .submit(_draft(), attachments: _files);
      if (!mounted) return;
      showSnack(context, l.leaveSubmitted, tone: SnackTone.success);
      context.pushReplacement(Routes.leaveRequest(request.id));
    } on ValidationFailure catch (failure) {
      setState(() => _errors = failure);
    } catch (error) {
      setState(() => _error = failureMessage(l, error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final types = ref.watch(leaveTypesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.newLeaveRequest)),
      body: AsyncView<List<LeaveType>>(
        value: types,
        onRefresh: () => ref.refresh(leaveTypesProvider.future).then((_) {}),
        isEmpty: (list) => list.isEmpty,
        empty: EmptyState(title: l.noLeaveTypes),
        builder: (list) => Form(
          key: _form,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              if (_error != null) ...[
                InlineBanner(
                  key: const Key('leave-error'),
                  message: _error!,
                  tone: Tone.danger,
                ),
                const SizedBox(height: 12),
              ],
              if (_errors != null) ...[
                InlineBanner(
                  key: const Key('leave-validation'),
                  message: _errors!.all.isEmpty
                      ? (_errors!.message ?? l.errorValidation)
                      : _errors!.all.join('\n'),
                  tone: Tone.danger,
                ),
                const SizedBox(height: 12),
              ],
              DropdownButtonFormField<LeaveType>(
                key: const Key('leave-type'),
                initialValue: _type,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: l.leaveType,
                  errorText: _errors?.first('leave_type_id'),
                ),
                items: [
                  for (final type in list)
                    DropdownMenuItem(value: type, child: Text(type.name)),
                ],
                validator: (value) =>
                    value == null ? l.validationRequired : null,
                onChanged: (value) {
                  setState(() {
                    _type = value;
                    _dayPart = 'full';
                  });
                  _changed();
                },
              ),
              if (_type?.description != null) ...[
                const SizedBox(height: 6),
                Text(_type!.description!, style: AppType.captionMd),
              ],
              if (_type != null && _type!.noticeDays > 0) ...[
                const SizedBox(height: 6),
                Text(
                  l.noticeDays(f.number(_type!.noticeDays)),
                  style: AppType.captionMd,
                ),
              ],
              const SizedBox(height: 16),
              if (_type != null &&
                  (_type!.allowsHalfDay || _type!.allowsHours)) ...[
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(value: 'full', label: Text(l.dayPartFull)),
                    if (_type!.allowsHalfDay) ...[
                      ButtonSegment(
                        value: 'first_half',
                        label: Text(l.dayPartFirstHalf),
                      ),
                      ButtonSegment(
                        value: 'second_half',
                        label: Text(l.dayPartSecondHalf),
                      ),
                    ],
                    if (_type!.allowsHours)
                      ButtonSegment(
                        value: 'hours',
                        label: Text(l.dayPartHours),
                      ),
                  ],
                  selected: {_dayPart},
                  showSelectedIcon: false,
                  onSelectionChanged: (value) {
                    setState(() => _dayPart = value.first);
                    _changed();
                  },
                ),
                const SizedBox(height: 16),
              ],
              _DateField(
                key: const Key('leave-start'),
                label: _dayPart == 'full' ? l.startDate : l.date,
                value: _start == null ? null : f.date(_start),
                errorText: _errors?.first('start_date'),
                onTap: () => _pickDate(start: true),
              ),
              if (_dayPart == 'full') ...[
                const SizedBox(height: 16),
                _DateField(
                  key: const Key('leave-end'),
                  label: l.endDate,
                  value: _end == null ? null : f.date(_end),
                  errorText: _errors?.first('end_date'),
                  onTap: () => _pickDate(start: false),
                ),
              ],
              if (_dayPart == 'hours') ...[
                const SizedBox(height: 16),
                TextFormField(
                  controller: _hours,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: InputDecoration(
                    labelText: l.hours,
                    errorText: _errors?.first('hours'),
                  ),
                  validator: (v) =>
                      (v ?? '').trim().isEmpty ? l.validationRequired : null,
                  onChanged: (_) => _changed(),
                ),
              ],
              const SizedBox(height: 16),
              TextFormField(
                controller: _reason,
                maxLines: 3,
                maxLength: 1000,
                decoration: InputDecoration(
                  labelText: l.reasonOptional,
                  errorText: _errors?.first('reason'),
                ),
              ),
              const SizedBox(height: 8),
              Text(l.attachments, style: AppType.labelMd),
              const SizedBox(height: 8),
              AttachmentPicker(
                files: _files,
                errorText: _errors?.first('attachments'),
                onChanged: (files) => setState(() => _files = files),
              ),
              if (_type?.requiresAttachment ?? false) ...[
                const SizedBox(height: 4),
                Text(l.attachmentRequiredHint, style: AppType.captionMd),
              ],
              const SizedBox(height: 20),
              if (_preview != null) ...[
                _PreviewCard(
                  preview: _preview!,
                  tracksBalance: _type!.tracksBalance,
                ),
                const SizedBox(height: 16),
              ],
              OutlinedButton.icon(
                key: const Key('leave-preview'),
                onPressed: _previewing ? null : _runPreview,
                icon: _previewing
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.calculate_outlined),
                label: Text(l.previewDays),
              ),
              const SizedBox(height: 12),
              LoadingButton(
                key: const Key('leave-submit'),
                label: l.submitRequest,
                loading: _submitting,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({
    super.key,
    required this.label,
    required this.value,
    required this.onTap,
    this.errorText,
  });

  final String label;
  final String? value;
  final VoidCallback onTap;
  final String? errorText;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(AppSizes.radiusMd),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
        suffixIcon: const Icon(Icons.calendar_today_outlined),
      ),
      child: Text(value ?? context.l10n.selectDate),
    ),
  );
}

class _PreviewCard extends ConsumerWidget {
  const _PreviewCard({required this.preview, required this.tracksBalance});

  final LeavePreview preview;
  final bool tracksBalance;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final notCounted = preview.days.where((d) => d['is_counted'] == false);
    return SectionCard(
      key: const Key('leave-preview-card'),
      title: l.previewTitle,
      children: [
        InfoRow(
          label: l.daysCounted,
          value: l.daysValue(f.decimal(preview.total)),
        ),
        if (notCounted.isNotEmpty)
          InfoRow(label: l.daysNotCounted, value: f.number(notCounted.length)),
        if (tracksBalance)
          for (final entry in preview.availableAfter.entries)
            InfoRow(
              label: l.balanceAfter(f.d(entry.key)),
              value: l.daysValue(f.decimal(entry.value)),
              valueStyle: entry.value.startsWith('-')
                  ? AppType.labelMd.copyWith(color: context.tokens.actionDanger)
                  : null,
            ),
        if (preview.needsAttachment)
          InlineBanner(message: l.attachmentRequired, tone: Tone.warning),
      ],
    );
  }
}
