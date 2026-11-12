import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/files/upload_file.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/requests_repository.dart';

/// One HR request: details, conversation thread, attachments, history,
/// reply, attach and cancel.
class RequestDetailsScreen extends ConsumerStatefulWidget {
  const RequestDetailsScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<RequestDetailsScreen> createState() =>
      _RequestDetailsScreenState();
}

class _RequestDetailsScreenState extends ConsumerState<RequestDetailsScreen> {
  final _reply = TextEditingController();
  List<UploadFile> _files = [];
  bool _sending = false;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _act(Future<void> Function() action, String success) async {
    final l = context.l10n;
    setState(() => _sending = true);
    try {
      await action();
      ref.invalidate(requestDetailProvider(widget.id));
      if (mounted) showSnack(context, success, tone: SnackTone.success);
    } on ValidationFailure catch (failure) {
      if (mounted) {
        showSnack(
          context,
          failure.all.isNotEmpty
              ? failure.all.first
              : failureMessage(l, failure),
          tone: SnackTone.error,
        );
      }
    } catch (error) {
      if (mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _sendReply() async {
    final text = _reply.text.trim();
    if (text.length < 2) return;
    await _act(() async {
      await ref
          .read(requestsRepositoryProvider)
          .reply(widget.id, text, files: _files);
      _reply.clear();
      setState(() => _files = []);
    }, context.l10n.replySent);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final t = context.tokens;
    final value = ref.watch(requestDetailProvider(widget.id));

    return Scaffold(
      appBar: AppBar(title: Text(l.requestDetailsTitle)),
      body: AsyncView<HrRequestDetail>(
        value: value,
        onRefresh: () =>
            ref.refresh(requestDetailProvider(widget.id).future).then((_) {}),
        builder: (detail) {
          final s = detail.summary;
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              SectionCard(
                title: s.subject.isEmpty ? s.categoryName : s.subject,
                trailing: StatusBadge(label: s.statusLabel, status: s.status),
                children: [
                  InfoRow(label: l.reference, value: s.reference),
                  InfoRow(label: l.category, value: s.categoryName),
                  if (detail.priorityLabel != null)
                    InfoRow(label: l.priority, value: detail.priorityLabel),
                  InfoRow(
                    label: l.submittedOn,
                    value: f.dateTime(s.submittedAt),
                  ),
                  for (final (label, value) in detail.fields)
                    InfoRow(label: label, value: value),
                  if (detail.description != null) ...[
                    const SizedBox(height: 8),
                    Text(detail.description!, style: AppType.bodyMd),
                  ],
                  if (detail.resolutionNote != null)
                    InfoRow(label: l.resolution, value: detail.resolutionNote),
                  if (detail.rejectionReason != null)
                    InfoRow(
                      label: l.rejectionReason,
                      value: detail.rejectionReason,
                    ),
                ],
              ),
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.conversation,
                children: [
                  if (detail.messages.isEmpty) Text(l.noMessages),
                  for (final message in detail.messages)
                    Align(
                      alignment: message.fromEmployee
                          ? AlignmentDirectional.centerEnd
                          : AlignmentDirectional.centerStart,
                      child: Container(
                        margin: const EdgeInsets.symmetric(vertical: 4),
                        padding: const EdgeInsets.all(12),
                        constraints: const BoxConstraints(maxWidth: 300),
                        decoration: BoxDecoration(
                          color: message.fromEmployee
                              ? t.actionPrimarySubtle
                              : t.surfaceSubtle,
                          borderRadius: BorderRadius.circular(
                            AppSizes.radiusLg,
                          ),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              message.fromEmployee ? l.you : message.authorName,
                              style: AppType.labelSm.copyWith(
                                color: t.textSecondary,
                              ),
                            ),
                            Text(message.body, style: AppType.bodyMd),
                            Text(
                              f.dateTime(message.createdAt),
                              style: AppType.captionSm.copyWith(
                                color: t.textTertiary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  if (s.canReply) ...[
                    const SizedBox(height: 8),
                    TextField(
                      key: const Key('reply-field'),
                      controller: _reply,
                      minLines: 1,
                      maxLines: 4,
                      decoration: InputDecoration(
                        hintText: l.writeReply,
                        suffixIcon: IconButton(
                          tooltip: l.send,
                          onPressed: _sending ? null : _sendReply,
                          icon: const Icon(Icons.send_rounded),
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    AttachmentPicker(
                      files: _files,
                      onChanged: (files) => setState(() => _files = files),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.attachments,
                trailing: s.canReply || s.canCancel
                    ? IconButton(
                        tooltip: l.addAttachment,
                        onPressed: _sending
                            ? null
                            : () async {
                                final files = await pickUploadFiles();
                                if (files.isEmpty) return;
                                await _act(
                                  () => ref
                                      .read(requestsRepositoryProvider)
                                      .attach(widget.id, files),
                                  l.attachmentsUploaded,
                                );
                              },
                        icon: const Icon(Icons.attach_file_rounded),
                      )
                    : null,
                children: [
                  if (detail.attachments.isEmpty) Text(l.noAttachments),
                  for (final file in detail.attachments)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.description_outlined),
                      title: Text(file.name),
                      trailing: const Icon(Icons.download_rounded),
                      onTap: file.downloadUrl == null
                          ? null
                          : () => downloadAndOpen(
                              context,
                              ref,
                              url: file.downloadUrl!,
                              fileName: file.name,
                            ),
                    ),
                ],
              ),
              if (detail.history.isNotEmpty) ...[
                const SizedBox(height: AppSizes.gap),
                SectionCard(
                  title: l.history,
                  children: [
                    for (final h in detail.history)
                      ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.circle, size: 10),
                        title: Text(h.str('description')),
                        subtitle: Text(f.dateTime(h.date('created_at'))),
                      ),
                  ],
                ),
              ],
              if (s.canCancel) ...[
                const SizedBox(height: AppSizes.gap),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: t.actionDanger,
                  ),
                  onPressed: _sending
                      ? null
                      : () async {
                          final ok = await confirmDialog(
                            context,
                            title: l.cancelRequest,
                            message: l.cancelRequestConfirm,
                            confirmLabel: l.cancelRequest,
                            danger: true,
                          );
                          if (ok) {
                            await _act(
                              () => ref
                                  .read(requestsRepositoryProvider)
                                  .cancel(widget.id),
                              l.requestCancelled,
                            );
                          }
                        },
                  icon: const Icon(Icons.cancel_outlined),
                  label: Text(l.cancelRequest),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
