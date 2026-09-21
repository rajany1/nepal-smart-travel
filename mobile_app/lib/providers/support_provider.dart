import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../core/models/support_model.dart';
import '../core/api/api_client.dart';

class SupportProvider extends ChangeNotifier {
  List<SupportConversationModel> _conversations = [];
  SupportConversationModel? _currentConversation;
  bool _isLoading = false;
  bool _isPolling = false;
  bool _hasNewMessages = false;
  String? _error;
  int _currentPage = 1;
  bool _hasMorePages = true;
  SupportSatisfactionModel? _currentSatisfaction;

  // Message pagination
  bool _hasMoreMessages = false;
  bool _isLoadingEarlier = false;
  int? _oldestMessageId;
  int _totalMessages = 0;

  List<SupportConversationModel> get conversations => _conversations;
  SupportConversationModel? get currentConversation => _currentConversation;
  bool get isLoading => _isLoading;
  bool get isPolling => _isPolling;
  bool get hasNewMessages => _hasNewMessages;
  String? get error => _error;
  bool get hasMorePages => _hasMorePages;
  SupportSatisfactionModel? get currentSatisfaction => _currentSatisfaction;
  bool get hasMoreMessages => _hasMoreMessages;
  bool get isLoadingEarlier => _isLoadingEarlier;
  int get totalMessages => _totalMessages;

  void clearNewMessagesFlag() {
    _hasNewMessages = false;
  }

  Future<void> loadConversations({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
      _hasMorePages = true;
      _conversations = [];
    }

    if (!_hasMorePages) return;

    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio.get(
        '/support',
        queryParameters: {'page': _currentPage},
      );

      if (response.data['success'] == true) {
        final data = response.data['data'];
        final items = (data['data'] as List)
            .map((e) => SupportConversationModel.fromJson(e))
            .toList();

        if (refresh) {
          _conversations = items;
        } else {
          _conversations.addAll(items);
        }

        _hasMorePages = data['current_page'] < data['last_page'];
        _currentPage++;
      }
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? 'Failed to load conversations';
    } catch (e) {
      _error = 'An unexpected error occurred';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  DateTime? _lastPollTime;

  /// Initial load: sets isLoading, replaces conversation.
  /// Loads latest 50 messages by default.
  Future<bool> loadConversation(int id) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio.get('/support/$id');

      if (response.data['success'] == true) {
        _currentConversation =
            SupportConversationModel.fromJson(response.data['data']);
        _lastPollTime = DateTime.now();

        // Message pagination metadata
        final msgMeta = response.data['messages'];
        if (msgMeta != null) {
          _hasMoreMessages = msgMeta['has_more'] ?? false;
          _oldestMessageId = msgMeta['oldest_id'];
          _totalMessages = msgMeta['total'] ?? _currentConversation!.messages.length;
        } else {
          _hasMoreMessages = false;
          _oldestMessageId = null;
          _totalMessages = _currentConversation!.messages.length;
        }

        _isLoading = false;
        notifyListeners();
        return true;
      }
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? 'Failed to load conversation';
    } catch (e) {
      _error = 'An unexpected error occurred';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  /// Load earlier (older) messages for the current conversation.
  Future<void> loadEarlierMessages(int conversationId) async {
    if (_isLoadingEarlier || !_hasMoreMessages || _oldestMessageId == null) return;
    _isLoadingEarlier = true;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio.get(
        '/support/$conversationId',
        queryParameters: {'before_message_id': _oldestMessageId},
      );

      if (response.data['success'] == true) {
        final olderMessages = (response.data['data']['messages'] as List)
            .map((e) => SupportMessageModel.fromJson(e))
            .toList();

        if (olderMessages.isNotEmpty && _currentConversation != null) {
          final existingIds = _currentConversation!.messages.map((m) => m.id).toSet();
          final newMessages = olderMessages.where((m) => !existingIds.contains(m.id)).toList();

          _currentConversation = SupportConversationModel(
            id: _currentConversation!.id,
            subject: _currentConversation!.subject,
            category: _currentConversation!.category,
            priority: _currentConversation!.priority,
            status: _currentConversation!.status,
            statusLabel: _currentConversation!.statusLabel,
            categoryLabel: _currentConversation!.categoryLabel,
            priorityLabel: _currentConversation!.priorityLabel,
            messageCount: _currentConversation!.messageCount,
            lastReplyAt: _currentConversation!.lastReplyAt,
            user: _currentConversation!.user,
            assignee: _currentConversation!.assignee,
            latestMessage: _currentConversation!.latestMessage,
            messages: [...newMessages, ..._currentConversation!.messages],
            aiHandled: _currentConversation!.aiHandled,
            metadata: _currentConversation!.metadata,
            createdAt: _currentConversation!.createdAt,
          );

          // Update pagination metadata
          final msgMeta = response.data['messages'];
          _hasMoreMessages = msgMeta['has_more'] ?? false;
          _oldestMessageId = msgMeta['oldest_id'] ?? _oldestMessageId;
          _totalMessages = msgMeta['total'] ?? _totalMessages;

          notifyListeners();
        }
      }
    } on DioException catch (_) {
      // Silent
    } catch (_) {
      // Silent
    } finally {
      _isLoadingEarlier = false;
      notifyListeners();
    }
  }

