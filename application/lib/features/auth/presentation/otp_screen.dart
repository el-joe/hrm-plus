import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/states.dart';
import '../../../core/widgets/ui.dart';
import '../data/auth_repository.dart';
import 'auth_layout.dart';

/// Step 2–3 of password recovery: verify the e-mailed code
/// (`auth/password/verify-otp`), then set a new password (`auth/password/reset`).
class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key, required this.email});

  final String email;

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _passwordForm = GlobalKey<FormState>();
  bool _verified = false;
  bool _busy = false;
  String? _error;
  int _resendIn = 60;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startTimer(60);
  }

  void _startTimer(int seconds) {
    _timer?.cancel();
    setState(() => _resendIn = seconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return timer.cancel();
      setState(() => _resendIn = _resendIn > 0 ? _resendIn - 1 : 0);
      if (_resendIn == 0) timer.cancel();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ValidationFailure catch (failure) {
      setState(
        () => _error =
            failure.first('code') ??
            failure.first('password') ??
            failure.first('email') ??
            failure.message,
      );
    } catch (error) {
      setState(() => _error = failureMessage(context.l10n, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    final length = ref.read(remoteConfigProvider).value?.otpLength ?? 6;
    if (_code.text.trim().length != length) {
      setState(() => _error = context.l10n.otpInvalidLength(length));
      return;
    }
    await _run(() async {
      await ref
          .read(authRepositoryProvider)
          .verifyOtp(widget.email, _code.text.trim());
      setState(() => _verified = true);
    });
  }

  Future<void> _resend() => _run(() async {
    final result = await ref
        .read(authRepositoryProvider)
        .forgotPassword(widget.email);
    if (!mounted) return;
    _startTimer(result.resendAfterSeconds);
    showSnack(context, context.l10n.otpResent, tone: SnackTone.success);
  });

  Future<void> _reset() async {
    if (!_passwordForm.currentState!.validate()) return;
    await _run(() async {
      await ref
          .read(authRepositoryProvider)
          .resetPassword(
            email: widget.email,
            code: _code.text.trim(),
            password: _password.text,
          );
      if (!mounted) return;
      showSnack(
        context,
        context.l10n.passwordResetDone,
        tone: SnackTone.success,
      );
      context.go(Routes.login);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final f = ref.watch(formatterProvider);
    final config = ref.watch(remoteConfigProvider).value;
    final length = config?.otpLength ?? 6;
    final minLength = config?.passwordMinLength ?? 8;

    return AuthLayout(
      showBack: true,
      title: _verified ? l.newPasswordTitle : l.otpTitle,
      subtitle: _verified ? l.newPasswordSubtitle : l.otpSubtitle(widget.email),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_error != null) ...[
            InlineBanner(message: _error!, tone: Tone.danger),
            const SizedBox(height: 16),
          ],
          if (!_verified) ...[
            TextField(
              key: const Key('otp-code'),
              controller: _code,
              autofocus: true,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              textDirection: TextDirection.ltr,
              maxLength: length,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: const TextStyle(fontSize: 24, letterSpacing: 8),
              decoration: InputDecoration(
                labelText: l.otpCode,
                counterText: '',
              ),
              onSubmitted: (_) => _verify(),
            ),
            const SizedBox(height: 20),
            LoadingButton(label: l.verify, loading: _busy, onPressed: _verify),
            TextButton(
              onPressed: _resendIn > 0 || _busy ? null : _resend,
              child: Text(
                _resendIn > 0 ? l.otpResendIn(f.d('$_resendIn')) : l.otpResend,
              ),
            ),
          ] else
            Form(
              key: _passwordForm,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: _password,
                    obscureText: true,
                    decoration: InputDecoration(labelText: l.newPassword),
                    validator: (v) => (v ?? '').length < minLength
                        ? l.validationPasswordLength(minLength)
                        : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _confirm,
                    obscureText: true,
                    decoration: InputDecoration(labelText: l.confirmPassword),
                    validator: (v) =>
                        v != _password.text ? l.validationPasswordMatch : null,
                  ),
                  const SizedBox(height: 20),
                  LoadingButton(
                    label: l.resetPassword,
                    loading: _busy,
                    onPressed: _reset,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
