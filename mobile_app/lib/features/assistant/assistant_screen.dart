import 'dart:async';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../config/constants/app_constants.dart';
import '../../core/services/location_service.dart';
import '../../providers/auth_provider.dart';
import '../places/nearby_map_screen.dart';
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
  final List<ChatMessage> _messages = [];
  bool _isTyping = false;

  // Daily quota state (backend: 5 chats/day, Redis counter)
  bool _isGuest = false;
  int _limit = 5;
  int _remaining = 5;
  DateTime? _cooldownUntil;
  Timer? _cooldownTimer;
  int _tick = 0;

  final List<String> _suggestions = [
    'Best places near Pokhara?',
    'Road conditions to Mustang?',
    'Budget hotels in Thamel?',
    'Hidden gems in Kathmandu?',
    'Weather in Pokhara today?',
    'Emergency numbers in Nepal?',
  ];

  bool get _isCoolingDown =>
      _cooldownUntil != null &&
      DateTime.now().isBefore(_cooldownUntil!);

  @override
  void initState() {
    super.initState();
    _messages.add(ChatMessage(
      text: context.read<LocalizationService>().t(
        'Namaste! 👋 I\'m your AI Travel Assistant for Nepal. Ask me about places to visit, road conditions, safety tips, or anything about traveling in Nepal!',
      ),
      isUser: false,
    ));
    _isGuest = !context.read<AuthProvider>().isAuthenticated;
    if (!_isGuest) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _fetchQuota());
    }
  }

  Future<void> _fetchQuota() async {
    try {
      final response = await ApiClient.instance.getAssistantQuota();
      if (!mounted) return;
      final data = response.data is Map ? response.data['data'] : null;
      if (data is Map) {
        setState(() {
          _limit = (data['limit'] as num?)?.toInt() ?? _limit;
          _remaining = (data['remaining'] as num?)?.toInt() ?? _remaining;
          if (_remaining <= 0) {
            _startCooldown(_parseResetAt(data['reset_at']));
          }
        });
      }
    } catch (_) {
      // Quota fetch failures shouldn't block chatting — backend enforces anyway.
    }
  }

  DateTime? _parseResetAt(dynamic value) {
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value)?.toLocal();
    }
    return null;
  }

  void _applyQuotaFromChat(Map? data) {
    final quota = data is Map ? data['quota'] : null;
    if (quota is Map) {
      _limit = (quota['limit'] as num?)?.toInt() ?? _limit;
      _remaining = (quota['remaining'] as num?)?.toInt() ?? 0;
      if (_remaining <= 0) {
        _startCooldown(_parseResetAt(quota['reset_at']));
      }
    }
    if (mounted) setState(() {});
  }

  void _startCooldown(DateTime? until) {
    _cooldownTimer?.cancel();
    _cooldownUntil =
        until ?? DateTime.now().add(const Duration(hours: 12));
    _remaining = 0;
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      if (!_isCoolingDown) {
        _cooldownTimer?.cancel();
        _cooldownUntil = null;
        _remaining = _limit;
        setState(() {});
        _fetchQuota();
      } else {
        setState(() => _tick++);
      }
    });
  }

  String get _countdownText {
    final remaining = _cooldownUntil?.difference(DateTime.now());
    if (remaining == null || remaining.isNegative) return '';
    final h = remaining.inHours;
    final m = remaining.inMinutes % 60;
    final s = remaining.inSeconds % 60;
    if (h > 0) return '${h}h ${m}m';
    if (m > 0) return '${m}m ${s}s';
    return '${s}s';
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    _cooldownTimer?.cancel();
    super.dispose();
  }

  void _sendMessage(String text) {
    if (text.trim().isEmpty) return;
    if (_isGuest || _isTyping || _isCoolingDown) return;

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

      _applyQuotaFromChat(data is Map ? data : null);

      setState(() {
        _isTyping = false;
        _messages.add(ChatMessage(text: reply, isUser: false, actions: actions));
      });
    } on DioException catch (e) {
      if (!mounted) return;
      final isRateLimited = e.response?.statusCode == 429;
      if (isRateLimited) {
        final body = e.response?.data;
        final bodyData = body is Map ? body['data'] : null;
        _startCooldown(_parseResetAt(
            bodyData is Map ? bodyData['reset_at'] : null));
        setState(() => _isTyping = false);
        _messages.add(ChatMessage(
          text: context.t('Daily chat limit reached — you can chat again tomorrow. 🌏'),
          isUser: false,
        ));
        _scrollToBottom();
        return;
      }
      setState(() {
        _isTyping = false;
        _messages.add(ChatMessage(
          text: context.t('Sorry, I couldn\'t reach the server. Please check your connection and try again.'),
          isUser: false,
        ));
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isTyping = false;
        _messages.add(ChatMessage(
          text: context.t('Sorry, I couldn\'t reach the server. Please check your connection and try again.'),
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
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            margin: const EdgeInsets.all(8),
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.arrow_back_ios_new, color: Color(0xFF2D3436), size: 18),
          ),
        ),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: const Color(0xFF2980B9).withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.smart_toy_outlined, color: Color(0xFF2980B9), size: 20),
            ),
            const SizedBox(width: 10),
            Text(
              context.t('AI Assistant'),
              style: const TextStyle(
                color: Color(0xFF2D3436),
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          GestureDetector(
            onTap: () {
              setState(() {
                _messages.clear();
                _messages.add(ChatMessage(
                  text: context.t('Namaste! 👋 I\'m your AI Travel Assistant for Nepal. Ask me about places to visit, road conditions, safety tips, or anything about traveling in Nepal!'),
                  isUser: false,
                ));
              });
            },
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(Icons.delete_outline, color: Colors.grey.shade600, size: 20),
            ),
          ),
          const SizedBox(width: 8),
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
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        const SizedBox(width: 48),
                        const SizedBox(
                          width: 24, height: 24,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 8),
                        Text(context.t('Thinking...'), style: const TextStyle(color: AppTheme.textSecondary)),
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
                    child: GestureDetector(
                      onTap: () => _sendMessage(_suggestions[index]),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.grey.shade200),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.04),
                              blurRadius: 4,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Text(
                          _suggestions[index],
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: Color(0xFF2D3436),
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),

          // Guest gate — AI chat requires a login (per-user daily quota)
          if (_isGuest)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(12, 8, 12, 0),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: AppTheme.primaryLight.withOpacity(0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.lock_outline,
                      size: 18, color: AppTheme.primaryColor),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      context.t('Login to chat with the AI assistant'),
                      style: const TextStyle(fontSize: AppTheme.textSm),
                    ),
                  ),
                  TextButton(
                    onPressed: () => Navigator.pushNamed(context, '/login'),
                    child: Text(context.t('Login')),
                  ),
                ],
              ),
            ),

          // Daily limit cooldown banner
          if (!_isGuest && _isCoolingDown)
            Container(
              width: double.infinity,
              key: ValueKey(_tick),
              margin: const EdgeInsets.fromLTRB(12, 8, 12, 0),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.orange.withOpacity(0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.hourglass_bottom,
                      size: 18, color: Colors.orange),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      '${context.t('Daily limit reached')} ($_remaining/$_limit) — ${context.t('resets in')} $_countdownText',
                      style: const TextStyle(fontSize: AppTheme.textSm),
                    ),
                  ),
                ],
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
                      enabled: !_isGuest && !_isTyping && !_isCoolingDown,
                      decoration: InputDecoration(
                        hintText: _isGuest
                            ? context.t('Login to start chatting')
                            : _isCoolingDown
                                ? context.t('Daily limit reached')
                                : context.t('Ask about Nepal travel...'),
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
                    decoration: BoxDecoration(
                      color: (_isGuest || _isTyping || _isCoolingDown)
                          ? AppTheme.textSecondary.withOpacity(0.4)
                          : AppTheme.primaryColor,
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
                  final label = a['label'] ?? context.t('Open');
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
                          builder: (_) => const NearbyMapScreen(),
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
