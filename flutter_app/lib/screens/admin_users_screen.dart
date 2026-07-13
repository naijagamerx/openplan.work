import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/admin_user.dart';
import '../repositories/admin_users_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin user management. Mirrors mobile/views/users.php: a searchable list of
/// users with avatar, name, email, Verified/Banned badges, a role selector
/// (disabled for the last admin), and ban/delete actions.
///
/// Admin screens are NOT primary tabs → plain Scaffold + AppBar (back arrow).
class AdminUsersScreen extends ConsumerStatefulWidget {
  const AdminUsersScreen({super.key});

  @override
  ConsumerState<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends ConsumerState<AdminUsersScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final users = ref.watch(adminUsersProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Users'),
      ),
      body: Column(
        children: [
          Padding(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 8),
            child: _SearchField(
              onChanged: (v) => setState(() => _query = v),
            ),
          ),
          Expanded(
            child: users.when(
              loading: () => ListView(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 4, AppSpacing.page, 32),
                children: const [
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                ],
              ),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: EmptyState(
                    iconPath: HeroPaths.users,
                    message: e.toString(),
                    actionLabel: 'Retry',
                    onAction: () => ref.invalidate(adminUsersProvider),
                  ),
                ),
              ),
              data: (list) {
                if (list.isEmpty) {
                  return const Center(
                    child: EmptyState(
                        iconPath: HeroPaths.users, message: 'No users yet'),
                  );
                }
                final filtered = _filter(list, _query);
                if (filtered.isEmpty) {
                  return const Center(
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No users match'),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () => ref.refresh(adminUsersProvider.future),
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(
                        AppSpacing.page, 4, AppSpacing.page, 32),
                    itemCount: filtered.length,
                    itemBuilder: (_, i) {
                      final u = filtered[i];
                      return FadeIn(
                        delay: Duration(milliseconds: (i * 40).clamp(0, 300)),
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _UserRow(user: u),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  List<AdminUser> _filter(List<AdminUser> list, String q) {
    final query = q.toLowerCase().trim();
    if (query.isEmpty) return list;
    return list.where((u) {
      return u.name.toLowerCase().contains(query) ||
          u.email.toLowerCase().contains(query) ||
          u.role.toLowerCase().contains(query);
    }).toList();
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.onChanged});
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      onChanged: onChanged,
      textCapitalization: TextCapitalization.words,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search users…',
        hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
        prefixIcon: HeroIcon(HeroPaths.search, size: 18, color: p.textFaint),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.ink, width: 1.5)),
      ),
    );
  }
}

