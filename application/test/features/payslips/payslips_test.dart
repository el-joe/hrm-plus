import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/core/utils/money.dart';
import 'package:hrm_plus/features/payslips/data/payslip_repository.dart';
import 'package:hrm_plus/features/payslips/presentation/payslip_detail_screen.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/fake_services.dart';
import '../../helpers/fixtures.dart';
import '../../helpers/pump.dart';

final _pdfBytes = [0x25, 0x50, 0x44, 0x46, 0x2D]; // "%PDF-"

void main() {
  group('PayslipRepository', () {
    test('list is paginated and money stays as strings', () async {
      final adapter = FakeAdapter()
        ..on(
          'GET me/payslips',
          (_) => FakeResponse.ok([
            payslipJson(),
            payslipJson(id: 81),
          ], meta: pagination(total: 2)),
        );
      final page = await PayslipRepository(testApiClient(adapter))
          .list(year: 2026);

      expect(page.items, hasLength(2));
      expect(page.items.first.netPay, '10998.99');
      expect(adapter.requests.single.uri.queryParameters['year'], '2026');
    });

    test('show parses the salary breakdown lines', () async {
      final adapter = FakeAdapter()
        ..on(
          'GET me/payslips/82',
          (_) => FakeResponse.ok(payslipJson(withLines: true)),
        );
      final payslip = await PayslipRepository(testApiClient(adapter)).show(82);

      final lines = payslip.lines!;
      expect(lines.earnings.map((l) => l.code), ['BASIC', 'HOUSING']);
      expect(lines.deductions.single.amount, '14.93');
      expect(lines.statutory.single.code, 'SI_EMP');
      expect(lines.demoStatutory, isTrue);
    });

    test('pdf downloads bytes in the requested language', () async {
      final adapter = FakeAdapter()
        ..on('GET me/payslips/82/pdf', (_) => FakeResponse(200, _pdfBytes));
      final bytes = await PayslipRepository(testApiClient(adapter))
          .pdf(82, 'ar');

      expect(bytes, _pdfBytes);
      expect(adapter.requests.single.uri.queryParameters['lang'], 'ar');
    });

    test('Money is exact (no double rounding)', () {
      final gross = Money.parse('13396.25');
      final deductions = Money.parse('2397.26');
      expect((gross - deductions).toApiString(), '10998.99');
      expect(Money.parse('1234567.5').grouped(), '1,234,567.50');
      expect(Money.parse('-14.93').toApiString(), '-14.93');
    });
  });

  group('PayslipDetailScreen', () {
    testWidgets('shows lines and opens the PDF', (tester) async {
      final adapter = FakeAdapter()
        ..on(
          'GET me/payslips/82',
          (_) => FakeResponse.ok(payslipJson(withLines: true)),
        )
        ..on('GET me/payslips/82/pdf', (_) => FakeResponse(200, _pdfBytes));
      final opener = FakeFileOpener();
      await pumpScreen(
        tester,
        const PayslipDetailScreen(id: 82),
        locale: const Locale('en'),
        overrides: [
          apiClientProvider.overrideWithValue(testApiClient(adapter)),
          fileOpenerProvider.overrideWithValue(opener),
        ],
      );
      await tester.pumpAndSettle();

      expect(find.text('Basic salary'), findsOneWidget);
      expect(find.text('10,998.99 EGP'), findsWidgets);
      // The breakdown is a lazy ListView taller than the test surface:
      // scroll the deductions into view before asserting on them.
      final list = find.byType(Scrollable).first;
      await tester.scrollUntilVisible(
        find.text('Late arrival deduction'),
        200,
        scrollable: list,
      );
      expect(find.text('Late arrival deduction'), findsOneWidget);

      await tester.tap(find.byKey(const Key('payslip-pdf')));
      await tester.pumpAndSettle();

      expect(opener.opened.single.$1, 'PS-2026-00082-en.pdf');
      expect(opener.opened.single.$2, _pdfBytes.length);
    });

    testWidgets('a missing payslip shows the error state', (tester) async {
      final adapter = FakeAdapter()
        ..on('GET me/payslips/5', (_) => FakeResponse.error(404, 'not_found'));
      await pumpScreen(
        tester,
        const PayslipDetailScreen(id: 5),
        locale: const Locale('en'),
        overrides: [
          apiClientProvider.overrideWithValue(testApiClient(adapter)),
        ],
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('error-state')), findsOneWidget);
      expect(find.text('This item was not found.'), findsOneWidget);
    });
  });
}
