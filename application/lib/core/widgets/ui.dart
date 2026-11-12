import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

/// Generic card: surface/default, border/subtle, radius 16, padding 16.
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.color,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Material(
      color: color ?? t.surfaceDefault,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppSizes.radiusXl),
        side: BorderSide(color: t.borderSubtle),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

/// Card with a heading row.
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.title,
    required this.children,
    this.trailing,
  });

  final String title;
  final List<Widget> children;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: AppType.headingH5.copyWith(
                    color: context.tokens.textPrimary,
                  ),
                ),
              ),
              ?trailing,
            ],
          ),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    );
  }
}

/// Label on the start side, value on the end side.
class InfoRow extends StatelessWidget {
  const InfoRow({
    super.key,
    required this.label,
    required this.value,
    this.valueStyle,
  });

  final String label;
  final String? value;
  final TextStyle? valueStyle;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(
              label,
              style: AppType.bodySm.copyWith(color: t.textSecondary),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 3,
            child: Text(
              (value == null || value!.isEmpty) ? '—' : value!,
              textAlign: TextAlign.end,
              style:
                  valueStyle ?? AppType.labelMd.copyWith(color: t.textPrimary),
            ),
          ),
        ],
      ),
    );
  }
}

/// Status badge (dot + label) using the design's status tones.
class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.label, this.status, this.tone});

  final String label;
  final String? status;
  final Tone? tone;

  @override
  Widget build(BuildContext context) {
    final colors = context.tokens.tone(tone ?? toneForStatus(status));
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: colors.bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: colors.solid,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: AppType.labelSm.copyWith(color: colors.fg),
            ),
          ),
        ],
      ),
    );
  }
}

/// Inline feedback banner (info / warning / danger / success).
class InlineBanner extends StatelessWidget {
  const InlineBanner({
    super.key,
    required this.message,
    this.tone = Tone.info,
    this.icon,
    this.title,
    this.action,
  });

  final String message;
  final String? title;
  final Tone tone;
  final IconData? icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final colors = context.tokens.tone(tone);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.bg,
        borderRadius: BorderRadius.circular(AppSizes.radiusLg),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon ?? Icons.info_outline_rounded, color: colors.fg, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null)
                  Text(
                    title!,
                    style: AppType.labelMd.copyWith(color: colors.fg),
                  ),
                Text(message, style: AppType.bodySm.copyWith(color: colors.fg)),
                if (action != null) ...[const SizedBox(height: 8), action!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Mobile list tile: padding 12, radius 12, leading 40.
class AppListTile extends StatelessWidget {
  const AppListTile({
    super.key,
    required this.title,
    this.subtitle,
    this.leadingIcon,
    this.trailing,
    this.onTap,
    this.leadingColor,
  });

  final String title;
  final String? subtitle;
  final IconData? leadingIcon;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color? leadingColor;

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return AppCard(
      padding: const EdgeInsets.all(12),
      onTap: onTap,
      child: Row(
        children: [
          if (leadingIcon != null) ...[
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: (leadingColor ?? t.actionPrimary).withValues(
                  alpha: 0.12,
                ),
                borderRadius: BorderRadius.circular(AppSizes.radiusLg),
              ),
              child: Icon(
                leadingIcon,
                color: leadingColor ?? t.actionPrimary,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppType.labelMd.copyWith(color: t.textPrimary),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (subtitle != null)
                  Text(
                    subtitle!,
                    style: AppType.captionMd.copyWith(color: t.textSecondary),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
              ],
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
  }
}

/// Primary button with a loading state (the design has none drawn).
class LoadingButton extends StatelessWidget {
  const LoadingButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.icon,
    this.danger = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData? icon;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final style = danger
        ? FilledButton.styleFrom(backgroundColor: context.tokens.actionDanger)
        : null;
    final child = loading
        ? const SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2.4),
          )
        : Text(label);
    if (icon != null && !loading) {
      return FilledButton.icon(
        style: style,
        onPressed: onPressed,
        icon: Icon(icon),
        label: child,
      );
    }
    return FilledButton(
      style: style,
      onPressed: loading ? null : onPressed,
      child: child,
    );
  }
}

/// Section heading inside a page.
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});

  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8, bottom: 8),
    child: Row(
      children: [
        Expanded(
          child: Text(
            text,
            style: AppType.headingH5.copyWith(
              color: context.tokens.textPrimary,
            ),
          ),
        ),
        ?trailing,
      ],
    ),
  );
}
