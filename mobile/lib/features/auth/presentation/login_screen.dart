import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/platform/client_platform.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/primary_button.dart';
import '../../../shared/widgets/service_number_field.dart';
import '../data/auth_repository.dart';

/// Login by Service Number + PIN on this device.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _serviceNumber = TextEditingController();
  final _pin = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).login(
        serviceNumber: _serviceNumber.text,
        pin: _pin.text,
        device: ClientPlatform.device,
      );
      if (mounted) context.go('/home');
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sign in')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Welcome back', style: AppTypography.h1),
              const SizedBox(height: 24),
              ServiceNumberField(controller: _serviceNumber),
              const SizedBox(height: 16),
              TextField(
                controller: _pin,
                obscureText: true,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(labelText: 'PIN', errorText: _error),
              ),
              const SizedBox(height: 20),
              PrimaryButton(label: 'Sign in', loading: _loading, onPressed: _submit),
            ],
          ),
        ),
      ),
    );
  }
}
