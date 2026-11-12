/// Response payloads shaped exactly like the real API (captured with curl
/// from the development backend as employee1@hrmplus.test).
library;

Map<String, Object?> userJson() => {
  'id': 12,
  'name': 'Sherif Soliman',
  'email': 'employee1@hrmplus.test',
  'account_type': 'employee',
  'status': 'active',
  'locale': 'ar',
  'employee_id': 24,
  'last_login_at': '2026-11-12T03:38:47+00:00',
};

Map<String, Object?> loginJson({String token = '1|first', String? expiresAt}) =>
    {
      'token': token,
      'token_type': 'Bearer',
      'expires_at': expiresAt ?? '2026-12-12T03:38:47+00:00',
      'user': userJson(),
    };

Map<String, Object?> todayJson({
  String faceMode = 'off',
  bool enrolled = true,
  bool allowAnywhere = false,
  bool mobileEnabled = true,
  bool employed = true,
  String state = 'not_checked_in',
  String nextAction = 'check_in',
  int maxAccuracy = 50,
  int tolerance = 20,
}) => {
  'date': '2026-11-12',
  'timezone': 'Africa/Cairo',
  'server_time': '2026-11-12T06:10:00Z',
  'employed': employed,
  'is_working_day': true,
  'is_rest_day': false,
  'holiday': null,
  'state': state,
  'next_action': nextAction,
  'within_shift_window': true,
  'shifts': [
    {
      'id': 1,
      'code': 'MOR',
      'name': 'Morning',
      'start_at': '2026-11-12T06:00:00Z',
      'end_at': '2026-11-12T14:30:00Z',
      'start_local': '08:00',
      'end_local': '16:30',
      'check_in_opens_at': '2026-11-12T04:00:00Z',
      'check_out_closes_at': '2026-11-12T18:30:00Z',
    },
  ],
  'punches': <Object?>[],
  'locations': {
    'allow_anywhere': allowAnywhere,
    'source': 'branch',
    'items': [
      {
        'id': 1,
        'name': 'Main building',
        'latitude': '30.0285310',
        'longitude': '31.4725180',
        'radius_meters': 150,
        'checkout_radius_meters': 150,
      },
    ],
  },
  'rules': {
    'mobile_checkin_enabled': mobileEnabled,
    'max_accuracy_meters': maxAccuracy,
    'accuracy_tolerance_meters': tolerance,
    'max_time_drift_seconds': 300,
    'mock_location_action': 'review',
    'outside_window_action': 'review',
  },
  'face': {
    'mode': faceMode,
    'max_attempts': 3,
    'remaining_attempts': 3,
    'provider': 'simulated',
    'is_simulated': true,
    'consent': {
      'current_version': '1.0',
      'given': enrolled,
      'given_version': '1.0',
      'given_at': '2026-11-12T03:25:40Z',
    },
    'enrolment': enrolled
        ? {
            'enrolled_at': '2026-11-12T03:25:40Z',
            'provider': 'simulated',
            'is_simulated': true,
            'expires_at': '2028-11-11T03:25:40Z',
          }
        : null,
    'enrolled': enrolled,
  },
  'pending_reviews': 0,
  'days': <Object?>[],
};

Map<String, Object?> attemptJson({
  String uuid = '3f8ee251-23cf-4f52-b8b0-5a258d3a0db3',
  String outcome = 'accepted',
  String direction = 'in',
  List<Map<String, String>> reasons = const [],
  int distance = 24,
  bool withinGeofence = true,
}) => {
  'request_uuid': uuid,
  'direction': direction,
  'outcome': outcome,
  'review_status': outcome == 'flagged' ? 'pending' : null,
  'recorded': outcome == 'accepted',
  'server_time': '2026-11-12T06:12:00Z',
  'server_time_local': '08:12',
  'work_date': '2026-11-12',
  'reasons': reasons,
  'location': {
    'id': 1,
    'name': 'Main building',
    'distance_meters': distance,
    'allowed_radius_meters': 150,
    'within_geofence': withinGeofence,
    'allow_anywhere': false,
    'accuracy_meters': 12,
  },
  'within_shift_window': true,
  'time_drift_seconds': 0,
  'face': {'mode': 'optional', 'result': null, 'is_simulated': true},
};

Map<String, Object?> payslipJson({int id = 82, bool withLines = false}) => {
  'id': id,
  'number': 'PS-2026-000$id',
  'period': {
    'code': '2026-10',
    'name': 'October 2026 payroll',
    'start_date': '2026-10-01',
    'end_date': '2026-10-31',
    'pay_date': '2026-10-31',
  },
  'run_type': 'regular',
  'currency': 'EGP',
  'gross_pay': '13396.25',
  'total_deductions': '2397.26',
  'net_pay': '10998.99',
  'payment_status': 'paid',
  'payment_status_label': 'Paid',
  'amount_paid': '10998.99',
  'paid_on': '2026-10-31',
  'issued_at': '2026-10-29T09:30:00+00:00',
  'pdf_url': 'https://api.hrm.test/api/v1/me/payslips/$id/pdf',
  if (withLines)
    'lines': {
      'basic_salary': '10237.00',
      'earnings': [
        {
          'name': 'Basic salary',
          'code': 'BASIC',
          'amount': '10237.00',
          'quantity': null,
          'note': 'Monthly salary 10,237.00, full period',
        },
        {
          'name': 'Housing allowance',
          'code': 'HOUSING',
          'amount': '2559.25',
          'quantity': null,
          'note': null,
        },
      ],
      'deductions': [
        {
          'name': 'Late arrival deduction',
          'code': 'LATE',
          'amount': '14.93',
          'quantity': '21',
          'note': '21 late minutes × 0.71 = 14.93',
        },
      ],
      'loans': <Object?>[],
      'statutory': [
        {
          'name': 'Social insurance (employee)',
          'code': 'SI_EMP',
          'amount': '1275.79',
          'quantity': null,
          'note': null,
        },
      ],
      'demo_statutory': true,
    },
};

Map<String, Object?> pagination({int page = 1, int last = 1, int total = 1}) =>
    {
      'pagination': {
        'current_page': page,
        'per_page': 20,
        'total': total,
        'last_page': last,
      },
    };
