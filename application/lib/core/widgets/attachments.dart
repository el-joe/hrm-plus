import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../errors/failure_message.dart';
import '../files/upload_file.dart';
import '../l10n/l10n.dart';
import '../network/api_client.dart';
import '../providers.dart';
import 'states.dart';

/// Opens the system file picker; returns the chosen files with their bytes
/// (works on every platform, uploads are multipart from memory).
Future<List<UploadFile>> pickUploadFiles({
  List<String> extensions = const ['pdf', 'jpg', 'jpeg', 'png'],
}) async {
  final picked = await FilePicker.pickFiles(
    type: FileType.custom,
    allowedExtensions: extensions,
  );
  return [
    for (final file in picked)
      UploadFile(name: file.name, bytes: await file.readAsBytes()),
  ];
}

/// Picked-file chips with remove buttons and an "Add file" action.
class AttachmentPicker extends StatelessWidget {
  const AttachmentPicker({
    super.key,
    required this.files,
    required this.onChanged,
    this.maxFiles = 5,
    this.extensions = const ['pdf', 'jpg', 'jpeg', 'png'],
    this.errorText,
  });

  final List<UploadFile> files;
  final ValueChanged<List<UploadFile>> onChanged;
  final int maxFiles;
  final List<String> extensions;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final file in files)
              InputChip(
                avatar: const Icon(Icons.attach_file_rounded, size: 16),
                label: Text(file.name, overflow: TextOverflow.ellipsis),
                onDeleted: () => onChanged([...files]..remove(file)),
              ),
            if (files.length < maxFiles)
              ActionChip(
                avatar: const Icon(Icons.add_rounded, size: 16),
                label: Text(l.addAttachment),
                onPressed: () async {
                  final picked = await pickUploadFiles(extensions: extensions);
                  if (picked.isEmpty) return;
                  onChanged([...files, ...picked].take(maxFiles).toList());
                },
              ),
          ],
        ),
        if (errorText != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              errorText!,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ),
      ],
    );
  }
}

/// Downloads an authenticated file URL and opens it.
Future<void> downloadAndOpen(
  BuildContext context,
  WidgetRef ref, {
  required String url,
  required String fileName,
  Map<String, dynamic>? query,
}) async {
  final l = context.l10n;
  showSnack(context, l.downloading);
  try {
    final ApiClient api = ref.read(apiClientProvider);
    final bytes = await api.download(url, query: query);
    final opened = await ref
        .read(fileOpenerProvider)
        .saveAndOpen(bytes, fileName);
    if (!context.mounted) return;
    if (!opened) showSnack(context, l.cannotOpenFile, tone: SnackTone.error);
  } catch (error) {
    if (!context.mounted) return;
    showSnack(context, failureMessage(l, error), tone: SnackTone.error);
  }
}
