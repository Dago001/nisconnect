import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../services/presence/presence_service.dart';
import '../../../services/push/push_service.dart';
import '../../calls/presentation/calls_screen.dart';
import '../../chat/presentation/chats_screen.dart';
import '../../directory/presentation/directory_screen.dart';
import '../../groups/presentation/groups_screen.dart';
import '../../settings/presentation/profile_screen.dart';

/// Bottom-navigation shell: Chats · Calls · Directory · Groups · Profile.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key, this.initialIndex = 0});
  final int initialIndex;

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  late int _index = widget.initialIndex;

  static const _screens = [
    ChatsScreen(),
    CallsScreen(),
    DirectoryScreen(),
    GroupsScreen(),
    ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    // Register for push and start reporting presence once the officer is in.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(pushServiceProvider).registerForCurrentDevice();
      ref.read(presenceServiceProvider).start();
    });
  }

  @override
  void dispose() {
    ref.read(presenceServiceProvider).stop();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        indicatorColor: AppColors.lightGreen,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.forum_outlined), selectedIcon: Icon(Icons.forum), label: 'Chats'),
          NavigationDestination(icon: Icon(Icons.call_outlined), selectedIcon: Icon(Icons.call), label: 'Calls'),
          NavigationDestination(icon: Icon(Icons.contacts_outlined), selectedIcon: Icon(Icons.contacts), label: 'Directory'),
          NavigationDestination(icon: Icon(Icons.groups_outlined), selectedIcon: Icon(Icons.groups), label: 'Groups'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}
