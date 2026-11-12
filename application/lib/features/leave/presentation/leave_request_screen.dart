import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/leave_repository.dart';

/// One leave request: dates, counted days, approval trail, attachments,
/// cancel and add attachments.
class LeaveRequestScreen extends ConsumerWidget {
  const LeaveRequestScreen({super.key, required this.id});

  final int id;

  Future<void> _cancel(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final ok = await confirmDialog(
      context,
      title: l.cancelRequest,
      message: l.cancelLeaveConfirm,
      confirmLabel: l.cancelRequest,
      danger: true,
    );
    if (!ok || !context.mounted) return;
    try {
      await ref.read(leaveRepositoryProvider).cancel(id);
      ref.invalidate(leaveRequestProvider(id));
      if (context.mounted) {
        showSnack(context, l.requestCancelled, tone: SnackTone.success);
      }
    } catch (error) {
      if (context.mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    }
  }

  Future<void> _attach(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final files = await pickUploadFiles();
    if (files.isEmpty) return;
    try {
      await ref.read(leaveRepositoryProvider).addAttachments(id, files);
      ref.invalidate(leaveRequestProvider(id));
      if (context.mounted) {
        showSnack(context, l.attachmentsUploaded, tone: SnackTone.success);
      }
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
    final value = ref.watch(leaveRequestProvider(id));

    return Scaffold(
      appBar: AppBar(title: Text(l.leaveRequestTitle)),
      body: AsyncView<LeaveRequest>(
        value: value,
        onRefresh: () =>
            ref.refresh(leaveRequestProvider(id).future).then((_) {}),
        builder: (request) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            SectionCard(
              title: request.typeName,
              trailing: StatusBadge(
                label: request.statusLabel,
                status: request.status,
              ),
              children: [
                InfoRow(
                  label: l.startDate,
                  value: f.date(Formatter.parseDate(request.startDate)),
                ),
                InfoRow(
                  label: l.endDate,
                  value: f.date(Formatter.parseDate(request.endDate)),
                ),
                InfoRow(
                  label: l.duration,
                  value: l.daysValue(f.decimal(request.days)),
                ),
                if (request.currentStep != null)
                  InfoRow(label: l.currentStep, value: request.currentStep),
                if (request.reason != null)
                  InfoRow(label: l.reason, value: request.reason),
                if (request.rejectionReason != null)
                  InfoRow(
                    label: l.rejectionReason,
                    value: request.rejectionReason,
                  ),
                InfoRow(
                  label: l.submittedOn,
                  value: f.dateTime(request.createdAt),
                ),
              ],
            ),
            if (request.dayBreakdown.isNotEmpty) ...[
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.dayBreakdown,
                children: [
                  for (final day in request.dayBreakdown)
                    InfoRow(
                      label: f.date(
                        Formatter.parseDate(day['date']?.toString()),
                      ),
                      value: day['is_counted'] == true
                          ? f.decimal(day['amount']?.toString())
                          : l.notCounted,
                    ),
                ],
              ),
            ],
            if (request.approval.isNotEmpty) ...[
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.approvalTrail,
                children: [
                  for (final step in request.approval)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      leading: const Icon(Icons.circle, size: 10),
                      title: Text(
                        [
                          step['action']?.toString() ?? '',
                          if (step['step'] != null) step['step'].toString(),
                        ].join(' · '),
                      ),
                      subtitle: Text(
                        f.dateTime(
                          DateTime.tryParse(step['at']?.toString() ?? ''),
                        ),
                      ),
                    ),
                ],
              ),
            ],
            const SizedBox(height: AppSizes.gap),
            SectionCard(
              title: l.attachments,
              trailing: request.status == 'pending'
                  ? IconButton(
                      tooltip: l.addAttachment,
                      onPressed: () => _attach(context, ref),
                      icon: const Icon(Icons.attach_file_rounded),
                    )
                  : null,
              children: [
                if (request.attachments.isEmpty) Text(l.noAttachments),
                for (final file in request.attachments)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.description_outlined),
                    title: Text(file.name, style: AppType.bodyMd),
                    trailing: const Icon(Icons.download_rounded),
                    onTap: file.url == null
                        ? null
                        : () => downloadAndOpen(
                            context,
                            ref,
                            url: file.url!,
                            fileName: file.name,
                          ),
                  ),
              ],
            ),
            if (request.canCancel) ...[
              const SizedBox(height: AppSizes.gap),
              OutlinedButton.icon(
                onPressed: () => _cancel(context, ref),
                icon: const Icon(Icons.cancel_outlined),
                label: Text(
                  request.cancelNeedsApproval
                      ? l.requestCancellation
                      : l.cancelRequest,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
