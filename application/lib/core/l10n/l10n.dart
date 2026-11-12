import 'package:flutter/widgets.dart';

import 'generated/app_localizations.dart';

export 'generated/app_localizations.dart';

extension L10nContext on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}

/// Supported languages; Arabic (RTL) first and default.
const supportedLocales = [Locale('ar'), Locale('en')];
