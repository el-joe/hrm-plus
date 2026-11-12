import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../../notifications/data/notifications_repository.dart';

final notificationPreferencesProvider =
    FutureProvider.autoDispose<NotificationPreferences>(
      (ref) => ref.watch(notificationsRepositoryProvider).preferences(),
    );

/// Per event and channel (in-app, e-mail, push). Mandatory events cannot be
/// switched off.
class NotificationPreferencesScreen extends ConsumerStatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  ConsumerState<NotificationPreferencesScreen> createState() =>
      _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState
    extends ConsumerState<NotificationPreferencesScreen> {
  /// event → channel → value (edits not yet saved).
  final Map<String, Map<String, bool>> _changes = {};
  bool _saving = false;

  bool _value(PreferenceEvent event, String channel) =>
      _changes[event.event]?[channel] ?? event.channels[channel] ?? false;

  Future<void> _save(NotificationPreferences prefs) async {
    final l = context.l10n;
    final choices = <String, Map<String, bool>>{
      for (final module in prefs.modules)
        for (final event in module.events)
          if (!event.mandatory)
            event.event: {
              for (final channel in event.channels.keys)
                channel: _value(event, channel),
            },
    };
    setState(() => _saving = true);
    try {
      await ref.read(notificationsRepositoryProvider).savePreferences(choices);
      _changes.clear();
      ref.invalidate(notificationPreferencesProvider);
      if (mounted) {
        showSnack(context, l.preferencesSaved, tone: SnackTone.success);
      }
    } catch (error) {
      if (mounted) {
        showSnack(context, failureMessage(l, error), tone: SnackTone.error);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final value = ref.watch(notificationPreferencesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.notificationPreferences)),
      bottomNavigationBar: value.hasValue
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(AppSizes.pagePadding),
                child: LoadingButton(
                  label: l.save,
                  loading: _saving,
                  onPressed: _changes.isEmpty
                      ? null
                      : () => _save(value.requireValue),
                ),
              ),
            )
          : null,
      body: AsyncView<NotificationPreferences>(
        value: value,
        onRefresh: () =>
            ref.refresh(notificationPreferencesProvider.future).then((_) {}),
        isEmpty: (p) => p.modules.isEmpty,
        builder: (prefs) {
          final channels = prefs.channels.where((c) => c.available).toList();
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              for (final module in prefs.modules) ...[
                SectionCard(
                  title: module.label,
                  children: [
                    for (final event in module.events) ...[
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(event.label, style: AppType.labelMd),
                      ),
                      Wrap(
                        spacing: 8,
                        children: [
                          for (final channel in channels)
                            if (event.channels.containsKey(channel.key))
                              FilterChip(
                                label: Text(channel.label),
                                selected: _value(event, channel.key),
                                onSelected: event.mandatory
                                    ? null
                                    : (selected) => setState(
                                        () =>
                                            (_changes[event.event] ??=
                                                    {})[channel.key] =
                                                selected,
                                      ),
                              ),
                        ],
                      ),
                      if (event.mandatory)
                        Text(l.mandatoryNotification, style: AppType.captionSm),
                    ],
                  ],
                ),
                const SizedBox(height: 12),
              ],
            ],
          );
        },
      ),
    );
  }
}
