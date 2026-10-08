import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_typography.dart';
import '../data/chat_wallpaper.dart';

/// Lets the officer choose the background shown behind their conversations.
class WallpaperScreen extends ConsumerWidget {
  const WallpaperScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final current = ref.watch(chatWallpaperProvider);
    final controller = ref.read(chatWallpaperProvider.notifier);

    Widget tile({required String? spec, required String label, Widget? child}) {
      final selected = current == spec || (spec == null && current == null);
      final decoration = ChatWallpapers.decoration(spec) ??
          BoxDecoration(color: Theme.of(context).scaffoldBackgroundColor);
      return Semantics(
        button: true,
        selected: selected,
        label: label,
        child: GestureDetector(
          onTap: () => controller.set(spec),
          child: Container(
            decoration: decoration.copyWith(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: selected ? Theme.of(context).colorScheme.primary : Theme.of(context).dividerColor,
                width: selected ? 3 : 1,
              ),
            ),
            child: Stack(
              children: [
                if (child != null) Center(child: child),
                if (selected)
                  Positioned(
                    right: 6,
                    top: 6,
                    child: CircleAvatar(
                      radius: 11,
                      backgroundColor: Theme.of(context).colorScheme.primary,
                      child: const Icon(Icons.check, size: 14, color: Colors.white),
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
    }

    Widget section(String title, List<Widget> tiles) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
              child: Text(title, style: AppTypography.title),
            ),
            GridView.count(
              crossAxisCount: 3,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 16),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 0.62,
              children: tiles,
            ),
          ],
        );

    final custom = current != null && current.startsWith('file:');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chat wallpaper'),
        actions: [
          if (current != null)
            TextButton(onPressed: () => controller.set(null), child: const Text('Reset')),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          section('Default & photo', [
            tile(
              spec: null,
              label: 'Default',
              child: const Text('Default', style: AppTypography.caption),
            ),
            if (ChatWallpapers.supportsCustomPhoto)
              custom
                  ? tile(spec: current, label: 'Your photo')
                  : _PickPhotoTile(onTap: () async {
                      try {
                        await controller.pickCustomPhoto();
                      } catch (e) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text("Couldn't use that photo: $e")),
                          );
                        }
                      }
                    }),
            if (custom && ChatWallpapers.supportsCustomPhoto)
              _PickPhotoTile(onTap: () => controller.pickCustomPhoto()),
          ]),
          section('NIS Headquarters', [
            for (final p in ChatWallpapers.photos)
              tile(spec: ChatWallpapers.assetSpec(p), label: 'Headquarters photo'),
          ]),
          section('Gradients', [
            for (var i = 0; i < ChatWallpapers.gradients.length; i++)
              tile(spec: ChatWallpapers.gradientSpec(i), label: 'Gradient ${i + 1}'),
          ]),
          section('Solid colours', [
            for (final c in ChatWallpapers.colors)
              tile(spec: ChatWallpapers.colorSpec(c), label: 'Colour'),
          ]),
        ],
      ),
    );
  }
}

class _PickPhotoTile extends StatelessWidget {
  const _PickPhotoTile({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Theme.of(context).dividerColor),
        ),
        child: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.add_photo_alternate_outlined, size: 32),
            SizedBox(height: 6),
            Text('Choose photo', style: AppTypography.caption, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
