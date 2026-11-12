import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/profile_repository.dart';

/// Employee profile (read-only). Changes go through a profile change request.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final t = context.tokens;
    final f = ref.watch(formatterProvider);
    final value = ref.watch(profileProvider);

    String? name(Json? json) => json?.strOrNull('name');
    String? date(String? v) =>
        v == null ? null : f.date(Formatter.parseDate(v));

    return Scaffold(
      appBar: AppBar(title: Text(l.profileTitle)),
      body: AsyncView<EmployeeProfile>(
        value: value,
        onRefresh: () => ref.refresh(profileProvider.future).then((_) {}),
        builder: (p) {
          final e = p.employment;
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSizes.pagePadding),
            children: [
              Center(
                child: CircleAvatar(
                  radius: 40,
                  backgroundColor: t.actionPrimarySubtle,
                  child: Icon(
                    Icons.person_rounded,
                    size: 40,
                    color: t.actionPrimary,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                p.name,
                textAlign: TextAlign.center,
                style: AppType.headingH4,
              ),
              Text(
                [p.employeeNumber, ?name(e.obj('job_title'))].join(' · '),
                textAlign: TextAlign.center,
                style: AppType.bodySm.copyWith(color: t.textSecondary),
              ),
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.employment,
                children: [
                  InfoRow(label: l.status, value: e.strOrNull('status_label')),
                  InfoRow(
                    label: l.hireDate,
                    value: date(e.strOrNull('hire_date')),
                  ),
                  InfoRow(label: l.branch, value: name(e.obj('branch'))),
                  InfoRow(
                    label: l.department,
                    value: name(e.obj('department')),
                  ),
                  InfoRow(label: l.position, value: name(e.obj('position'))),
                  InfoRow(label: l.jobGrade, value: name(e.obj('job_grade'))),
                  InfoRow(
                    label: l.employmentType,
                    value: name(e.obj('employment_type')),
                  ),
                  InfoRow(
                    label: l.workLocation,
                    value: name(e.obj('work_location')),
                  ),
                  InfoRow(label: l.manager, value: name(e.obj('manager'))),
                ],
              ),
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.contact,
                children: [
                  InfoRow(
                    label: l.workEmail,
                    value: p.contact.strOrNull('email'),
                  ),
                  InfoRow(
                    label: l.personalEmail,
                    value: p.contact.strOrNull('personal_email'),
                  ),
                  InfoRow(label: l.phone, value: p.contact.strOrNull('phone')),
                  InfoRow(
                    label: l.address,
                    value: p.contact.strOrNull('address'),
                  ),
                  InfoRow(label: l.city, value: p.contact.strOrNull('city')),
                ],
              ),
              const SizedBox(height: AppSizes.gap),
              SectionCard(
                title: l.personal,
                children: [
                  InfoRow(
                    label: l.dateOfBirth,
                    value: date(p.personal.strOrNull('date_of_birth')),
                  ),
                  InfoRow(
                    label: l.nationalId,
                    value: p.personal.strOrNull('national_id_masked'),
                  ),
                  InfoRow(
                    label: l.nationality,
                    value: p.personal.strOrNull('nationality'),
                  ),
                ],
              ),
              if (p.emergencyContacts.isNotEmpty) ...[
                const SizedBox(height: AppSizes.gap),
                SectionCard(
                  title: l.emergencyContacts,
                  children: [
                    for (final c in p.emergencyContacts)
                      InfoRow(
                        label: '${c.str('name')} (${c.str('relationship')})',
                        value: c.strOrNull('phone'),
                      ),
                  ],
                ),
              ],
              const SizedBox(height: AppSizes.gap),
              OutlinedButton.icon(
                onPressed: () => context.push(
                  Uri(
                    path: Routes.newRequest,
                    queryParameters: {'code': 'PROFILE_CORRECTION'},
                  ).toString(),
                ),
                icon: const Icon(Icons.edit_note_rounded),
                label: Text(l.requestProfileChange),
              ),
            ],
          );
        },
      ),
    );
  }
}
