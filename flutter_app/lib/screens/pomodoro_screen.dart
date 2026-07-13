import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/pomodoro_session.dart';
import '../models/pomodoro_track.dart';
import '../repositories/pomodoro_repository.dart';
import '../state/pomodoro_controller.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/pomodoro_music_player.dart';
import '../widgets/progress_ring.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/stat_card.dart';

/// Full Pomodoro screen — mirrors mobile/views/pomodoro.php.
/// Reachable from the Menu hub (not a primary tab), so it uses a plain
/// Scaffold + AppBar with a back arrow.
class PomodoroScreen extends ConsumerStatefulWidget {
  const PomodoroScreen({super.key});

  @override
  ConsumerState<PomodoroScreen> createState() => _PomodoroScreenState();
}

class _PomodoroScreenState extends ConsumerState<PomodoroScreen> {
  final _focusCtrl = TextEditingController();
  final _breakCtrl = TextEditingController();
  bool _customExpanded = false;

  // Focus-music UI state (lives in the screen; playback in the player widget).
  PomodoroTrack? _selectedTrack;
  bool _musicPlaying = false;
  double _volume = 0.7;
  bool _loop = false;
  bool _playWhileRunning = false;
  bool _uploading = false;

  PomodoroController? _watchedCtrl;

  @override
  void initState() {
    super.initState();
    _focusCtrl.text = '25';
    _breakCtrl.text = '5';
    // Defer controller access until after the first build so the provider is
    // available, then listen for running-state changes to drive music playback.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final ctrl = ref.read(pomodoroControllerProvider);
      _watchedCtrl = ctrl;
      ctrl.addListener(_onControllerChanged);
    });
  }

  void _onControllerChanged() {
    final ctrl = _watchedCtrl;
    if (ctrl == null) return;
    // Auto-play / auto-stop music when the focus timer starts or stops, if the
    // user has enabled "play while running" and selected a track.
    if (!_playWhileRunning) return;
    final shouldPlay = ctrl.isRunning && _selectedTrack != null;
    if (shouldPlay != _musicPlaying) {
      setState(() => _musicPlaying = shouldPlay);
    }
  }

  @override
  void dispose() {
    _watchedCtrl?.removeListener(_onControllerChanged);
    _focusCtrl.dispose();
    _breakCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = ref.watch(pomodoroControllerProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Pomodoro'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          FadeIn(child: _TimerCard(ctrl: ctrl)),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: _PresetRow(
              ctrl: ctrl,
              focusCtrl: _focusCtrl,
              breakCtrl: _breakCtrl,
              customExpanded: _customExpanded,
              onToggleCustom: () => setState(
                  () => _customExpanded = !_customExpanded),
              onApplyCustom: _applyCustom,
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: _StatsRow(ctrl: ctrl),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 180),
            child: _FocusMusicSection(
              isRunning: ctrl.isRunning,
              selectedTrack: _selectedTrack,
              isPlaying: _musicPlaying,
              volume: _volume,
              loop: _loop,
              playWhileRunning: _playWhileRunning,
              uploading: _uploading,
              onSelectTrack: (t) => setState(() {
                _selectedTrack = t;
                _musicPlaying = _selectedTrack != null &&
                    (_playWhileRunning && ctrl.isRunning);
              }),
              onPlayingChanged: (v) => setState(() => _musicPlaying = v),
              onVolumeChanged: (v) => setState(() => _volume = v),
              onLoopChanged: (v) => setState(() => _loop = v),
              onPlayWhileRunningChanged: (v) {
                setState(() => _playWhileRunning = v);
                // Reconcile: if focus not running, stop music regardless.
                if (v && !ctrl.isRunning) {
                  setState(() => _musicPlaying = false);
                }
              },
              onUpload: _uploadTrack,
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 240),
            child: _RecentSessionsSection(),
          ),
        ],
      ),
    );
  }

  void _applyCustom() {
    final f = int.tryParse(_focusCtrl.text.trim());
    final b = int.tryParse(_breakCtrl.text.trim());
    if (f == null || f < 1 || b == null || b < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter valid focus and break minutes')),
      );
      return;
    }
    ref.read(pomodoroControllerProvider).setPreset(focus: f, breakLength: b);
    setState(() => _customExpanded = false);
  }

  Future<void> _uploadTrack() async {
    // file_selector: pick an audio file (mp3/wav/m4a). The type group keeps the
    // picker filtered to audio on platforms that support it.
    const audioGroup = XTypeGroup(
      label: 'Audio',
      extensions: ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'],
    );
    final xFile = await openFile(acceptedTypeGroups: const [audioGroup]);
    if (xFile == null) return;
    final path = xFile.path;
    final name = xFile.name;

    setState(() => _uploading = true);
    try {
      await ref.read(pomodoroRepositoryProvider).uploadMusic(
            name: _stripExt(name),
            filePath: path,
          );
      ref.invalidate(pomodoroMusicProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Track uploaded'),
              behavior: SnackBarBehavior.floating),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  String _stripExt(String name) {
    final dot = name.lastIndexOf('.');
    return dot > 0 ? name.substring(0, dot) : name;
  }
}

// ---------------------------------------------------------------------------
// Timer card: big progress ring + MM:SS + controls.
// ---------------------------------------------------------------------------

class _TimerCard extends StatelessWidget {
  const _TimerCard({required this.ctrl});
  final PomodoroController ctrl;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      child: Column(
        children: [
          // State label
          Text(
            ctrl.mode.label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11),
          ),
          const SizedBox(height: 20),
          // Big ring with mono MM:SS centered
          ProgressRing(
            progress: ctrl.progress,
            trackColor: p.border,
            color: ctrl.mode == PomodoroMode.focusBreak ? p.textMuted : p.ink,
            size: 240,
            stroke: 10,
            center: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  ctrl.remainingLabel,
                  style: AppType.mono(
                    size: 48,
                    weight: FontWeight.w300,
                    color: p.textPrimary,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  (ctrl.mode == PomodoroMode.focusBreak ? 'BREAK' : 'FOCUS'),
                  style: AppType.label(context, color: p.textFaint, size: 10),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          // Controls
          Row(
            children: [
              Expanded(
                child: _ControlButton(
                  label: ctrl.isRunning
                      ? 'Pause'
                      : (ctrl.mode == PomodoroMode.paused ? 'Resume' : 'Start'),
                  primary: true,
                  icon: ctrl.isRunning ? HeroPaths.pause : HeroPaths.play,
                  onTap: () {
                    if (ctrl.isRunning) {
                      ctrl.pause();
                    } else if (ctrl.mode == PomodoroMode.paused) {
                      ctrl.resume();
                    } else {
                      ctrl.start();
                    }
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _ControlButton(
                  label: 'Reset',
                  primary: false,
                  icon: HeroPaths.arrowPath,
                  onTap: ctrl.reset,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ControlButton extends StatelessWidget {
  const _ControlButton({
    required this.label,
    required this.primary,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final bool primary;
  final String icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = primary ? p.onInk : p.textPrimary;
    final fill = primary ? p.ink : Colors.transparent;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        height: AppSpacing.touchTarget,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: fill,
          border: primary ? null : Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            HeroIcon(icon, size: 16, color: color),
            const SizedBox(width: 8),
            Text(
              label.toUpperCase(),
              style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 12,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Preset chips + custom inputs.
// ---------------------------------------------------------------------------

class _PresetRow extends StatelessWidget {
  const _PresetRow({
    required this.ctrl,
    required this.focusCtrl,
    required this.breakCtrl,
    required this.customExpanded,
    required this.onToggleCustom,
    required this.onApplyCustom,
  });

  final PomodoroController ctrl;
  final TextEditingController focusCtrl;
  final TextEditingController breakCtrl;
  final bool customExpanded;
  final VoidCallback onToggleCustom;
  final VoidCallback onApplyCustom;

  @override
  Widget build(BuildContext context) {
    final presets = <(int, int, String)>[
      (25, 5, '25 min'),
      (15, 3, '15 min'),
      (5, 1, '5 min'),
    ];
    final isCustom = !presets
        .any((e) => e.$1 == ctrl.focusMinutes && e.$2 == ctrl.breakMinutes);

    return SectionCard(
      heading: 'Presets',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (focus, brk, label) in presets)
                _PresetChip(
                  label: label,
                  selected: ctrl.focusMinutes == focus &&
                      ctrl.breakMinutes == brk &&
                      !ctrl.isRunning,
                  onTap: ctrl.isRunning
                      ? null
                      : () => ctrl.setPreset(focus: focus, breakLength: brk),
                ),
              _PresetChip(
                label: 'Custom',
                selected: isCustom,
                onTap: onToggleCustom,
              ),
            ],
          ),
          if (customExpanded) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: LabeledField(
                    label: 'Focus (min)',
                    controller: focusCtrl,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: LabeledField(
                    label: 'Break (min)',
                    controller: breakCtrl,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: onApplyCustom,
                child: const Text('Apply'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _PresetChip extends StatelessWidget {
  const _PresetChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? p.ink : Colors.transparent,
          border: Border.all(color: selected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 12,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.5,
            color: selected ? p.onInk : p.textPrimary,
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Stats row: Completed Today / Current Session / Focus Time.
// ---------------------------------------------------------------------------

class _StatsRow extends StatelessWidget {
  const _StatsRow({required this.ctrl});
  final PomodoroController ctrl;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: StatCard(
            label: 'Completed Today',
            iconPath: HeroPaths.checkCircle,
            valueInt: ctrl.completedToday,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: StatCard(
            label: 'Current Session',
            iconPath: HeroPaths.clock,
            valueInt: ctrl.currentSession,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: StatCard(
            label: 'Focus Time',
            iconPath: HeroPaths.fire,
            valueText: '${ctrl.totalFocusMinutes}m',
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Focus Music section.
// ---------------------------------------------------------------------------

class _FocusMusicSection extends ConsumerWidget {
  const _FocusMusicSection({
    required this.isRunning,
    required this.selectedTrack,
    required this.isPlaying,
    required this.volume,
    required this.loop,
    required this.playWhileRunning,
    required this.uploading,
    required this.onSelectTrack,
    required this.onPlayingChanged,
    required this.onVolumeChanged,
    required this.onLoopChanged,
    required this.onPlayWhileRunningChanged,
    required this.onUpload,
  });

  final bool isRunning;
  final PomodoroTrack? selectedTrack;
  final bool isPlaying;
  final double volume;
  final bool loop;
  final bool playWhileRunning;
  final bool uploading;
  final ValueChanged<PomodoroTrack> onSelectTrack;
  final ValueChanged<bool> onPlayingChanged;
  final ValueChanged<double> onVolumeChanged;
  final ValueChanged<bool> onLoopChanged;
  final ValueChanged<bool> onPlayWhileRunningChanged;
  final VoidCallback onUpload;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final tracks = ref.watch(pomodoroMusicProvider);
    return SectionCard(
      heading: 'Focus Music',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PomodoroMusicPlayer(
            track: selectedTrack,
            isPlaying: isPlaying,
            volume: volume,
            loop: loop,
            onPlayingChanged: onPlayingChanged,
            onVolumeChanged: onVolumeChanged,
            onLoopChanged: onLoopChanged,
          ),
          const SizedBox(height: 16),
          // Toggles
          _ToggleRow(
            label: 'Play while running',
            value: playWhileRunning,
            onChanged: onPlayWhileRunningChanged,
          ),
          _ToggleRow(
            label: 'Loop track',
            value: loop,
            onChanged: onLoopChanged,
          ),
          const SizedBox(height: 16),
          // Upload button
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: uploading ? null : onUpload,
              icon: HeroIcon(
                uploading ? HeroPaths.arrowPath : HeroPaths.cloud,
                size: 18,
                color: p.textPrimary,
              ),
              label: Text(uploading ? 'Uploading…' : 'Upload Track'),
            ),
          ),
          const SizedBox(height: 16),
          Text('LIBRARY', style: AppType.label(context, color: p.textMuted)),
          const SizedBox(height: 8),
          tracks.when(
            loading: () => const Column(
              children: [
                Skeleton(height: 44, radius: AppRadii.button),
                SizedBox(height: 8),
                Skeleton(height: 44, radius: AppRadii.button),
              ],
            ),
            error: (e, _) => EmptyState(
              iconPath: HeroPaths.cloud,
              message: '$e',
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(pomodoroMusicProvider),
            ),
            data: (list) {
              if (list.isEmpty) {
                return const EmptyState(
                  iconPath: HeroPaths.cloud,
                  message: 'No tracks yet. Upload one to get started.',
                );
              }
              return Column(
                children: [
                  for (final t in list)
                    _TrackTile(
                      track: t,
                      selected: selectedTrack?.id == t.id,
                      onTap: () => onSelectTrack(t),
                      onDelete: t.canDelete
                          ? () => _deleteTrack(ref, t)
                          : null,
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Future<void> _deleteTrack(WidgetRef ref, PomodoroTrack t) async {
    try {
      await ref.read(pomodoroRepositoryProvider).deleteMusic(t.id);
      ref.invalidate(pomodoroMusicProvider);
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

class _ToggleRow extends StatelessWidget {
  const _ToggleRow({
    required this.label,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: p.onInk,
            activeTrackColor: p.ink,
            inactiveThumbColor: p.textFaint,
            inactiveTrackColor: p.border,
          ),
        ],
      ),
    );
  }
}

class _TrackTile extends StatelessWidget {
  const _TrackTile({
    required this.track,
    required this.selected,
    required this.onTap,
    this.onDelete,
  });

  final PomodoroTrack track;
  final bool selected;
  final VoidCallback onTap;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? p.ink.withValues(alpha: 0.06) : p.surface,
          border: Border.all(color: selected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          children: [
            HeroIcon(
              selected ? HeroPaths.pause : HeroPaths.play,
              size: 18,
              color: selected ? p.ink : p.textMuted,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    track.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: p.textPrimary,
                    ),
                  ),
                  Text(
                    _fmtSize(track.size),
                    style: TextStyle(fontSize: 11, color: p.textFaint),
                  ),
                ],
              ),
            ),
            if (track.isShared)
              _Badge('SHARED', p.border, p.textMuted),
            if (onDelete != null) ...[
              const SizedBox(width: 8),
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () async {
                  final ok = await confirmDialog(context,
                      title: 'Delete track',
                      message: 'Delete "${track.name}"?',
                      confirmLabel: 'Delete');
                  if (ok) onDelete!();
                },
                child: const HeroIcon(HeroPaths.trash,
                    size: 16, color: Color(0xFFDC2626)),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _fmtSize(int bytes) {
    if (bytes <= 0) return '—';
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).round()} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }
}

class _Badge extends StatelessWidget {
  const _Badge(this.label, this.bg, this.fg);
  final String label;
  final Color bg;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
          color: fg,
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Recent Sessions (last 8).
// ---------------------------------------------------------------------------

class _RecentSessionsSection extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sessions = ref.watch(pomodoroSessionsProvider);
    return SectionCard(
      heading: 'Recent Sessions',
      child: sessions.when(
        loading: () => const Column(
          children: [
            Skeleton(height: 40, radius: AppRadii.button),
            SizedBox(height: 8),
            Skeleton(height: 40, radius: AppRadii.button),
            SizedBox(height: 8),
            Skeleton(height: 40, radius: AppRadii.button),
          ],
        ),
        error: (e, _) => EmptyState(
          iconPath: HeroPaths.clock,
          message: '$e',
          actionLabel: 'Retry',
          onAction: () => ref.invalidate(pomodoroSessionsProvider),
        ),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyState(
              iconPath: HeroPaths.clock,
              message: 'No sessions yet. Complete a focus to see history.',
            );
          }
          final recent = list.take(8).toList();
          return Column(
            children: [
              for (final s in recent) _SessionTile(session: s),
            ],
          );
        },
      ),
    );
  }
}

class _SessionTile extends StatelessWidget {
  const _SessionTile({required this.session});
  final PomodoroSession session;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final date = session.date;
    final focusMinutes = session.focusMinutes;
    final fmt = DateFormat('MMM d, h:mm a');
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Row(
        children: [
          HeroIcon(HeroPaths.checkCircle, size: 18, color: p.ink),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${focusMinutes}m focus',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: p.textPrimary,
                  ),
                ),
                Text(
                  fmt.format(date),
                  style: TextStyle(fontSize: 11, color: p.textFaint),
                ),
              ],
            ),
          ),
          Text(
            session.mode.toString(),
            style: AppType.label(context, color: p.textMuted),
          ),
        ],
      ),
    );
  }
}
