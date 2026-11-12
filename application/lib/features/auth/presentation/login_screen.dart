import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/failure_message.dart';
import '../../../core/errors/failures.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/ui.dart';
import '../application/auth_controller.dart';
import 'auth_layout.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, this.from});

  /// Where to go after signing in (set by the router guard).
  final String? from;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .login(_email.text, _password.text);
      // The router guard redirects to home / `from`.
    } on ValidationFailure catch (failure) {
      setState(() {
        _fieldErrors = failure.errors;
        if (failure.errors.isEmpty) {
          _error = failure.message ?? context.l10n.loginFailed;
        }
      });
    } catch (error) {
      setState(() => _error = failureMessage(context.l10n, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final expired = ref.watch(
      authControllerProvider.select((s) => s.sessionExpired),
    );
    return AuthLayout(
      title: l.loginTitle,
      subtitle: l.loginSubtitle,
      child: AutofillGroup(
        child: Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (expired && _error == null) ...[
                InlineBanner(
                  message: l.errorSessionExpired,
                  tone: Tone.warning,
                ),
                const SizedBox(height: 16),
              ],
              if (_error != null) ...[
                InlineBanner(
                  key: const Key('login-error'),
                  message: _error!,
                  tone: Tone.danger,
                  icon: Icons.error_outline_rounded,
                ),
                const SizedBox(height: 16),
              ],
              TextFormField(
                key: const Key('login-email'),
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.email],
                textDirection: TextDirection.ltr,
                decoration: InputDecoration(
                  labelText: l.email,
                  prefixIcon: const Icon(Icons.mail_outline_rounded),
                  errorText: _fieldErrors['email']?.first,
                ),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (text.isEmpty) return l.validationRequired;
                  if (!text.contains('@')) return l.validationEmail;
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                key: const Key('login-password'),
                controller: _password,
                obscureText: _obscure,
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.password],
                onFieldSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: l.password,
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  errorText: _fieldErrors['password']?.first,
                  suffixIcon: IconButton(
                    tooltip: _obscure ? l.showPassword : l.hidePassword,
                    onPressed: () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (value) =>
                    (value ?? '').isEmpty ? l.validationRequired : null,
              ),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton(
                  onPressed: () => context.push(Routes.forgotPassword),
                  child: Text(l.forgotPassword),
                ),
              ),
              const SizedBox(height: 8),
              LoadingButton(
                key: const Key('login-submit'),
                label: l.signIn,
                loading: _busy,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
