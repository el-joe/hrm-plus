import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/files/upload_file.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/attachments.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/requests_repository.dart';

/// New HR request. The category drives the form: its custom fields, letter
/// language for letters, proposed values for a profile change.
class NewRequestScreen extends ConsumerStatefulWidget {
  const NewRequestScreen({
    super.key,
    this.initialCategoryId,
    this.initialCategoryCode,
  });

  final int? initialCategoryId;

  /// e.g. PROFILE_CORRECTION from the profile screen.
  final String? initialCategoryCode;

  @override
  ConsumerState<NewRequestScreen> createState() => _NewRequestScreenState();
}

class _NewRequestScreenState extends ConsumerState<NewRequestScreen> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _description = TextEditingController();
  final Map<String, TextEditingController> _fieldControllers = {};
  final Map<String, String> _selects = {};
  RequestCategory? _category;
  String? _letterLanguage;
  List<UploadFile> _files = [];
  bool _busy = false;
  ValidationFailure? _errors;
  String? _error;

  @override
  void dispose() {
    _subject.dispose();
    _description.dispose();
    for (final c in _fieldControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  TextEditingController _controller(String key) =>
      _fieldControllers.putIfAbsent(key, TextEditingController.new);

  void _select(RequestCategory? category) => setState(() {
    _category = category;
    _letterLanguage = category?.letterLanguages.isNotEmpty == true
        ? category!.letterLanguages.first
        : null;
    _errors = null;
  });

  Future<void> _submit() async {
    final category = _category;
    if (category == null || !_form.currentState!.validate()) return;
    final l = context.l10n;
    if (category.requiresAttachment && _files.isEmpty) {
      setState(() => _error = l.attachmentRequired);
      return;
    }
    final changes = <String, String>{};
    final fields = <String, String>{};
    if (category.isProfileChange) {
      _fieldControllers.forEach((key, c) {
        if (key.startsWith('change.') && c.text.trim().isNotEmpty) {
          changes[key.substring(7)] = c.text.trim();
        }
      });
      if (changes.isEmpty) {
        setState(() => _error = l.profileChangeEmpty);
        return;
      }
    } else {
      for (final field in category.fields) {
        final value = field.type == 'select'
            ? _selects[field.key]
            : _controller('field.${field.key}').text;
        if (value != null && value.trim().isNotEmpty) fields[field.key] = value;
      }
    }

    setState(() {
      _busy = true;
      _error = null;
      _errors = null;
    });
    try {
      final created = await ref
          .read(requestsRepositoryProvider)
          .create(
            RequestDraft(
              categoryId: category.id,
              subject: _subject.text,
              description: _description.text,
              letterLanguage: category.isLetter ? _letterLanguage : null,
              fields: fields,
              changes: changes,
              attachments: _files,
            ),
          );
      if (!mounted) return;
      showSnack(context, l.requestSubmitted, tone: SnackTone.success);
      context.pushReplacement(Routes.request(created.summary.id));
    } on ValidationFailure catch (failure) {
      setState(() => _errors = failure);
    } catch (error) {
      setState(() => _error = failureMessage(l, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final categories = ref.watch(requestCategoriesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.newRequest)),
      body: AsyncView<RequestCategories>(
        value: categories,
        onRefresh: () =>
            ref.refresh(requestCategoriesProvider.future).then((_) {}),
        isEmpty: (data) => data.categories.isEmpty || !data.enabled,
        empty: EmptyState(title: l.requestsDisabled),
        builder: (data) {
          if (_category == null &&
              (widget.initialCategoryId != null ||
                  widget.initialCategoryCode != null)) {
            final match = data.categories.where(
              (c) =>
                  c.id == widget.initialCategoryId ||
                  c.code == widget.initialCategoryCode,
            );
            if (match.isNotEmpty) {
              WidgetsBinding.instance.addPostFrameCallback(
                (_) => _select(match.first),
              );
            }
          }
          final category = _category;
          return Form(
            key: _form,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(AppSizes.pagePadding),
              children: [
                if (_error != null) ...[
                  InlineBanner(message: _error!, tone: Tone.danger),
                  const SizedBox(height: 12),
                ],
                if (_errors != null) ...[
                  InlineBanner(
                    message: _errors!.all.isEmpty
                        ? (_errors!.message ?? l.errorValidation)
                        : _errors!.all.join('\n'),
                    tone: Tone.danger,
                  ),
                  const SizedBox(height: 12),
                ],
                DropdownButtonFormField<RequestCategory>(
                  key: ValueKey(category?.id),
                  initialValue: category,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: l.category),
                  items: [
                    for (final c in data.categories)
                      DropdownMenuItem(value: c, child: Text(c.name)),
                  ],
                  validator: (v) => v == null ? l.validationRequired : null,
                  onChanged: _select,
                ),
                if (category?.description != null) ...[
                  const SizedBox(height: 6),
                  Text(category!.description!, style: AppType.captionMd),
                ],
                if (category != null) ...[
                  const SizedBox(height: 16),
                  if (!category.isProfileChange)
                    TextFormField(
                      controller: _subject,
                      maxLength: 200,
                      decoration: InputDecoration(
                        labelText: l.subjectOptional,
                        errorText: _errors?.first('subject'),
                      ),
                    ),
                  if (category.isLetter &&
                      category.letterLanguages.length > 1) ...[
                    Text(l.letterLanguage, style: AppType.labelMd),
                    const SizedBox(height: 8),
                    SegmentedButton<String>(
                      segments: [
                        for (final lang in category.letterLanguages)
                          ButtonSegment(
                            value: lang,
                            label: Text(
                              lang == 'ar'
                                  ? l.arabic
                                  : lang == 'en'
                                  ? l.english
                                  : lang == 'both'
                                  ? l.bothLanguages
                                  : lang,
                            ),
                          ),
                      ],
                      selected: {
                        _letterLanguage ?? category.letterLanguages.first,
                      },
                      showSelectedIcon: false,
                      onSelectionChanged: (v) =>
                          setState(() => _letterLanguage = v.first),
                    ),
                    const SizedBox(height: 16),
                  ],
                  for (final field in category.fields) ...[
                    _CustomField(
                      field: field,
                      controller: _controller('field.${field.key}'),
                      selected: _selects[field.key],
                      errorText: _errors?.first('fields.${field.key}'),
                      onSelect: (v) => setState(() => _selects[field.key] = v),
                    ),
                    const SizedBox(height: 16),
                  ],
                  if (category.isProfileChange)
                    _ProfileChanges(
                      controllerFor: (field) => _controller('change.$field'),
                      errors: _errors,
                    ),
                  TextFormField(
                    controller: _description,
                    maxLines: 4,
                    maxLength: 5000,
                    decoration: InputDecoration(
                      labelText: category.isLetter || category.isProfileChange
                          ? l.notesOptional
                          : l.description,
                      errorText: _errors?.first('description'),
                    ),
                    validator: (v) =>
                        !category.isLetter &&
                            !category.isProfileChange &&
                            (v ?? '').trim().isEmpty
                        ? l.validationRequired
                        : null,
                  ),
                  const SizedBox(height: 8),
                  Text(l.attachments, style: AppType.labelMd),
                  const SizedBox(height: 8),
                  AttachmentPicker(
                    files: _files,
                    maxFiles: data.maxFiles,
                    extensions: data.extensions.isEmpty
                        ? const ['pdf', 'jpg', 'jpeg', 'png']
                        : data.extensions,
                    errorText: _errors?.first('attachments'),
                    onChanged: (files) => setState(() => _files = files),
                  ),
                  const SizedBox(height: 24),
                  LoadingButton(
                    key: const Key('request-submit'),
                    label: l.submitRequest,
                    loading: _busy,
                    onPressed: _submit,
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _CustomField extends StatelessWidget {
  const _CustomField({
    required this.field,
    required this.controller,
    required this.selected,
    required this.onSelect,
    this.errorText,
  });

  final CategoryField field;
  final TextEditingController controller;
  final String? selected;
  final ValueChanged<String> onSelect;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    if (field.type == 'select' && field.options.isNotEmpty) {
      return DropdownButtonFormField<String>(
        initialValue: selected,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: field.label,
          errorText: errorText,
        ),
        items: [
          for (final option in field.options)
            DropdownMenuItem(value: option, child: Text(option)),
        ],
        validator: (v) =>
            field.required && v == null ? l.validationRequired : null,
        onChanged: (v) {
          if (v != null) onSelect(v);
        },
      );
    }
    return TextFormField(
      controller: controller,
      maxLines: field.type == 'textarea' ? 3 : 1,
      keyboardType: field.type == 'number'
          ? TextInputType.number
          : TextInputType.text,
      decoration: InputDecoration(labelText: field.label, errorText: errorText),
      validator: (v) => field.required && (v ?? '').trim().isEmpty
          ? l.validationRequired
          : null,
    );
  }
}

class _ProfileChanges extends ConsumerWidget {
  const _ProfileChanges({required this.controllerFor, required this.errors});

  final TextEditingController Function(String field) controllerFor;
  final ValidationFailure? errors;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final fields = ref.watch(profileFieldsProvider);
    return fields.when(
      loading: () => const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (error, _) => ErrorState(
        error: error,
        onRetry: () => ref.invalidate(profileFieldsProvider),
      ),
      data: (list) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InlineBanner(message: l.profileChangeHint, tone: Tone.info),
          const SizedBox(height: 12),
          for (final field in list) ...[
            TextFormField(
              controller: controllerFor(field.field),
              decoration: InputDecoration(
                labelText: field.label,
                hintText: field.current,
                helperText: field.current == null
                    ? null
                    : l.currentValue(field.current!),
                errorText: errors?.first('changes.${field.field}'),
              ),
            ),
            const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}
