import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Service Number input: numeric keyboard, digits only. Rejects letters, "/",
/// "-", spaces and any non-digit at the input layer. Leading zeroes preserved.
class ServiceNumberField extends StatelessWidget {
  const ServiceNumberField({
    super.key,
    required this.controller,
    this.maxLength = 12,
    this.errorText,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final int maxLength;
  final String? errorText;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.number,
      inputFormatters: [
        FilteringTextInputFormatter.digitsOnly, // only 0-9
        LengthLimitingTextInputFormatter(maxLength),
      ],
      autofillHints: const [],
      decoration: InputDecoration(
        labelText: 'Service Number',
        hintText: 'e.g. 123456',
        errorText: errorText,
        prefixIcon: const Icon(Icons.badge_outlined),
      ),
      onSubmitted: onSubmitted,
    );
  }
}
