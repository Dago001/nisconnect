import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../core/theme/app_typography.dart';
import '../../../services/biometric/biometric_service.dart';
import '../../auth/data/auth_repository.dart';
import 'devices_screen.dart';

final _biometricAvailableProvider = FutureProvider.autoDispose<bool>(
  (ref) => ref.watch(biometricServiceProvider).isAvailable(),
);

final _biometricEnabledProvider = FutureProvider.autoDispose<bool>(
  (ref) => ref.watch(secureStorageProvider).readBiometricUnlock(),
);

/// Change PIN, biometric unlock and a shortcut to signed-in devices.
class SecurityScreen extends ConsumerWidget {
  const SecurityScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final available = ref.watch(_biometricAvailableProvider).valueOrNull ?? false;
    final enabled = ref.watch(_biometricEnabledProvider).valueOrNull ?? true;

    return Scaffold(
      appBar: AppBar(title: const Text('Security')),
      body: ListView(
        children: [
          ListTile(
            leading: const Icon(Icons.pin_outlined),
            title: const Text('Change PIN', style: AppTypography.title),
            subtitle: const Text('The PIN you use to sign in', style: AppTypography.caption),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => showDialog<void>(context: context, builder: (_) => const ChangePinDialog()),
          ),
          SwitchListTile(
            secondary: const Icon(Icons.fingerprint),
            title: const Text('Biometric unlock', style: AppTypography.title),
            subtitle: Text(
              available
                  ? 'Ask for fingerprint or face when opening NISconnect'
                  : 'Not available on this device',
              style: AppTypography.caption,
            ),
            value: available && enabled,
            onChanged: !available
                ? null
                : (on) async {
                    // Turning it off requires proving it is really the officer.
                    if (!on) {
                      final ok = await ref
                          .read(biometricServiceProvider)
                          .authenticate(reason: 'Confirm to turn off biometric unlock');
                      if (!ok) return;
                    }
                    await ref.read(secureStorageProvider).saveBiometricUnlock(on);
                    ref.invalidate(_biometricEnabledProvider);
                  },
          ),
          ListTile(
            leading: const Icon(Icons.devices_outlined),
            title: const Text('Signed-in devices', style: AppTypography.title),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const DevicesScreen())),
          ),
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Your account is locked for a while after repeated wrong PINs. '
              'NISconnect never asks for your PIN in a message or call.',
              style: AppTypography.caption,
            ),
          ),
        ],
      ),
    );
  }
}

class ChangePinDialog extends ConsumerStatefulWidget {
  const ChangePinDialog({super.key});

  @override
  ConsumerState<ChangePinDialog> createState() => _ChangePinDialogState();
}

class _ChangePinDialogState extends ConsumerState<ChangePinDialog> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  String? _currentError;
  String? _nextError;
  String? _confirmError;
  bool _saving = false;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _currentError = _current.text.isEmpty ? 'Enter your current PIN' : null;
      _nextError = _next.text.length < 4 ? 'Use at least 4 digits' : null;
      _confirmError = _confirm.text != _next.text ? "PINs don't match" : null;
      if (_nextError == null && _next.text == _current.text) {
        _nextError = 'Choose a PIN different from your current one';
      }
    });
    if (_currentError != null || _nextError != null || _confirmError != null) return;

    setState(() => _saving = true);
    try {
      await ref.read(authRepositoryProvider).changePin(_current.text, _next.text);
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('PIN changed. Use your new PIN next time you sign in.')));
    } on ApiException catch (e) {
      setState(() {
        final errors = e.errors ?? const {};
        String? first(String key) => (errors[key] is List && (errors[key] as List).isNotEmpty)
            ? (errors[key] as List).first.toString()
            : null;
        _currentError = first('current_pin');
        _nextError = first('new_pin');
        if (_currentError == null && _nextError == null) _confirmError = e.message;
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _field(TextEditingController c, String label, String? error) => TextField(
        controller: c,
        obscureText: true,
        keyboardType: TextInputType.number,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(12)],
        decoration: InputDecoration(labelText: label, errorText: error),
      );

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Change PIN'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _field(_current, 'Current PIN', _currentError),
            const SizedBox(height: 8),
            _field(_next, 'New PIN', _nextError),
            const SizedBox(height: 8),
            _field(_confirm, 'Confirm new PIN', _confirmError),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: _saving ? null : () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save')),
      ],
    );
  }
}