  /// Silent poll: does NOT touch _isLoading, only notifies if new messages.
  Future<void> pollConversation(int id) async {
    if (_isPolling) return; // prevent overlapping
    _isPolling = true;

    try {
      String url = '/support/$id';
      if (_lastPollTime != null) {
        url += '?since=${_lastPollTime!.millisecondsSinceEpoch ~/ 1000}';
      }

      final response = await ApiClient.instance.dio.get(url);

      if (response.data['success'] == true) {
        final polled =
            SupportConversationModel.fromJson(response.data['data']);

        if (_currentConversation != null && _currentConversation!.id == id) {
          final existingIds =
              _currentConversation!.messages.map((m) => m.id).toSet();
          final newMessages = polled.messages
              .where((m) => !existingIds.contains(m.id))
              .toList();

          if (newMessages.isNotEmpty) {
            _currentConversation = SupportConversationModel(
              id: _currentConversation!.id,
              subject: _currentConversation!.subject,
              category: _currentConversation!.category,
              priority: _currentConversation!.priority,
              status: polled.status,
              statusLabel: polled.statusLabel,
              categoryLabel: polled.categoryLabel,
              priorityLabel: polled.priorityLabel,
              messageCount: polled.messageCount,
              lastReplyAt: polled.lastReplyAt,
              user: _currentConversation!.user,
              assignee: polled.assignee ?? _currentConversation!.assignee,
              latestMessage:
                  polled.latestMessage ?? _currentConversation!.latestMessage,
              messages: [..._currentConversation!.messages, ...newMessages],
              aiHandled: polled.aiHandled,
              metadata: polled.metadata,
              createdAt: _currentConversation!.createdAt,
            );
            _hasNewMessages = true;
            _lastPollTime = DateTime.now();
            notifyListeners(); // Only notify when new messages exist
          }
          // else: no new messages, NO notifyListeners, NO state change
        }
      }
    } on DioException catch (_) {
      // Polling failure: silent, keep current state
    } catch (_) {
      // Silent
    } finally {
      _isPolling = false;
    }
  }

  Future<bool> createConversation({
    required String subject,
    required String message,
    String category = 'general',
    String priority = 'normal',
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio.post('/support', data: {
        'subject': subject,
        'message': message,
        'category': category,
        'priority': priority,
      });

      if (response.data['success'] == true) {
        final conversation =
            SupportConversationModel.fromJson(response.data['data']);
        _conversations.insert(0, conversation);
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? 'Failed to create conversation';
    } catch (e) {
      _error = 'An unexpected error occurred';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  Future<bool> reply(int conversationId, String message) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio
          .post('/support/$conversationId/reply', data: {
        'message': message,
      });

      if (response.data['success'] == true) {
        final newMessage =
            SupportMessageModel.fromJson(response.data['data']);

        if (_currentConversation != null &&
            _currentConversation!.id == conversationId) {
          final updatedMessages = [..._currentConversation!.messages, newMessage];
          _currentConversation = SupportConversationModel(
            id: _currentConversation!.id,
            subject: _currentConversation!.subject,
            category: _currentConversation!.category,
            priority: _currentConversation!.priority,
            status: _currentConversation!.status,
            messageCount: _currentConversation!.messageCount + 1,
            lastReplyAt: DateTime.now().toIso8601String(),
            user: _currentConversation!.user,
            assignee: _currentConversation!.assignee,
            latestMessage: newMessage,
            messages: updatedMessages,
            aiHandled: _currentConversation!.aiHandled,
            metadata: _currentConversation!.metadata,
            createdAt: _currentConversation!.createdAt,
          );
        }

        _isLoading = false;
        notifyListeners();
        return true;
      }
    } on DioException catch (e) {
      _error = e.response?.data?['message'] ?? 'Failed to send reply';
    } catch (e) {
      _error = 'An unexpected error occurred';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  Future<void> loadSatisfaction(int conversationId) async {
    try {
      final response = await ApiClient.instance.getSupportSatisfaction(conversationId);
      if (response.data['success'] == true) {
        final data = response.data['data'];
        _currentSatisfaction = data != null ? SupportSatisfactionModel.fromJson(data) : null;
        notifyListeners();
      }
    } catch (_) {
      // Silent — satisfaction load failure is non-critical
    }
  }

  Future<bool> submitSatisfaction(int conversationId, {required int rating, String? comment}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.submitSupportSatisfaction(
        conversationId,
        rating: rating,
        comment: comment,
      );

      if (response.data['success'] == true) {
        _currentSatisfaction = SupportSatisfactionModel.fromJson(response.data['data']);
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = 'Failed to submit review. Please try again.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }
}
