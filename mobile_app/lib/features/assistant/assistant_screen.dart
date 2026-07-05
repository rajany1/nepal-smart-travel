import 'package:flutter/material.dart';
import '../../config/themes/app_theme.dart';

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

    // Simulate AI response
    Future.delayed(const Duration(seconds: 2), () {
      if (!mounted) return;
      setState(() {
        _isTyping = false;
        _messages.add(_generateResponse(text));
      });
      _scrollToBottom();
    });
  }

  ChatMessage _generateResponse(String query) {
    String response;
    if (query.toLowerCase().contains('pokhara') || query.toLowerCase().contains('near')) {
      response = 'Here are the best places near Pokhara:\n\n'
          '🏞️ **Phewa Lake** - Beautiful lake with mountain views\n'
          '⛰️ **Sarangkot** - Sunrise view point (4.7⭐)\n'
          '💧 **Devi\'s Fall** - Stunning waterfall\n'
          '🛕 **Peace Stupa** - Buddhist monument on hilltop\n'
          '🪂 **Paragliding** - Adventure activity\n\n'
          'Would you like directions or more details about any of these?';
    } else if (query.toLowerCase().contains('road') || query.toLowerCase().contains('traffic')) {
      response = 'Current Road Conditions:\n\n'
          '🚧 **Prithvi Highway**: Blockage near Malekhu (High priority)\n'
          '✅ **Kathmandu-Pokhara**: Open with caution\n'
          '⚠️ **Mustang Route**: Partially blocked due to weather\n'
          '✅ **Kathmandu Valley**: Normal traffic\n\n'
          '⚠️ Always check live reports before traveling!';
    } else if (query.toLowerCase().contains('hotel') || query.toLowerCase().contains('stay')) {
      response = 'Recommended accommodations in Thamel:\n\n'
          '🏨 **Hotel Barahi** (4.2⭐) - Mid-range, great location\n'
          '🏨 **Kathmandu Guest House** (4.0⭐) - Budget friendly\n'
          '🏨 **Dwarika\'s Hotel** (4.8⭐) - Luxury experience\n\n'
          'Prices range from NPR 1,000-15,000 per night. Would you like more options?';
    } else if (query.toLowerCase().contains('hidden') || query.toLowerCase().contains('gem')) {
      response = 'Hidden Gems in Kathmandu Valley:\n\n'
          '🏛️ **Boudhanath Backstreets** - Peaceful monasteries\n'
          '🌿 **Gokarna Forest** - Nature trail just outside city\n'
          '🏘️ **Kirtipur** - Ancient Newari town with valley views\n'
          '⛲ **Sundarijal** - Waterfall and hiking spot\n\n'
          'These places are less crowded and offer authentic experiences!';
    } else if (query.toLowerCase().contains('weather') || query.toLowerCase().contains('rain')) {
      response = 'Current Weather Update:\n\n'
          '🌤️ **Kathmandu**: 25°C, Partly Cloudy\n'
          '🌧️ **Pokhara**: 22°C, Light Rain Expected\n'
          '☀️ **Mustang**: 18°C, Clear Sky\n'
          '🌨️ **Everest Region**: -5°C, Snow at higher altitudes\n\n'
          'Pack accordingly for your destinations!';
    } else if (query.toLowerCase().contains('emergency') || query.toLowerCase().contains('sos')) {
      response = '🚨 Emergency Numbers in Nepal:\n\n'
          '🚑 Ambulance: **102**\n'
          '🚔 Police: **100**\n'
          '🔥 Fire: **101**\n'
          '🏔️ Mountain Rescue: **1149**\n'
          '🗣️ Tourist Police: **1144**\n\n'
          'Save these numbers for quick access!';
    } else {
      response = 'Thank you for your question! I\'ll help you with travel information about Nepal. '
          'Could you please be more specific? You can ask about:\n\n'
          '📍 Places to visit\n'
          '🛣️ Road conditions\n'
          '🏨 Accommodations\n'
          '🍜 Local food\n'
          '🚨 Emergency info\n'
          '🌤️ Weather updates\n'
          '🗺️ Hidden destinations';
    }
    return ChatMessage(text: response, isUser: false);
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

  ChatMessage({required this.text, required this.isUser});
}

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
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
    );
  }
}
