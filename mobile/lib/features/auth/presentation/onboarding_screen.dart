import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/primary_button.dart';
import '../../../shared/widgets/service_number_field.dart';
import '../domain/personnel_record.dart';
import 'onboarding_controller.dart';

/// Single screen that renders the correct step of the Service Number
/// onboarding flow based on controller state.
class OnboardingScreen extends ConsumerWidget {
  const OnboardingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(onboardingControllerProvider);

    ref.listen(onboardingControllerProvider, (prev, next) {
      if (next.step == OnboardingStep.done) {
        context.go('/home');
      }
    });

    return Scaffold(
      appBar: AppBar(title: const Text('Create Account')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: switch (state.step) {
            OnboardingStep.serviceNumber => const _ServiceNumberStep(),
            OnboardingStep.personnelRecord => _PersonnelRecordStep(record: state.record!),
            OnboardingStep.phone => const _PhoneStep(),
            OnboardingStep.otp => _OtpStep(phone: state.phone ?? ''),
            OnboardingStep.credentials => const _PinStep(),
            OnboardingStep.done => const Center(child: CircularProgressIndicator()),
          },
        ),
      ),
    );
  }
}

class _ServiceNumberStep extends ConsumerStatefulWidget {
  const _ServiceNumberStep();
  @override
  ConsumerState<_ServiceNumberStep> createState() => _ServiceNumberStepState();
}

class _ServiceNumberStepState extends ConsumerState<_ServiceNumberStep> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(onboardingControllerProvider);
    final notifier = ref.read(onboardingControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Enter your Service Number', style: AppTypography.h1),
        const SizedBox(height: 8),
        Text(
          'We will verify it against the NIS personnel records before creating your account.',
          style: AppTypography.body.copyWith(color: AppColors.neutralGrey),
        ),
        const SizedBox(height: 24),
        ServiceNumberField(
          controller: _controller,
          errorText: state.error,
          onSubmitted: (v) => notifier.verifyServiceNumber(v),
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Verify Service Number',
          loading: state.loading,
          onPressed: () => notifier.verifyServiceNumber(_controller.text),
        ),
      ],
    );
  }
}

class _PersonnelRecordStep extends ConsumerWidget {
  const _PersonnelRecordStep({required this.record});
  final PersonnelRecord record;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifier = ref.read(onboardingControllerProvider.notifier);
    Widget row(String label, String? value) {
      if (value == null || value.isEmpty) return const SizedBox.shrink();
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)),
            Text(value, style: AppTypography.title),
          ],
        ),
      );
    }

    return ListView(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: AppColors.lightGreen,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text('PERSONNEL RECORD FOUND',
              style: AppTypography.label.copyWith(color: AppColors.darkGreen)),
        ),
        const SizedBox(height: 8),
        row('Service Number', record.serviceNumber),
        row('Name', record.fullName),
        row('Rank', record.rank),
        row('Directorate', record.directorate),
        row('Department', record.department),
        row('Command', record.command),
        const SizedBox(height: 24),
        PrimaryButton(label: 'This is me', onPressed: notifier.confirmIsMe),
      ],
    );
  }
}

class _PhoneStep extends ConsumerStatefulWidget {
  const _PhoneStep();
  @override
  ConsumerState<_PhoneStep> createState() => _PhoneStepState();
}

class _PhoneStepState extends ConsumerState<_PhoneStep> {
  final _controller = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(onboardingControllerProvider);
    final notifier = ref.read(onboardingControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Verify your phone', style: AppTypography.h1),
        const SizedBox(height: 8),
        Text('We will send a one-time code to confirm this number.',
            style: AppTypography.body.copyWith(color: AppColors.neutralGrey)),
        const SizedBox(height: 24),
        TextField(
          controller: _controller,
          keyboardType: TextInputType.phone,
          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+]'))],
          decoration: InputDecoration(
            labelText: 'Phone number',
            hintText: '+234...',
            errorText: state.error,
            prefixIcon: const Icon(Icons.phone_outlined),
          ),
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Send code',
          loading: state.loading,
          onPressed: () => notifier.submitPhone(_controller.text),
        ),
      ],
    );
  }
}

class _OtpStep extends ConsumerStatefulWidget {
  const _OtpStep({required this.phone});
  final String phone;
  @override
  ConsumerState<_OtpStep> createState() => _OtpStepState();
}

class _OtpStepState extends ConsumerState<_OtpStep> {
  final _controller = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(onboardingControllerProvider);
    final notifier = ref.read(onboardingControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Enter the code', style: AppTypography.h1),
        const SizedBox(height: 8),
        Text('Sent to ${widget.phone}',
            style: AppTypography.body.copyWith(color: AppColors.neutralGrey)),
        const SizedBox(height: 24),
        TextField(
          controller: _controller,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          textAlign: TextAlign.center,
          style: AppTypography.h1,
          decoration: InputDecoration(errorText: state.error),
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Verify',
          loading: state.loading,
          onPressed: () => notifier.verifyOtp(_controller.text),
        ),
      ],
    );
  }
}

class _PinStep extends ConsumerStatefulWidget {
  const _PinStep();
  @override
  ConsumerState<_PinStep> createState() => _PinStepState();
}

class _PinStepState extends ConsumerState<_PinStep> {
  final _pin = TextEditingController();

  Map<String, dynamic> _device() => {
        'name': Platform.isIOS ? 'iPhone' : 'Android device',
        'platform': Platform.isIOS ? 'ios' : 'android',
      };

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(onboardingControllerProvider);
    final notifier = ref.read(onboardingControllerProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Create a PIN', style: AppTypography.h1),
        const SizedBox(height: 8),
        Text('Used to secure your NISconnect account on this device.',
            style: AppTypography.body.copyWith(color: AppColors.neutralGrey)),
        const SizedBox(height: 24),
        TextField(
          controller: _pin,
          keyboardType: TextInputType.number,
          obscureText: true,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
            LengthLimitingTextInputFormatter(12),
          ],
          decoration: InputDecoration(labelText: 'PIN (4–12 digits)', errorText: state.error),
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Create account',
          loading: state.loading,
          onPressed: () => notifier.setCredentials(_pin.text, _device()),
        ),
      ],
    );
  }
}
