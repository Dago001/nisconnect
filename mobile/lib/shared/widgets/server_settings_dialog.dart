import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/storage/secure_storage.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_typography.dart';

/// Lets the user point the app at a different NISconnect server (e.g. the
/// hosted test server) without installing a new build.
Future<void> showServerSettingsDialog(BuildContext context) {
  return showDialog<void>(context: context, builder: (_) => const _ServerSettingsDialog());
}

/// Small text button that opens [showServerSettingsDialog].
class ServerAddressButton extends StatelessWidget {
  const ServerAddressButton({super.key});

  @override
  Widget build(BuildContext context) {
    return TextButton.icon(
      onPressed: () => showServerSettingsDialog(context),
      icon: const Icon(Icons.dns_outlined, size: 18),
      label: const Text('Server address'),
    );
  }
}

/// Accepts "nisconnect.onrender.com", "https://host/", "https://host/api/v1"
/// and returns the server root ("https://host"), or null if unusable.
String? normaliseServerUrl(String input) {
  var value = input.trim();
  if (value.isEmpty) return null;
  if (!value.contains('://')) value = 'https://$value';
  value = value.replaceFirst(RegExp(r'/+$'), '').replaceFirst(RegExp(r'/api/v1$'), '');
  final uri = Uri.tryParse(value);
  if (uri == null || !(uri.scheme == 'http' || uri.scheme == 'https') || uri.host.isEmpty) {
    return null;
  }
  return value;
}

class _ServerSettingsDialog extends StatefulWidget {
  const _ServerSettingsDialog();

  @override
  State<_ServerSettingsDialog> createState() => _ServerSettingsDialogState();
}

class _ServerSettingsDialogState extends State<_ServerSettingsDialog> {
  late final _controller = TextEditingController(text: AppConfig.serverRoot);
  bool _busy = false;
  String? _error;
  String? _status;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<bool> _test(String root) async {
    setState(() {
      _busy = true;
      _error = null;
      _status = 'Connecting… a sleeping test server can take up to a minute to wake.';
    });
    try {
      final dio = Dio(BaseOptions(
        connectTimeout: const Duration(seconds: 70),
        receiveTimeout: const Duration(seconds: 70),
      ));
      await dio.get<dynamic>('$root/up');
      setState(() => _status = 'Connected.');
      return true;
    } on DioException catch (e) {
      setState(() {
        _status = null;
        _error = e.response == null
            ? "Can't reach $root. Check the address and your internet connection."
            : 'The server answered with an error (${e.response!.statusCode}).';
      });
      return false;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save() async {
    final root = normaliseServerUrl(_controller.text);
    if (root == null) {
      setState(() => _error = 'Enter an address like https://nisconnect.onrender.com');
      return;
    }
    if (!await _test(root)) return;
    await secureStorage.saveServerUrl(root);
    AppConfig.serverOverride = root;
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _reset() async {
    await secureStorage.saveServerUrl(null);
    AppConfig.serverOverride = null;
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Server address'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('The NISconnect server this app connects to.', style: AppTypography.caption),
          const SizedBox(height: 12),
          TextField(
            controller: _controller,
            enabled: !_busy,
            keyboardType: TextInputType.url,
            autocorrect: false,
            decoration: InputDecoration(
              labelText: 'Address',
              hintText: 'https://nisconnect.onrender.com',
              errorText: _error,
              errorMaxLines: 3,
            ),
            onSubmitted: (_) => _save(),
          ),
          if (_status != null) ...[
            const SizedBox(height: 8),
            Text(_status!, style: AppTypography.caption.copyWith(color: AppColors.primaryGreen)),
          ],
        ],
      ),
      actions: [
        if (AppConfig.serverOverride != null)
          TextButton(onPressed: _busy ? null : _reset, child: const Text('Use default')),
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _busy ? null : _save,
          child: _busy
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Save'),
        ),
      ],
    );
  }
}
