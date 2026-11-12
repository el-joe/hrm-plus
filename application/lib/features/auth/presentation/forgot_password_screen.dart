import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/ui.dart';
import '../data/auth_repository.dart';
import 'auth_layout.dart';

/// Step 1 of password recovery: e-mail an OTP (`POST auth/password/forgot`).
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authRepositoryProvider).forgotPassword(_email.text);
      if (!mounted) return;
      await context.push(
        Uri(
          path: Routes.otp,
          queryParameters: {'email': _email.text.trim()},
        ).toString(),
      );
    } on ValidationFailure catch (failure) {
      setState(() => _error = failure.first('email') ?? failure.message);
    } catch (error) {
      setState(() => _error = failureMessage(context.l10n, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return AuthLayout(
      showBack: true,
      title: l.forgotPasswordTitle,
      subtitle: l.forgotPasswordSubtitle,
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_error != null) ...[
              InlineBanner(message: _error!, tone: Tone.danger),
              const SizedBox(height: 16),
            ],
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(
                labelText: l.email,
                prefixIcon: const Icon(Icons.mail_outline_rounded),
              ),
              validator: (value) {
                final text = value?.trim() ?? '';
                if (text.isEmpty) return l.validationRequired;
                if (!text.contains('@')) return l.validationEmail;
                return null;
              },
              onFieldSubmitted: (_) => _submit(),
            ),
            const SizedBox(height: 20),
            LoadingButton(
              label: l.sendCode,
              loading: _busy,
              onPressed: _submit,
            ),
            TextButton(
              onPressed: () => context.go(Routes.login),
              child: Text(l.backToLogin),
            ),
          ],
        ),
      ),
    );
  }
}
