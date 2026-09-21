class SupportConversationModel {
  final int id;
  final String subject;
  final String category;
  final String priority;
  final String status;
  final String? statusLabel;
  final String? categoryLabel;
  final String? priorityLabel;
  final int messageCount;
  final String? lastReplyAt;
  final UserModel? user;
  final UserModel? assignee;
  final SupportMessageModel? latestMessage;
  final List<SupportMessageModel> messages;
  final bool aiHandled;
  final Map<String, dynamic>? metadata;
  final String createdAt;

  SupportConversationModel({
    required this.id,
    required this.subject,
    required this.category,
    required this.priority,
    required this.status,
    this.statusLabel,
    this.categoryLabel,
    this.priorityLabel,
    required this.messageCount,
    this.lastReplyAt,
    this.user,
    this.assignee,
    this.latestMessage,
    this.messages = const [],
    this.aiHandled = false,
    this.metadata,
    required this.createdAt,
  });

  factory SupportConversationModel.fromJson(Map<String, dynamic> json) {
    return SupportConversationModel(
      id: json['id'],
      subject: json['subject'] ?? '',
      category: json['category'] ?? 'general',
      priority: json['priority'] ?? 'normal',
      status: json['status'] ?? 'open',
      statusLabel: json['status_label'],
      categoryLabel: json['category_label'],
      priorityLabel: json['priority_label'],
      messageCount: json['message_count'] ?? 0,
      lastReplyAt: json['last_reply_at'],
      user: json['user'] != null ? UserModel.fromJson(json['user']) : null,
      assignee: json['assignee'] != null ? UserModel.fromJson(json['assignee']) : null,
      latestMessage: json['latest_message'] != null
          ? SupportMessageModel.fromJson(json['latest_message'])
          : null,
      messages: json['messages'] != null
          ? (json['messages'] as List)
              .map((e) => SupportMessageModel.fromJson(e))
              .toList()
          : [],
      aiHandled: json['ai_handled'] ?? false,
      metadata: json['metadata'],
      createdAt: json['created_at'] ?? '',
    );
  }
}

class SupportMessageModel {
  final int id;
  final int? userId;
  final String senderType;
  final String content;
  final String? aiModel;
  final double? aiConfidence;
  final List<dynamic>? aiActions;
  final bool isInternalNote;
  final UserModel? user;
  final String createdAt;

  SupportMessageModel({
    required this.id,
    this.userId,
    required this.senderType,
    required this.content,
    this.aiModel,
    this.aiConfidence,
    this.aiActions,
    this.isInternalNote = false,
    this.user,
    required this.createdAt,
  });

  factory SupportMessageModel.fromJson(Map<String, dynamic> json) {
    return SupportMessageModel(
      id: json['id'],
      userId: json['user_id'],
      senderType: json['sender_type'] ?? 'user',
      content: json['content'] ?? '',
      aiModel: json['ai_model'],
      aiConfidence: json['ai_confidence']?.toDouble(),
      aiActions: json['ai_actions'],
      isInternalNote: json['is_internal_note'] ?? false,
      user: json['user'] != null ? UserModel.fromJson(json['user']) : null,
      createdAt: json['created_at'] ?? '',
    );
  }

  bool get isFromUser => senderType == 'user';
  bool get isFromAi => senderType == 'ai';
  bool get isFromSupport => senderType == 'support';
  bool get isFromSystem => senderType == 'system';
}

class UserModel {
  final int id;
  final String name;
  final String? email;

  UserModel({required this.id, required this.name, this.email});

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'],
      name: json['name'] ?? '',
      email: json['email'],
    );
  }
}

class SupportSatisfactionModel {
  final int id;
  final int rating;
  final String? comment;
  final String submittedAt;

  SupportSatisfactionModel({
    required this.id,
    required this.rating,
    this.comment,
    required this.submittedAt,
  });

  factory SupportSatisfactionModel.fromJson(Map<String, dynamic> json) {
    return SupportSatisfactionModel(
      id: json['id'],
      rating: json['rating'],
      comment: json['comment'],
      submittedAt: json['submitted_at'] ?? '',
    );
  }

  String get ratingLabel => switch (rating) {
    1 => 'Very dissatisfied',
    2 => 'Dissatisfied',
    3 => 'Neutral',
    4 => 'Satisfied',
    5 => 'Very satisfied',
    _ => 'Unknown',
  };
}
