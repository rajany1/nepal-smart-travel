import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/models/support_model.dart';
import '../../providers/support_provider.dart';

class SupportConversationScreen extends StatefulWidget {
  final int conversationId;

  const SupportConversationScreen({super.key, required this.conversationId});

  @override
  State<SupportConversationScreen> createState() =>
      _SupportConversationScreenState();
}

class _SupportConversationScreenState extends State<SupportConversationScreen> {
  final TextEditingController _replyController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  bool _isInitialLoading = false;
  Timer? _pollTimer;
  bool _initialLoadDone = false;

  @override
  void initState() {
    super.initState();
    _initialLoad();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      _pollMessages();
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _replyController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    // Load earlier messages when scrolled near the top
    if (_scrollController.position.pixels < 100) {
      _loadEarlier();
    }
  }

  Future<void> _loadEarlier() async {
    final provider = context.read<SupportProvider>();
    if (provider.isLoadingEarlier || !provider.hasMoreMessages) return;
    await provider.loadEarlierMessages(widget.conversationId);
  }

  Future<void> _initialLoad() async {
    setState(() => _isInitialLoading = true);
    final provider = context.read<SupportProvider>();
    await provider.loadConversation(widget.conversationId);
    await provider.loadSatisfaction(widget.conversationId);
    if (mounted) {
      setState(() {
        _isInitialLoading = false;
        _initialLoadDone = true;
      });
      _scrollToBottom();
    }
  }

