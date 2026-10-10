import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/core/session/session.dart';
import 'package:nisconnect/features/chat/data/chat_repository.dart';
import 'package:nisconnect/features/settings/data/chat_wallpaper.dart';
import 'package:nisconnect/shared/widgets/service_number_watermark.dart';

void main() {
  group('Service Number watermark', () {
    tearDown(() => currentServiceNumber.value = null);

    testWidgets('is hidden when signed out', (tester) async {
      currentServiceNumber.value = null;
      await tester.pumpWidget(const Directionality(
        textDirection: TextDirection.ltr,
        child: ServiceNumberWatermark(child: SizedBox.expand()),
      ));
      expect(find.byType(CustomPaint), findsNothing);
    });

    testWidgets('paints the signed-in officer\'s Service Number', (tester) async {
      currentServiceNumber.value = '001234';
      await tester.pumpWidget(const Directionality(
        textDirection: TextDirection.ltr,
        child: ServiceNumberWatermark(child: SizedBox.expand()),
      ));
      final paint = tester.widget<CustomPaint>(find.byType(CustomPaint));
      expect((paint.painter! as WatermarkPainter).text, contains('001234'));

      currentServiceNumber.value = null;
      await tester.pump();
      expect(find.byType(CustomPaint), findsNothing);
    });
  });

  group('Chat wallpaper', () {
    test('specs round-trip to decorations', () {
      expect(ChatWallpapers.decoration(null), isNull);
      expect(ChatWallpapers.decoration('bogus'), isNull);

      final colour = ChatWallpapers.decoration(ChatWallpapers.colorSpec(const Color(0xFF123524)));
      expect(colour!.color, const Color(0xFF123524));

      expect(ChatWallpapers.decoration(ChatWallpapers.gradientSpec(0))!.gradient, isNotNull);
      expect(ChatWallpapers.decoration('gradient:99'), isNull);

      final photo = ChatWallpapers.decoration(ChatWallpapers.assetSpec(ChatWallpapers.photos.first));
      expect(photo!.image, isNotNull);
    });
  });

  group('Chat list', () {
    final json = {
      'id': 'c1',
      'type': 'direct',
      'title': null,
      'members': [
        {'user_id': 'me', 'display_name': 'John Doe', 'service_number': '123456'},
        {'user_id': 'u2', 'display_name': '', 'service_number': '001234', 'rank': 'Inspector'},
      ],
      'last_message': {
        'id': 'm1',
        'sender_id': 'u2',
        'type': 'text',
        'body': 'Good morning',
        'status': 'sent',
        'created_at': '2026-10-08T06:00:00Z',
      },
    };

    test('a direct chat is titled with the other officer', () {
      final chat = ChatSummary.fromJson(json);
      expect(chat.displayTitle('me'), 'Officer 001234');
      expect(chat.otherMember('me')!.rank, 'Inspector');
      expect(chat.lastMessage!.preview, 'Good morning');
    });

    test('deleted and media messages get readable previews', () {
      ChatMessage msg(String type, String status) =>
          ChatMessage(id: 'x', senderId: 'u', type: type, body: 'secret', status: status);
      expect(msg('text', 'deleted').preview, 'This message was deleted');
      expect(msg('voice', 'sent').preview, 'Voice message');
    });
  });
}
