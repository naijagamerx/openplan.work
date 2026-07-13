import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:just_audio/just_audio.dart';

import '../auth/auth_controller.dart';
import '../config/app_config.dart';
import '../models/pomodoro_track.dart';
import '../theme/app_tokens.dart';
import 'hero_icon.dart';

/// Manages a single [just_audio.AudioPlayer] for the Pomodoro Focus Music
/// library. Plays a selected track from the token-protected streaming endpoint,
/// with volume + loop controls. The parent screen owns the track list; this
/// widget owns playback state.
class PomodoroMusicPlayer extends ConsumerStatefulWidget {
  const PomodoroMusicPlayer({
    super.key,
    required this.track,
    required this.isPlaying,
    required this.volume,
    required this.loop,
    required this.onPlayingChanged,
    required this.onVolumeChanged,
    required this.onLoopChanged,
  });

  final PomodoroTrack? track;
  final bool isPlaying;
  final double volume; // 0..1
  final bool loop;
  final ValueChanged<bool> onPlayingChanged;
  final ValueChanged<double> onVolumeChanged;
  final ValueChanged<bool> onLoopChanged;

  @override
  ConsumerState<PomodoroMusicPlayer> createState() => _PomodoroMusicPlayerState();
}

class _PomodoroMusicPlayerState extends ConsumerState<PomodoroMusicPlayer> {
  final AudioPlayer _player = AudioPlayer();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _player.setVolume(widget.volume);
    _player.setLoopMode(widget.loop ? LoopMode.one : LoopMode.off);
    _player.playerStateStream.listen((s) {
      if (s.processingState == ProcessingState.completed &&
          _player.loopMode != LoopMode.one) {
        widget.onPlayingChanged(false);
      }
    });
  }

  @override
  void didUpdateWidget(covariant PomodoroMusicPlayer old) {
    super.didUpdateWidget(old);
    if (widget.volume != old.volume) {
      _player.setVolume(widget.volume);
    }
    if (widget.loop != old.loop) {
      _player.setLoopMode(widget.loop ? LoopMode.one : LoopMode.off);
    }
    // Track changed or play/pause toggled externally → reconcile.
    if (widget.track?.id != old.track?.id) {
      _loadAndSync();
    } else if (widget.isPlaying != old.isPlaying) {
      _syncPlayback();
    }
  }

  Future<void> _loadAndSync() async {
    final track = widget.track;
    if (track == null) return;
    setState(() => _loading = true);
    try {
      final token = await ref.read(tokenStoreProvider).readToken();
      await _player.setAudioSource(
        AudioSource.uri(
          Uri.parse(track.url(AppConfig.baseUrl)),
          headers: {
            if (token != null && token.isNotEmpty) ...{
              'Authorization': 'Bearer $token',
              'X-Device-Token': token,
            },
          },
        ),
      );
      if (widget.isPlaying) {
        await _player.play();
      }
    } catch (e) {
      debugPrint('[PomodoroMusic] load failed: $e');
      widget.onPlayingChanged(false);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _syncPlayback() async {
    try {
      if (widget.isPlaying) {
        if (_player.processingState == ProcessingState.idle &&
            widget.track != null) {
          await _loadAndSync();
          return;
        }
        await _player.play();
      } else {
        await _player.pause();
      }
    } catch (e) {
      debugPrint('[PomodoroMusic] sync failed: $e');
    }
  }

  Future<void> _togglePlay() async {
    if (widget.track == null) return;
    if (widget.isPlaying) {
      await _player.pause();
      widget.onPlayingChanged(false);
    } else {
      if (_player.processingState == ProcessingState.idle ||
          _player.processingState == ProcessingState.completed) {
        await _loadAndSync();
        if (mounted) widget.onPlayingChanged(true);
      } else {
        await _player.play();
        widget.onPlayingChanged(true);
      }
    }
  }

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final hasTrack = widget.track != null;
    return Row(
      children: [
        GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: hasTrack && !_loading ? _togglePlay : null,
          child: Opacity(
            opacity: hasTrack ? 1 : 0.4,
            child: Container(
              width: AppSpacing.touchTarget,
              height: AppSpacing.touchTarget,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: p.ink,
                shape: BoxShape.circle,
              ),
              child: HeroIcon(
                widget.isPlaying ? HeroPaths.pause : HeroPaths.play,
                size: 20,
                color: p.onInk,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.track?.name ?? 'No track selected',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: p.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              // Volume slider
              SliderTheme(
                data: SliderTheme.of(context).copyWith(
                  trackHeight: 3,
                  thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7),
                  overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
                  activeTrackColor: p.ink,
                  inactiveTrackColor: p.border,
                  thumbColor: p.ink,
                ),
                child: Slider(
                  value: widget.volume,
                  min: 0,
                  max: 1,
                  onChanged: hasTrack ? widget.onVolumeChanged : null,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