  Future<void> _pollMessages() async {
    if (!_initialLoadDone) return;
    final provider = context.read<SupportProvider>();
    await provider.pollConversation(widget.conversationId);

    if (!mounted) return;

    if (provider.hasNewMessages) {
      provider.clearNewMessagesFlag();
      _scrollToBottom();
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _sendReply() async {
    final message = _replyController.text.trim();
    if (message.isEmpty) return;

    _replyController.clear();
    final provider = context.read<SupportProvider>();
    final success = await provider.reply(widget.conversationId, message);

    if (success) {
      _scrollToBottom();
    } else if (mounted && provider.error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(provider.error!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Consumer<SupportProvider>(
          builder: (_, provider, __) {
            final conv = provider.currentConversation;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  conv?.subject ?? 'Loading...',
                  style: const TextStyle(fontSize: 16, color: Colors.white),
                ),
                if (conv != null)
                  Text(
                    '${conv.status.toUpperCase()} · ${conv.category}',
                    style:
                        const TextStyle(fontSize: 11, color: Colors.white70),
                  ),
              ],
            );
          },
        ),
        backgroundColor: const Color(0xFF009688),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _initialLoad,
          ),
        ],
      ),
      body: Consumer<SupportProvider>(
        builder: (context, provider, _) {
          if (_isInitialLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          final conv = provider.currentConversation;
          if (conv == null) {
            return const Center(child: Text('Conversation not found'));
          }

          return Column(
            children: [
              Expanded(
                child: _MessagesList(
                  messages: _getMessages(conv),
                  scrollController: _scrollController,
                  hasMoreMessages: provider.hasMoreMessages,
                  isLoadingEarlier: provider.isLoadingEarlier,
                  totalMessages: provider.totalMessages,
                  loadedCount: conv.messages.length,
                  onLoadEarlier: _loadEarlier,
                ),
              ),
              if ((conv.status == 'resolved' || conv.status == 'closed') &&
                  provider.currentSatisfaction == null)
                _SatisfactionReview(
                  conversationId: conv.id,
                  onSubmit: (rating, comment) async {
                    final success = await provider.submitSatisfaction(
                      conv.id,
                      rating: rating,
                      comment: comment,
                    );
                    if (success) {
                      await provider.loadSatisfaction(conv.id);
                    }
                    return success;
                  },
                ),
              if (provider.currentSatisfaction != null)
                _SatisfactionSummary(satisfaction: provider.currentSatisfaction!),
              if (conv.status != 'closed' &&
                  !(conv.status == 'resolved' && provider.currentSatisfaction == null))
                _ReplyInput(
                  controller: _replyController,
                  onSend: _sendReply,
                ),
              if (conv.status == 'closed')
                Container(
                  padding: const EdgeInsets.all(12),
                  color: Colors.grey[100],
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.lock_outline,
                          size: 16, color: Colors.grey),
                      SizedBox(width: 8),
                      Text('This conversation is closed',
                          style: TextStyle(color: Colors.grey, fontSize: 13)),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  List<SupportMessageModel> _getMessages(SupportConversationModel conv) {
    if (conv.messages.isNotEmpty) {
      return conv.messages;
    }
    if (conv.latestMessage != null) {
      return [conv.latestMessage!];
    }
    return [];
  }
}

class _MessagesList extends StatelessWidget {
  final List<SupportMessageModel> messages;
  final ScrollController scrollController;
  final bool hasMoreMessages;
  final bool isLoadingEarlier;
  final int totalMessages;
  final int loadedCount;
  final VoidCallback onLoadEarlier;

  const _MessagesList({
    required this.messages,
    required this.scrollController,
    required this.hasMoreMessages,
    required this.isLoadingEarlier,
    required this.totalMessages,
    required this.loadedCount,
    required this.onLoadEarlier,
  });

  @override
  Widget build(BuildContext context) {
    if (messages.isEmpty) {
      return const Center(
        child: Text('No messages yet',
            style: TextStyle(color: Colors.grey)),
      );
    }

    return Column(
      children: [
        if (hasMoreMessages || isLoadingEarlier)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: isLoadingEarlier
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : GestureDetector(
                    onTap: onLoadEarlier,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.teal[50],
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'Load earlier messages ($loadedCount of $totalMessages)',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.teal[700],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ),
          ),
        Expanded(
          child: ListView.builder(
            controller: scrollController,
            padding: const EdgeInsets.all(12),
            itemCount: messages.length,
            itemBuilder: (context, index) {
              final msg = messages[index];
              return _MessageBubble(message: msg);
            },
          ),
        ),
      ],
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final SupportMessageModel message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    final isUser = message.isFromUser;
    final isAi = message.isFromAi;
    final isSystem = message.isFromSystem;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment:
            isUser ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (!isUser) ...[
            CircleAvatar(
              radius: 14,
              backgroundColor: isAi
                  ? Colors.purple[100]
                  : isSystem
                      ? Colors.grey[200]
                      : const Color(0xFF009688).withOpacity(0.2),
              child: Icon(
                isAi
                    ? Icons.smart_toy_outlined
                    : isSystem
                        ? Icons.info_outline
                        : Icons.support_agent,
                size: 14,
                color: isAi
                    ? Colors.purple[700]
                    : isSystem
                        ? Colors.grey[500]
                        : const Color(0xFF009688),
              ),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isUser
                    ? const Color(0xFF009688)
                    : isAi
                        ? Colors.purple[50]
                        : isSystem
                            ? Colors.grey[100]
                            : Colors.white,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(16),
                  topRight: const Radius.circular(16),
                  bottomLeft: Radius.circular(isUser ? 16 : 4),
                  bottomRight: Radius.circular(isUser ? 4 : 16),
                ),
                border: isUser
                    ? null
                    : Border.all(color: Colors.grey.withOpacity(0.2)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isUser)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Text(
                        isAi
                            ? 'AI Assistant${message.aiModel != null ? ' (${message.aiModel})' : ''}'
                            : isSystem
                                ? 'System'
                                : (message.user?.name ?? 'Support'),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: isAi
                              ? Colors.purple[700]
                              : isSystem
                                  ? Colors.grey[500]
                                  : const Color(0xFF009688),
                        ),
                      ),
                    ),
                  Text(
                    message.content,
                    style: TextStyle(
                      fontSize: 14,
                      color: isUser ? Colors.white : Colors.black87,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      _formatTime(message.createdAt),
                      style: TextStyle(
                        fontSize: 9,
                        color: isUser ? Colors.white60 : Colors.grey[400],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isUser) ...[
            const SizedBox(width: 8),
            CircleAvatar(
              radius: 14,
              backgroundColor: const Color(0xFF009688),
              child: const Icon(Icons.person, size: 14, color: Colors.white),
            ),
          ],
        ],
      ),
    );
  }

  String _formatTime(String time) {
    try {
      final dt = DateTime.parse(time);
      return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return '';
    }
  }
}

class _ReplyInput extends StatelessWidget {
  final TextEditingController controller;
  final VoidCallback onSend;

  const _ReplyInput({
    required this.controller,
    required this.onSend,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                maxLines: null,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => onSend(),
                decoration: InputDecoration(
                  hintText: 'Type your message...',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide(color: Colors.grey[300]!),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide(color: Colors.grey[300]!),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: const BorderSide(color: Color(0xFF009688)),
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16, vertical: 10),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              onPressed: onSend,
              icon: const Icon(Icons.send, color: Color(0xFF009688)),
            ),
          ],
        ),
      ),
    );
  }
}

