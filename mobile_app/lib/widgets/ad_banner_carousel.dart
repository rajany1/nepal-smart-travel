import 'dart:async';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/api/api_client.dart';

class AdBannerCarousel extends StatefulWidget {
  final String adContext;
  final dynamic reportId;
  final bool persistent;
  const AdBannerCarousel({super.key, this.adContext = 'home', this.reportId, this.persistent = false});

  @override
  State<AdBannerCarousel> createState() => _AdBannerCarouselState();
}

class _AdBannerCarouselState extends State<AdBannerCarousel> {
  final _api = ApiClient.instance;
  List<dynamic> _ads = [];
  bool _loaded = false;
  final _pageCtrl = PageController(viewportFraction: 0.88);
  int _currentPage = 0;
  Timer? _autoSlideTimer;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _autoSlideTimer?.cancel();
    _pageCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final res = await _api.getActiveAds(adContext: widget.adContext, limit: 5, persistent: widget.persistent);
      _ads = (res.data['data'] as List<dynamic>?) ?? [];
    } catch (_) {}
    if (mounted) {
      setState(() { _loaded = true; });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _trackVisible();
        _startAutoSlide();
      });
    }
  }

  void _startAutoSlide() {
    _autoSlideTimer?.cancel();
    if (_ads.length <= 1) return;
    _autoSlideTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || _ads.isEmpty) return;
      final next = (_currentPage + 1) % _ads.length;
      _pageCtrl.animateToPage(
        next,
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeInOut,
      );
    });
  }

  void _trackVisible() {
    final ad = _ads.isEmpty ? null : _ads[_currentPage];
    if (ad is Map<String, dynamic> && ad['id'] is int) {
      _api.trackAdImpression(ad['id'] as int, reportId: widget.reportId, context: widget.adContext).then((_) {}).catchError((_) {});
    }
  }

  Future<void> _onTap(dynamic ad) async {
    if (ad is! Map<String, dynamic>) return;
    final id = ad['id'];
    if (id is int) {
      _api.trackAdClick(id, reportId: widget.reportId, context: widget.adContext).then((_) {}).catchError((_) {});
    }
    final target = ad['target_url'] as String?;
    if (target != null && target.isNotEmpty) {
      final uri = Uri.tryParse(target);
      if (uri != null) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ads.isEmpty) return const SizedBox.shrink();

    final isSingle = _ads.length == 1;

    return SizedBox(
      height: 170,
      child: Column(
        children: [
          Expanded(
            child: PageView.builder(
              controller: _pageCtrl,
              onPageChanged: (i) {
                setState(() { _currentPage = i; });
                _trackVisible();
              },
              itemCount: isSingle ? 1 : _ads.length * 100,
              itemBuilder: (_, i) {
                final adIdx = isSingle ? 0 : i % _ads.length;
                final ad = _ads[adIdx] as Map<String, dynamic>;
                return _buildBannerCard(ad, adIdx);
              },
            ),
          ),
          if (!isSingle) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (int i = 0; i < _ads.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: _currentPage == i ? 24 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                        color: _currentPage == i
                            ? Theme.of(context).colorScheme.primary
                            : Theme.of(context).colorScheme.primary.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildBannerCard(Map<String, dynamic> ad, int index) {
    final gradients = [
      [const Color(0xFF00897B), const Color(0xFF4DB6AC)],
      [const Color(0xFF5C6BC0), const Color(0xFF9FA8DA)],
      [const Color(0xFFEF6C00), const Color(0xFFFFB74D)],
      [const Color(0xFFC62828), const Color(0xFFEF5350)],
      [const Color(0xFF2E7D32), const Color(0xFF81C784)],
    ];
    final colors = gradients[index % gradients.length];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: GestureDetector(
        onTap: () => _onTap(ad),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: colors,
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: colors[0].withValues(alpha: 0.3),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.25),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        ad['ad_type'] == 'banner' ? 'Sponsored' : 'Promoted',
                        style: const TextStyle(
                          fontSize: 10,
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      ad['name'] ?? 'Advertisement',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (ad['content'] != null && (ad['content'] as String).isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        ad['content'],
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.white.withValues(alpha: 0.85),
                        ),
                      ),
                    ],
                    const Spacer(),
                    Row(
                      children: [
                        Icon(
                          ad['ad_type'] == 'banner' ? Icons.open_in_new : Icons.store,
                          color: Colors.white.withValues(alpha: 0.7),
                          size: 12,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          ad['ad_type'] == 'banner' ? 'Learn More' : 'Visit',
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.9),
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Icon(
                Icons.arrow_forward_ios,
                color: Colors.white.withValues(alpha: 0.5),
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
