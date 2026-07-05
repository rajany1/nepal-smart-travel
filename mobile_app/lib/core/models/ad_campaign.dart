import 'package:flutter/foundation.dart';

class AdCampaignModel {
  final int id;
  final String name;
  final String adType; // banner, promoted_place, sponsored_card
  final String? content;
  final String? image;
  final String? targetUrl;
  final String? targetDistrict;
  final String? targetCategory;
  final String? businessName;

  AdCampaignModel({
    required this.id,
    required this.name,
    required this.adType,
    this.content,
    this.image,
    this.targetUrl,
    this.targetDistrict,
    this.targetCategory,
    this.businessName,
  });

  factory AdCampaignModel.fromJson(Map<String, dynamic> json) {
    return AdCampaignModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      adType: json['ad_type'] ?? 'banner',
      content: json['content'],
      image: json['image'],
      targetUrl: json['target_url'],
      targetDistrict: json['target_district'],
      targetCategory: json['target_category'],
      businessName: json['business_name'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'ad_type': adType,
    'content': content,
    'image': image,
    'target_url': targetUrl,
    'target_district': targetDistrict,
    'target_category': targetCategory,
    'business_name': businessName,
  };
}
