class ReportComment {
  final String id;
  final String content;
  final String userName;
  final String? userAvatar;
  final String userId;
  final String reportUserId;
  final String? parentCommentId;
  final String? replyToName;
  final List<ReportComment> replies;
  final DateTime createdAt;
  final String timeAgo;

  ReportComment({
    required this.id,
    required this.content,
    required this.userName,
    this.userAvatar,
    this.userId = '',
    this.reportUserId = '',
    this.parentCommentId,
    this.replyToName,
    this.replies = const [],
    required this.createdAt,
    this.timeAgo = '',
  });

  bool get isAuthor => userId.isNotEmpty && userId == reportUserId;
  bool get hasReplies => replies.isNotEmpty;

  factory ReportComment.fromJson(Map<String, dynamic> json) {
    // Parse nested replies if present
    List<ReportComment> childReplies = [];
    if (json['replies'] != null && json['replies'] is List) {
      childReplies = (json['replies'] as List)
          .map((r) => ReportComment.fromJson(r is Map ? Map<String, dynamic>.from(r) : {}))
          .toList();
    }

    return ReportComment(
      id: (json['id'] ?? '').toString(),
      content: json['content'] ?? '',
      userName: json['user_name'] ?? 'Anonymous',
      userAvatar: json['user_avatar'],
      userId: (json['user_id'] ?? '').toString(),
      reportUserId: (json['report_user_id'] ?? '').toString(),
      parentCommentId: json['parent_comment_id']?.toString(),
      replyToName: json['reply_to_name'],
      replies: childReplies,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
      timeAgo: json['time_ago'] ?? '',
    );
  }
}