import 'package:flutter/material.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../config/constants/app_constants.dart';
import '../../core/services/location_service.dart';
import '../places/nearby_places_screen.dart';
import '../places/place_details_screen.dart';
import '../../core/models/place.dart';

class AssistantScreen extends StatefulWidget {
  const AssistantScreen({super.key});

  @override
  State<AssistantScreen> createState() => _AssistantScreenState();
}

class _AssistantScreenState extends State<AssistantScreen> {
  final _messageController = TextEditingController();
  final _scrollController = ScrollController();
  final List<ChatMessage> _messages = [
    ChatMessage(
      text: 'Namaste! 👋 I\'m your AI Travel Assistant for Nepal. Ask me about places to visit, road conditions, safety tips, or anything about traveling in Nepal!',
      isUser: false,
    ),
  ];
  bool _isTyping = false;

  final List<String> _suggestions = [
    'Best places near Pokhara?',
    'Road conditions to Mustang?',
    'Budget hotels in Thamel?',
    'Hidden gems in Kathmandu?',
    'Weather in Pokhara today?',
    'Emergency numbers in Nepal?',
  ];

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _sendMessage(String text) {
    if (text.trim().isEmpty) return;

    setState(() {
      _messages.add(ChatMessage(text: text, isUser: true));
      _isTyping = true;
    });

    _messageController.clear();
    _scrollToBottom();

    _callAssistantApi(text);
  }

  Future<void> _callAssistantApi(String text) async {
    try {
      // FL-14: use the user's real location when available, fall back to defaults
      var lat = AppConstants.defaultLatitude;
      var lng = AppConstants.defaultLongitude;
      try {
        final position = await LocationService().getCurrentLocation();
        if (position != null) {
          lat = position.latitude;
          lng = position.longitude;
        }
      } catch (_) {}

      final response = await ApiClient.instance.chatWithAssistant(
        message: text,
        lat: lat,
        lng: lng,
      );

      if (!mounted) return;

      // FL-24: safe parse — 'data' may be missing or non-map
      final data = response.data is Map ? response.data['data'] : null;
      final reply = (data is Map ? data['reply'] : null)?.toString() ??
          'Sorry, ma bujhna sakina.';
      final actions = data is Map && data['actions'] is List
          ? List<Map<String, dynamic>>.from(
              (data['actions'] as List).whereType<Map>())
          : <Map<String, dynamic>>[];

      setState(() {
        _isTyping = false;
        _messages.add(ChatMessage(text: reply, isUser: false, actions: actions));
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isTyping = false;
        _messages.add(ChatMessage(
          text: 'Sorry, I couldn\'t reach the server. Please check your connection and try again.',
          isUser: false,
        ));
      });
    }
    _scrollToBottom();
  }

  void _scrollToBottom() {
    Future.delayed(const Duration(milliseconds: 100), () {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('AI Travel Assistant'),
        actions: [
          IconButton(icon: const Icon(Icons.delete_outline), onPressed: () {
            setState(() {
              _messages.clear();
              _messages.add(ChatMessage(
                text: 'Namaste! 👋 I\'m your AI Travel Assistant for Nepal. Ask me about places to visit, road conditions, safety tips, or anything about traveling in Nepal!',
                isUser: false,
              ));
            });
          }),
        ],
      ),
      body: Column(
        children: [
          // Chat Messages
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.all(16),
              itemCount: _messages.length + (_isTyping ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == _messages.length) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        SizedBox(width: 48),
                        SizedBox(
                          width: 24, height: 24,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        SizedBox(width: 8),
                        Text('Thinking...', style: TextStyle(color: AppTheme.textSecondary)),
                      ],
                    ),
                  );
                }
                final message = _messages[index];
                return _MessageBubble(message: message);
              },
            ),
          ),

          // Suggestions (show only when messages are few)
          if (_messages.length <= 2)
            Container(
              height: 44,
              margin: const EdgeInsets.only(bottom: 4),
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: _suggestions.length,
                itemBuilder: (context, index) {
                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ActionChip(
                      label: Text(_suggestions[index], style: const TextStyle(fontSize: AppTheme.textSm)),
                      onPressed: () => _sendMessage(_suggestions[index]),
                      backgroundColor: AppTheme.primaryLight.withOpacity(0.1),
                    ),
                  );
                },
              ),
            ),

          // Input Area
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 5, offset: const Offset(0, -2)),
              ],
            ),
            child: SafeArea(
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _messageController,
                      decoration: InputDecoration(
                        hintText: 'Ask about Nepal travel...',
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24),
                          borderSide: BorderSide.none,
                        ),
                        filled: true,
                        fillColor: AppTheme.backgroundColor,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
                      ),
                      textInputAction: TextInputAction.send,
                      onSubmitted: _sendMessage,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    decoration: const BoxDecoration(
                      color: AppTheme.primaryColor,
                      shape: BoxShape.circle,
                    ),
                    child: IconButton(
                      icon: const Icon(Icons.send, color: Colors.white),
                      onPressed: () => _sendMessage(_messageController.text),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ChatMessage {
  final String text;
  final bool isUser;
  final List<dynamic>? actions;

  ChatMessage({required this.text, required this.isUser, this.actions});
}

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        crossAxisAlignment: message.isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: message.isUser ? MainAxisAlignment.end : MainAxisAlignment.start,
            children: [
              if (!message.isUser)
                Container(
                  margin: const EdgeInsets.only(right: 8),
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.auto_awesome, color: Colors.white, size: 18),
                ),
              Flexible(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: message.isUser ? AppTheme.primaryColor : Colors.white,
                    borderRadius: BorderRadius.circular(16).copyWith(
                      bottomRight: message.isUser ? const Radius.circular(4) : const Radius.circular(16),
                      bottomLeft: message.isUser ? const Radius.circular(16) : const Radius.circular(4),
                    ),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 2, offset: const Offset(0, 1)),
                    ],
                  ),
                  child: Text(
                    message.text,
                    style: TextStyle(
                      color: message.isUser ? Colors.white : AppTheme.textPrimary,
                      fontSize: AppTheme.textBase,
                      height: 1.5,
                    ),
                  ),
                ),
              ),
              if (message.isUser)
                Container(
                  margin: const EdgeInsets.only(left: 8),
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryLight.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.person, color: AppTheme.primaryColor, size: 18),
                ),
            ],
          ),
          if (!message.isUser && message.actions != null && message.actions!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(left: 48, top: 6),
              child: Wrap(
                spacing: 6,
                runSpacing: 4,
                children: message.actions!.map((a) {
                  final label = a['label'] ?? 'Open';
                  final type = a['type'] ?? 'nearby';
                      return ActionChip(
                    label: Text(label, style: const TextStyle(fontSize: 12)),
                    avatar: Icon(
                      type == 'place_detail' ? Icons.place : Icons.map,
                      size: 16,
                    ),
                    onPressed: () {
                      final payload = a['payload'] ?? {};
                      if (type == 'place_detail' && payload['place_id'] != null) {
                        Navigator.push(context, MaterialPageRoute(
                          builder: (_) => PlaceDetailsScreen(
                            place: Place(id: payload['place_id'].toString(), name: label, latitude: 0, longitude: 0, category: ''),
                          ),
                        ));
                      } else if (type == 'nearby') {
                        Navigator.push(context, MaterialPageRoute(
                          builder: (_) => const NearbyPlacesScreen(),
                        ));
                      }
                    },
                  );
                }).toList(),
              ),
            ),
        ],
      ),
    );
  }
}
