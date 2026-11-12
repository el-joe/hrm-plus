import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/errors/failures.dart';
import 'package:hrm_plus/core/files/upload_file.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/features/leave/data/leave_repository.dart';
import 'package:hrm_plus/features/leave/presentation/leave_balance_screen.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/pump.dart';

Map<String, Object?> _preview() => {
  'total': '3.00',
  'by_year': {'2026': '3.00'},
  'days': [
    for (final d in ['2026-12-14', '2026-12-15', '2026-12-16'])
      {
        'date': d,
        'leave_year': 2026,
        'day_part': 'full',
        'amount': '1.00',
        'hours': null,
        'is_counted': true,
        'day_kind': 'working',
      },
  ],
  'available_after': {'2026': '14.00'},
  'needs_attachment': false,
};

Map<String, Object?> _request() => {
  'id': 46,
  'leave_type': {
    'id': 1,
    'code': 'ANNUAL',
    'name': 'Annual leave',
    'color': 'chart-1',
  },
  'start_date': '2026-12-14',
  'end_date': '2026-12-16',
  'day_part': 'full',
  'hours': null,
  'start_time': null,
  'days': '3.00',
  'reason': 'Family trip',
  'status': 'pending',
  'status_label': 'Pending',
  'current_step': 'Manager approval',
  'rejection_reason': null,
  'cancellation_reason': null,
  'can_cancel': true,
  'cancel_needs_approval': false,
  'attachments': <Object?>[],
  'created_at': '2026-11-12T03:25:54Z',
  'decided_at': null,
};

Map<String, Object?> _balance() => {
  'leave_type': {
    'id': 1,
    'code': 'ANNUAL',
    'name': 'Annual leave',
    'color': 'chart-1',
    'unit': 'half_day',
  },
  'year': 2026,
  'tracks_balance': true,
  'entitlement': '24.00',
  'carried_in': '10.00',
  'accrued': '24.00',
  'adjusted': '0.00',
  'used': '1.00',
  'expired': '10.00',
  'encashed': '0.00',
  'carried_out': '0.00',
  'balance': '23.00',
  'pending': '6.00',
  'available': '17.00',
  'taken': '1.00',
};

const _draft = LeaveDraft(
  typeId: 1,
  startDate: '2026-12-14',
  endDate: '2026-12-16',
  reason: 'Family trip',
);

void main() {
  group('LeaveRepository', () {
    test('preview sends the draft and keeps decimals as strings', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/leave/requests/preview',
          (_) => FakeResponse.ok(_preview()),
        );
      final preview = await LeaveRepository(testApiClient(adapter))
          .preview(_draft);

      expect(preview.total, '3.00');
      expect(preview.availableAfter['2026'], '14.00');
      expect(preview.needsAttachment, isFalse);
      expect(jsonBody(adapter.requests.single), {
        'leave_type_id': 1,
        'start_date': '2026-12-14',
        'end_date': '2026-12-16',
        'day_part': 'full',
        'reason': 'Family trip',
      });
    });

    test('preview validation errors are typed', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/leave/requests/preview',
          (_) => FakeResponse.error(
            422,
            'validation_error',
            errors: {
              'start_date': ['The From field is required.'],
            },
          ),
        );
      final failure = await LeaveRepository(testApiClient(adapter))
          .preview(_draft)
          .then<Object?>((_) => null, onError: (Object e) => e);
      expect((failure! as ValidationFailure).first('start_date'), isNotNull);
    });

    test('submit without files posts JSON and parses the request', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/leave/requests',
          (_) => FakeResponse.ok(_request(), status: 201),
        );
      final request = await LeaveRepository(testApiClient(adapter))
          .submit(_draft);

      expect(request.id, 46);
      expect(request.days, '3.00');
      expect(request.canCancel, isTrue);
      expect(adapter.requests.single.data, isA<Map<String, Object>>());
    });

    test('submit with files posts multipart with attachments[]', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/leave/requests',
          (_) => FakeResponse.ok(_request(), status: 201),
        );
      await LeaveRepository(testApiClient(adapter)).submit(
        _draft,
        attachments: [
          UploadFile(name: 'note.pdf', bytes: Uint8List.fromList([1, 2, 3])),
        ],
      );

      final form = adapter.requests.single.data as FormData;
      expect(form.files.single.key, 'attachments[]');
      expect(form.files.single.value.filename, 'note.pdf');
      expect(
        form.fields.map((e) => e.key),
        containsAll(['leave_type_id', 'start_date', 'end_date', 'day_part']),
      );
    });

    test('half-day drafts omit end_date and send the day part', () {
      const draft = LeaveDraft(
        typeId: 1,
        startDate: '2026-12-14',
        dayPart: 'first_half',
      );
      expect(draft.toFields().containsKey('end_date'), isFalse);
      expect(draft.toFields()['day_part'], 'first_half');
    });
  });

  group('LeaveBalanceScreen states', () {
    Future<FakeAdapter> pump(WidgetTester tester, FakeHandler handler) async {
      final adapter = FakeAdapter()..on('GET me/leave/balances', handler);
      await pumpScreen(
        tester,
        const LeaveBalanceScreen(),
        locale: const Locale('en'),
        overrides: [
          apiClientProvider.overrideWithValue(testApiClient(adapter)),
        ],
      );
      return adapter;
    }

    testWidgets('loading → skeleton, then balances', (tester) async {
      await pump(
        tester,
        (_) => FakeResponse.ok(
          [_balance()],
          meta: {'year': 2026, 'year_label': '2026'},
        ),
      );
      expect(find.byKey(const Key('skeleton-list')), findsOneWidget);
      await tester.pumpAndSettle();
      expect(find.text('Annual leave'), findsOneWidget);
      expect(find.text('17 days'), findsOneWidget);
    });

    testWidgets('empty list → empty state', (tester) async {
      await pump(
        tester,
        (_) => FakeResponse.ok(<Object?>[], meta: {'year': 2026}),
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('empty-state')), findsOneWidget);
    });

    testWidgets('error → error state with retry', (tester) async {
      var calls = 0;
      await pump(tester, (request) {
        calls++;
        if (calls == 1) connectionError(request);
        return FakeResponse.ok([_balance()], meta: {'year': 2026});
      });
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('error-state')), findsOneWidget);
      expect(find.text('You are offline'), findsOneWidget);

      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Annual leave'), findsOneWidget);
    });
  });
}
