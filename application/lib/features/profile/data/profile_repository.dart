import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

/// `EmployeeProfileResource` (`GET me/profile`). Sections are kept as JSON
/// maps: the screen shows labelled rows, nothing is computed from them.
class EmployeeProfile {
  const EmployeeProfile({
    required this.employeeNumber,
    required this.name,
    required this.personal,
    required this.contact,
    required this.employment,
    required this.emergencyContacts,
    this.photoUrl,
  });

  factory EmployeeProfile.fromJson(Json json) => EmployeeProfile(
    employeeNumber: json.str('employee_number'),
    name: json.str('name'),
    photoUrl: json.strOrNull('photo_url'),
    personal: json.obj('personal') ?? const {},
    contact: json.obj('contact') ?? const {},
    employment: json.obj('employment') ?? const {},
    emergencyContacts: json.objList('emergency_contacts'),
  );

  final String employeeNumber;
  final String name;
  final String? photoUrl;
  final Json personal;
  final Json contact;
  final Json employment;
  final List<Json> emergencyContacts;
}

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Future<EmployeeProfile> profile() async =>
      EmployeeProfile.fromJson((await _api.get('me/profile')).json);
}

final profileRepositoryProvider = Provider<ProfileRepository>(
  (ref) => ProfileRepository(ref.watch(apiClientProvider)),
);

final profileProvider = FutureProvider.autoDispose<EmployeeProfile>(
  (ref) => ref.watch(profileRepositoryProvider).profile(),
);
