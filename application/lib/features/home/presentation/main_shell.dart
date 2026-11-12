import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';

/// Bottom navigation with 5 destinations (Figma "Mobile bottom navigation").
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (index) =>
            shell.goBranch(index, initialLocation: index == shell.currentIndex),
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home_rounded),
            label: l.navHome,
          ),
          NavigationDestination(
            icon: const Icon(Icons.schedule_outlined),
            selectedIcon: const Icon(Icons.schedule_rounded),
            label: l.navAttendance,
          ),
          NavigationDestination(
            icon: const Icon(Icons.event_busy_outlined),
            selectedIcon: const Icon(Icons.event_busy_rounded),
            label: l.navLeave,
          ),
          NavigationDestination(
            icon: const Icon(Icons.account_balance_wallet_outlined),
            selectedIcon: const Icon(Icons.account_balance_wallet_rounded),
            label: l.navPayslips,
          ),
          NavigationDestination(
            icon: const Icon(Icons.person_outline_rounded),
            selectedIcon: const Icon(Icons.person_rounded),
            label: l.navAccount,
          ),
        ],
      ),
    );
  }
}