class _SatisfactionReview extends StatefulWidget {
  final int conversationId;
  final Future<bool> Function(int rating, String? comment) onSubmit;

  const _SatisfactionReview({required this.conversationId, required this.onSubmit});

  @override
  State<_SatisfactionReview> createState() => _SatisfactionReviewState();
}

class _SatisfactionReviewState extends State<_SatisfactionReview> {
  int _selectedRating = 0;
  final _commentController = TextEditingController();
  bool _isSubmitting = false;
  bool _submitted = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_selectedRating == 0 || _isSubmitting) return;
    setState(() => _isSubmitting = true);
    final success = await widget.onSubmit(_selectedRating, _commentController.text.trim());
    if (mounted) {
      setState(() {
        _isSubmitting = false;
        _submitted = success;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_submitted) {
      return Container(
        padding: const EdgeInsets.all(16),
        color: Colors.green[50],
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle, color: Colors.green[600], size: 20),
            const SizedBox(width: 8),
            Text(
              'Thank you for your feedback!',
              style: TextStyle(color: Colors.green[700], fontWeight: FontWeight.w600),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(16),
      color: Colors.blue[50],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'How was your support experience?',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.blue[800]),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (i) {
              final star = i + 1;
              return GestureDetector(
                onTap: () => setState(() => _selectedRating = star),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  child: Icon(
                    star <= _selectedRating ? Icons.star : Icons.star_border,
                    color: star <= _selectedRating ? Colors.amber : Colors.grey[400],
                    size: 36,
                  ),
                ),
              );
            }),
          ),
          if (_selectedRating > 0) ...[
            const SizedBox(height: 6),
            Center(
              child: Text(
                ['', 'Very dissatisfied', 'Dissatisfied', 'Neutral', 'Satisfied', 'Very satisfied'][_selectedRating],
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
            ),
          ],
          const SizedBox(height: 10),
          TextField(
            controller: _commentController,
            maxLines: 2,
            maxLength: 1000,
            decoration: InputDecoration(
              hintText: 'Optional comment...',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              isDense: true,
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _selectedRating > 0 && !_isSubmitting ? _submit : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF009688),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: _isSubmitting
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Submit Review'),
            ),
          ),
        ],
      ),
    );
  }
}

class _SatisfactionSummary extends StatelessWidget {
  final SupportSatisfactionModel satisfaction;

  const _SatisfactionSummary({required this.satisfaction});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      color: Colors.green[50],
      child: Row(
        children: [
          Icon(Icons.check_circle, color: Colors.green[600], size: 18),
          const SizedBox(width: 8),
          Text(
            'Review submitted: ',
            style: TextStyle(fontSize: 12, color: Colors.green[700]),
          ),
          ...List.generate(5, (i) => Icon(
            i < satisfaction.rating ? Icons.star : Icons.star_border,
            color: i < satisfaction.rating ? Colors.amber : Colors.grey[400],
            size: 14,
          )),
          const SizedBox(width: 6),
          Text(
            satisfaction.ratingLabel,
            style: TextStyle(fontSize: 11, color: Colors.grey[600]),
          ),
        ],
      ),
    );
  }
}
