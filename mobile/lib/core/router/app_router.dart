import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/onboarding_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/auth/presentation/welcome_screen.dart';
import '../../features/chat/presentation/conversation_screen.dart';
import '../../features/home/presentation/home_shell.dart';

/// Application routes. The splash decides between welcome and home based on a
/// stored token.
final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (_, __) => const SplashScreen()),
    GoRoute(path: '/welcome', builder: (_, __) => const WelcomeScreen()),
    GoRoute(path: '/onboarding', builder: (_, __) => const OnboardingScreen()),
    GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
    GoRoute(path: '/home', builder: (_, __) => const HomeShell()),
    GoRoute(path: '/home/directory', builder: (_, __) => const HomeShell(initialIndex: 2)),
    GoRoute(
      path: '/home/chat/:id',
      builder: (_, state) => ConversationScreen(conversationId: state.pathParameters['id']!),
    ),
  ],
);
