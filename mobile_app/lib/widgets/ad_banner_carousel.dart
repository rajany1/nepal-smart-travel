import 'package:flutter/material.dart';
import '../core/api/api_client.dart';

class AdBannerCarousel extends StatefulWidget {
  const AdBannerCarousel({super.key});

  @override
  State<AdBannerCarousel> createState() => _AdBannerCarouselState();
}

class _AdBannerCarouselState extends State<AdBannerCarousel> {
  final _api = ApiClient.instance;
  List<dynamic> _ads = [];
  bool _loaded = false;
  final _pageCtrl = PageController(viewportFraction: 0.92);
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _pageCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final res = await _api.getActiveAds();
      _ads = (res.data['data'] as List<dynamic>?) ?? [];
    } catch (_) {}
    if (mounted) setState(() { _loaded = true; });
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ads.isEmpty) return const SizedBox.shrink();
    return SizedBox(
      height: 160,
      child: Column(
        children: [
          Expanded(
            child: PageView.builder(
              controller: _pageCtrl,
              onPageChanged: (i) => setState(() { _currentPage = i; }),
              itemCount: _ads.length,
              itemBuilder: (_, i) {
                final ad = _ads[i] as Map<String, dynamic>;
                final color = Colors.primaries[i % Colors.primaries.length];
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  child: Card(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 2,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(14),
                      onTap: () {},
                      child: Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(14),
                          gradient: LinearGradient(colors: [color.shade200, color.shade50]),
                        ),
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(ad['ad_type'] == 'banner' ? 'Sponsored' : 'Promoted', style: TextStyle(fontSize: 11, color: color.shade700, fontWeight: FontWeight.w700)),
                            const SizedBox(height: 6),
                            Text(ad['name'] ?? 'Advertisement', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color.shade900)),
                            if (ad['content'] != null && (ad['content'] as String).isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(ad['content'], maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 13, color: color.shade700)),
                            ],
                            const Spacer(),
                            Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(6)), child: Text(ad['ad_type'] == 'banner' ? 'Learn More' : 'Visit', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600))),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 6),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            for (int i = 0; i < _ads.length; i++)
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 2),
                width: _currentPage == i ? 20 : 8,
                height: 6,
                decoration: BoxDecoration(
                  color: _currentPage == i ? Colors.blue : Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
          ]),
          const SizedBox(height: 4),
        ],
      ),
    );
  }
}
