import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/documents_repository.dart';

class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final value = ref.watch(documentsProvider);
    final titles = {
      'documents': l.docsShared,
      'contracts': l.docsContracts,
      'certificates': l.docsCertificates,
      'letters': l.docsLetters,
      'payslips': l.payslipsTitle,
    };

    return Scaffold(
      appBar: AppBar(title: Text(l.documentsTitle)),
      body: AsyncView<Map<String, List<EmployeeDocument>>>(
        value: value,
        onRefresh: () => ref.refresh(documentsProvider.future).then((_) {}),
        isEmpty: (groups) => groups.values.every((list) => list.isEmpty),
        empty: EmptyState(
          icon: Icons.folder_open_rounded,
          title: l.noDocuments,
        ),
        builder: (groups) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSizes.pagePadding),
          children: [
            for (final entry in groups.entries)
              if (entry.value.isNotEmpty) ...[
                SectionTitle(titles[entry.key] ?? entry.key),
                for (final doc in entry.value) ...[
                  AppListTile(
                    leadingIcon: switch (doc.type) {
                      'payslip' => Icons.receipt_long_outlined,
                      'letter' => Icons.mail_outline_rounded,
                      'certificate' => Icons.workspace_premium_outlined,
                      'contract' => Icons.handshake_outlined,
                      _ => Icons.description_outlined,
                    },
                    title: doc.title,
                    subtitle: [
                      ?doc.subtitle,
                      if (doc.date != null)
                        f.date(Formatter.parseDate(doc.date)),
                      if (doc.fileSize != null) f.fileSize(doc.fileSize!),
                    ].join(' · '),
                    trailing: doc.isExpired
                        ? StatusBadge(label: l.expired, tone: Tone.danger)
                        : const Icon(Icons.download_rounded),
                    onTap: doc.downloadUrl == null
                        ? null
                        : () => downloadAndOpen(
                            context,
                            ref,
                            url: doc.downloadUrl!,
                            fileName:
                                doc.fileName ?? '${doc.type}-${doc.id}.pdf',
                          ),
                  ),
                  const SizedBox(height: 8),
                ],
              ],
          ],
        ),
      ),
    );
  }
}