class _RowSkeleton extends StatelessWidget {
  const _RowSkeleton();

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: const Row(
        children: [
          Skeleton(width: 48, height: 48, radius: 999),
          SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Skeleton(height: 14, width: 140),
                SizedBox(height: 8),
                Skeleton(height: 10, width: 100),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _UserRow extends ConsumerWidget {
  const _UserRow({required this.user});
  final AdminUser user;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    // Last-admin protection: server refuses to demote/delete the protected
    // account (canDelete==false), so lock the role selector + destructive bits.
    final locked = !user.canDelete;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _Avatar(initials: user.initials, banned: user.isBanned),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name.isEmpty ? user.email : user.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      user.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12, color: p.textFaint),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (user.isVerified) const _Badge(label: 'Verified', positive: true),
              if (user.isBanned) const _Badge(label: 'Banned', positive: false),
              if (locked)
                const _Badge(label: 'Protected', positive: true),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _RoleDropdown(
                  value: user.role,
                  enabled: !locked,
                  onChanged: (role) {
                    if (role == null || role == user.role) return;
                    _changeRole(ref, role);
                  },
                ),
              ),
              const SizedBox(width: 8),
              _IconButton(
                icon: HeroPaths.lockClosed,
                label: user.isBanned ? 'Unban' : 'Ban',
                destructive: !user.isBanned,
                disabled: locked,
                onTap: locked ? null : () => _toggleBan(ref),
              ),
              const SizedBox(width: 8),
              _IconButton(
                icon: HeroPaths.trash,
                label: 'Delete',
                destructive: true,
                disabled: locked,
                onTap: locked ? null : () => _delete(ref),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _changeRole(WidgetRef ref, String role) async {
    try {
      await ref.read(adminUsersRepositoryProvider).updateRole(user.id, role);
      ref.invalidate(adminUsersProvider);
    } catch (e) {
      _toast(ref, '$e');
    }
  }

  Future<void> _toggleBan(WidgetRef ref) async {
    final ctx = ref.context;
    final action = user.isBanned ? 'Unban' : 'Ban';
    final ok = await confirmDialog(ctx,
        title: '$action user',
        message: '$action "${user.name.isEmpty ? user.email : user.name}"?',
        confirmLabel: action,
        destructive: !user.isBanned);
    if (!ok) return;
    try {
      await ref.read(adminUsersRepositoryProvider).toggleBan(user.id);
      ref.invalidate(adminUsersProvider);
    } catch (e) {
      _toast(ref, '$e');
    }
  }

  Future<void> _delete(WidgetRef ref) async {
    final ctx = ref.context;
    final ok = await confirmDialog(ctx,
        title: 'Delete user',
        message:
            'Permanently delete "${user.name.isEmpty ? user.email : user.name}"? '
            'This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(adminUsersRepositoryProvider).deleteUser(user.id);
      ref.invalidate(adminUsersProvider);
    } catch (e) {
      _toast(ref, '$e');
    }
  }

  void _toast(WidgetRef ref, String message) {
    if (ref.context.mounted) {
      ScaffoldMessenger.of(ref.context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }
}

class _RoleDropdown extends StatelessWidget {
  const _RoleDropdown({
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final String value;
  final bool enabled;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: AbsorbPointer(
        absorbing: !enabled,
        child: DropdownButtonFormField<String>(
          initialValue: value,
          decoration: InputDecoration(
            isDense: true,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(AppRadii.button),
              borderSide: BorderSide(color: p.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(AppRadii.button),
              borderSide: BorderSide(color: p.border),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(AppRadii.button),
              borderSide: BorderSide(color: p.ink, width: 1.5),
            ),
          ),
          items: const [
            DropdownMenuItem(value: 'user', child: Text('User')),
            DropdownMenuItem(value: 'admin', child: Text('Admin')),
          ],
          onChanged: onChanged,
          icon: HeroIcon(HeroPaths.chevronDown, size: 18, color: p.textMuted),
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: p.textPrimary,
          ),
          dropdownColor: p.surface,
        ),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initials, required this.banned});
  final String initials;
  final bool banned;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    // Banned accounts use a muted fill instead of the ink accent.
    final fill = banned ? p.border : p.ink;
    final fg = banned ? p.textMuted : p.onInk;
    return Container(
      width: 48,
      height: 48,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: fill,
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w800,
          color: fg,
        ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.positive});
  final String label;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    final color = positive ? const Color(0xFF16A34A) : const Color(0xFFDC2626);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.6,
          color: Colors.white,
        ),
      ),
    );
  }
}

class _IconButton extends StatelessWidget {
  const _IconButton({
    required this.icon,
    required this.label,
    required this.onTap,
    this.destructive = false,
    this.disabled = false,
  });

  final String icon;
  final String label;
  final VoidCallback? onTap;
  final bool destructive;
  final bool disabled;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = destructive ? const Color(0xFFDC2626) : p.textPrimary;
    return GestureDetector(
      onTap: disabled ? null : onTap,
      behavior: HitTestBehavior.opaque,
      child: Opacity(
        opacity: disabled ? 0.4 : 1,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: p.surface,
            border: Border.all(color: p.border),
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              HeroIcon(icon, size: 16, color: color),
              const SizedBox(width: 6),
              Text(
                label.toUpperCase(),
                style: AppType.label(context, color: color, size: 10),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
