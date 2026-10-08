import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/storage/secure_storage.dart';

/// A chat background, stored as a short spec string:
/// `color:AARRGGBB`, `gradient:<index>`, `asset:<path>` or `file:<path>`.
/// Null means the plain theme background.
class ChatWallpapers {
  ChatWallpapers._();

  static const colors = <Color>[
    Color(0xFFE6F4EC), // brand light green
    Color(0xFFF3EFE2), // parchment
    Color(0xFFE3ECF5), // pale blue
    Color(0xFF123524), // deep green
    Color(0xFF1E2A33), // slate
    Color(0xFF2B2B2B), // charcoal
  ];

  static const gradients = <List<Color>>[
    [Color(0xFF0B6B3A), Color(0xFF064A28)],
    [Color(0xFF2E9E63), Color(0xFFC9A227)],
    [Color(0xFF1E3C72), Color(0xFF2A5298)],
    [Color(0xFF232526), Color(0xFF414345)],
  ];

  static const photos = <String>[
    'assets/images/hq-lobby.jpg',
    'assets/images/hq-atrium-stairs.jpg',
    'assets/images/hq-exterior-night.jpg',
  ];

  static String colorSpec(Color c) =>
      'color:${c.toARGB32().toRadixString(16).padLeft(8, '0')}';
  static String gradientSpec(int i) => 'gradient:$i';
  static String assetSpec(String path) => 'asset:$path';

  /// Background decoration for [spec], or null for the theme default.
  static BoxDecoration? decoration(String? spec) {
    if (spec == null || !spec.contains(':')) return null;
    final kind = spec.substring(0, spec.indexOf(':'));
    final value = spec.substring(spec.indexOf(':') + 1);
    // Photos are dimmed slightly so message bubbles stay readable.
    final dim = ColorFilter.mode(Colors.black.withValues(alpha: 0.3), BlendMode.darken);
    switch (kind) {
      case 'color':
        final argb = int.tryParse(value, radix: 16);
        return argb == null ? null : BoxDecoration(color: Color(argb));
      case 'gradient':
        final i = int.tryParse(value);
        if (i == null || i < 0 || i >= gradients.length) return null;
        return BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: gradients[i],
          ),
        );
      case 'asset':
        return BoxDecoration(
          image: DecorationImage(image: AssetImage(value), fit: BoxFit.cover, colorFilter: dim),
        );
      case 'file':
        if (kIsWeb) return null;
        return BoxDecoration(
          image: DecorationImage(image: FileImage(File(value)), fit: BoxFit.cover, colorFilter: dim),
        );
    }
    return null;
  }

  /// Custom photos are copied into app storage, which the web build lacks.
  static bool get supportsCustomPhoto => !kIsWeb;
}

/// The chosen chat wallpaper spec (null = default), persisted on the device.
class ChatWallpaperController extends StateNotifier<String?> {
  ChatWallpaperController(this._storage) : super(null) {
    _storage.readChatWallpaper().then((v) {
      if (mounted) state = v;
    }).catchError((Object _) {});
  }

  final SecureStorage _storage;

  Future<void> set(String? spec) async {
    final previous = state;
    state = spec;
    await _storage.saveChatWallpaper(spec);
    if (previous != null && previous != spec) await _deleteCustomFile(previous);
  }

  /// Lets the officer pick a photo from the gallery. Returns false if cancelled.
  Future<bool> pickCustomPhoto() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 2000,
      maxHeight: 2000,
      imageQuality: 85,
    );
    if (picked == null) return false;
    final dir = await getApplicationDocumentsDirectory();
    final path = '${dir.path}/chat_wallpaper_${DateTime.now().millisecondsSinceEpoch}.jpg';
    await picked.saveTo(path);
    await set('file:$path');
    return true;
  }

  Future<void> _deleteCustomFile(String spec) async {
    if (kIsWeb || !spec.startsWith('file:')) return;
    try {
      await File(spec.substring(5)).delete();
    } catch (_) {
      // Already gone.
    }
  }
}

final chatWallpaperProvider = StateNotifierProvider<ChatWallpaperController, String?>(
  (ref) => ChatWallpaperController(ref.watch(secureStorageProvider)),
);
