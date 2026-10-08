import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/config/app_config.dart';
import 'core/router/app_router.dart';
import 'core/storage/secure_storage.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_theme.dart';
import 'shared/widgets/service_number_watermark.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    AppConfig.serverOverride = await secureStorage.readServerUrl();
  } catch (_) {
    // Storage unavailable: fall back to the build-time server.
  }
  runApp(const ProviderScope(child: NISconnectApp()));
}

class NISconnectApp extends StatelessWidget {
  const NISconnectApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'NISconnect',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.system, // Light / Dark / System
      routerConfig: appRouter,
      builder: (context, child) => _ResponsiveFrame(
        child: ServiceNumberWatermark(child: child!),
      ),
    );
  }
}

/// The UI is designed for phone-width screens. On wide screens (desktop
/// browsers, tablets in landscape) it is centred in a phone-width column on a
/// brand-coloured backdrop instead of being stretched edge to edge.
class _ResponsiveFrame extends StatelessWidget {
  const _ResponsiveFrame({required this.child});

  final Widget child;

  static const double _maxWidth = 480;

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    if (media.size.width <= _maxWidth + 120) return child;

    return ColoredBox(
      color: AppColors.darkGreen,
      child: Center(
        child: Container(
          width: _maxWidth,
          clipBehavior: Clip.antiAlias,
          decoration: const BoxDecoration(
            boxShadow: [BoxShadow(color: Colors.black38, blurRadius: 24)],
          ),
          child: MediaQuery(
            data: media.copyWith(size: Size(_maxWidth, media.size.height)),
            child: child,
          ),
        ),
      ),
    );
  }
}
