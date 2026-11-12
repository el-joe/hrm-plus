import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/announcements_repository.dart';

class AnnouncementScreen extends ConsumerStatefulWidget {
  const AnnouncementScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<AnnouncementScreen> createState() => _AnnouncementScreenState();
}

class _AnnouncementScreenState extends ConsumerState<AnnouncementScreen> {
  bool _busy = false;

  Future<void> _acknowledge() async {
    final l = context.l10n;
    setState(() => _busy = true);
    try {
      await ref.read(announcementsRepositoryProvider).acknowledge(widget.id);
      ref.invalidate(announcementProvider(widget.id));
      if (mounted) showSnack(context, l.acknowledged, tone: SnackTone.success);
    } catch (error) {
      if (mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final value = ref.watch(announcementProvider(widget.id));

    return Scaffold(
      appBar: AppBar(title: Text(l.announcementsTitle)),
      body: AsyncView<Announcement>(
        value: value,
        onRefresh: () =>
            ref.refresh(announcementProvider(widget.id).future).then((_) {}),
        builder: (a) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            Row(
              children: [
                StatusBadge(label: a.typeLabel, tone: Tone.brand),
                const Spacer(),
                Text(
                  f.dateTime(a.publishedAt),
                  style: AppType.captionMd.copyWith(color: t.textTertiary),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(a.title, style: AppType.headingH3),
            const SizedBox(height: 12),
            SelectableText(
              a.bodyText ?? a.summary ?? '',
              style: AppType.bodyLg.copyWith(color: t.textPrimary),
            ),
            if (a.attachments.isNotEmpty) ...[
              const SizedBox(height: 20),
              SectionCard(
                title: l.attachments,
                children: [
                  for (final file in a.attachments)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.description_outlined),
                      title: Text(file.name),
                      subtitle: file.size == null
                          ? null
                          : Text(f.fileSize(file.size!)),
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
            ],
            const SizedBox(height: 20),
            if (a.canAcknowledge)
              LoadingButton(
                key: const Key('acknowledge'),
                label: l.acknowledge,
                icon: Icons.task_alt_rounded,
                loading: _busy,
                onPressed: _acknowledge,
              )
            else if (a.acknowledged)
              InlineBanner(
                message: l.acknowledgedOn(f.dateTime(a.acknowledgedAt)),
                tone: Tone.success,
                icon: Icons.task_alt_rounded,
              ),
          ],
        ),
      ),
    );
  }
}
