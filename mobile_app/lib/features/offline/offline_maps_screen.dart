import 'dart:async';

import 'package:flutter/material.dart';

import '../../config/themes/app_theme.dart';
import '../../core/services/offline_db_service.dart';
import '../../core/services/offline_tile_provider.dart';

/// Offline Maps: download the full Nepal map pack (30/60-day validity),
/// update (replace) it once expired, or delete it to free storage.
class OfflineMapsScreen extends StatefulWidget {
  const OfflineMapsScreen({super.key});

  @override
  State<OfflineMapsScreen> createState() => _OfflineMapsScreenState();
}

class _OfflineMapsScreenState extends State<OfflineMapsScreen> {
  static const String _packName = 'nepal';

  Map<String, dynamic>? _pack;
  bool _isDownloading = false;
  bool _cancelRequested = false;
  int _done = 0;
  int _total = 0;

  @override
  void initState() {
    super.initState();
    _loadPack();
  }

  Future<void> _loadPack() async {
    final pack = await OfflineDbService.instance.getOfflineRegion(_packName);
    if (mounted) setState(() => _pack = pack);
  }

  DateTime? _expiry(Map<String, dynamic> pack) {
    final at = (pack['downloaded_at'] as int?) ?? 0;
    final days = (pack['valid_days'] as int?) ?? 30;
    if (at == 0) return null;
    return DateTime.fromMillisecondsSinceEpoch(
        at + days * Duration.millisecondsPerDay);
  }

  Future<void> _startDownload(int validDays) async {
    if (_isDownloading) return;
    setState(() {
      _isDownloading = true;
      _cancelRequested = false;
      _done = 0;
      _total = OfflineTileDownloader.regionTileCount(
        minLat: OfflineTileDownloader.nepalMinLat,
        maxLat: OfflineTileDownloader.nepalMaxLat,
        minLng: OfflineTileDownloader.nepalMinLng,
        maxLng: OfflineTileDownloader.nepalMaxLng,
        minZoom: 8,
        maxZoom: 12,
      );
    });

    await OfflineTileDownloader.downloadNepalMap(
      validDays: validDays,
      onProgress: (done, total) {
        if (mounted) {
          setState(() {
            _done = done;
            _total = total;
          });
        }
      },
      isCancelled: () => _cancelRequested,
    );

    if (!mounted) return;
    if (_cancelRequested) {
      setState(() => _isDownloading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Download cancelled. Downloaded tiles are kept.')),
      );
    } else {
      setState(() => _isDownloading = false);
      await _loadPack();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Nepal offline map downloaded ($validDays days validity)'),
          backgroundColor: AppTheme.successColor,
        ),
      );
    }
  }

  Future<void> _updateMap() async {
    if (_pack == null || _isDownloading) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Update offline map?'),
        content: const Text(
          'The old map will be replaced and downloaded again with a fresh validity period.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Update'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final db = OfflineDbService.instance;
    final validDays = (_pack!['valid_days'] as int?) ?? 30;
    await db.clearTilesInRegion(
      minLat: OfflineTileDownloader.nepalMinLat,
      maxLat: OfflineTileDownloader.nepalMaxLat,
      minLng: OfflineTileDownloader.nepalMinLng,
      maxLng: OfflineTileDownloader.nepalMaxLng,
      minZoom: 8,
      maxZoom: 12,
    );
    await db.deleteOfflineRegion(_packName);
    await _loadPack();
    await _startDownload(validDays);
  }

  Future<void> _deleteMap() async {
    if (_pack == null || _isDownloading) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete offline map?'),
        content: const Text(
          'All downloaded Nepal tiles will be removed and storage freed. The map will keep working online.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final db = OfflineDbService.instance;
    await db.clearTilesInRegion(
      minLat: OfflineTileDownloader.nepalMinLat,
      maxLat: OfflineTileDownloader.nepalMaxLat,
      minLng: OfflineTileDownloader.nepalMinLng,
      maxLng: OfflineTileDownloader.nepalMaxLng,
      minZoom: 8,
      maxZoom: 12,
    );
    await db.deleteOfflineRegion(_packName);
    await _loadPack();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Offline map deleted')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        title: const Text('Offline Maps'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Download the map so it works without internet — perfect for trekking and remote areas of Nepal. '
            'Valid for a limited period (30/60 days); after expiry you can update (re-download) it.',
            style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
          ),
          const SizedBox(height: 16),
          _buildNepalCard(),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildNepalCard() {
    final pack = _pack;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.map_outlined,
                    color: AppTheme.primaryColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Nepal Full Map',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    Text(
                      _isDownloading
                          ? 'Downloading…'
                          : (pack == null
                              ? 'Not downloaded · zoom 8-12 · ~6500 tiles'
                              : '${pack['tile_count'] ?? 0} tiles cached'),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          if (_isDownloading) ...[
            LinearProgressIndicator(
              value: _total > 0 ? _done / _total : null,
              backgroundColor: AppTheme.primaryColor.withOpacity(0.15),
              color: AppTheme.primaryColor,
            ),
            const SizedBox(height: 8),
            Text(
              '$_done / $_total tiles',
              style: const TextStyle(
                  fontSize: 12, color: AppTheme.textSecondary),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => setState(() => _cancelRequested = true),
                icon: const Icon(Icons.stop_circle_outlined),
                label: const Text('Cancel Download'),
              ),
            ),
          ] else if (pack == null) ...[
            const Text(
              'Whole Nepal works offline: cities, highways, trekking regions. '
              'Valid for 30 or 60 days after download.',
              style: TextStyle(fontSize: 12, color: AppTheme.textSecondary),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => _startDownload(30),
                    icon: const Icon(Icons.download, size: 18),
                    label: const Text('30 days'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => _startDownload(60),
                    icon: const Icon(Icons.download, size: 18),
                    label: const Text('60 days'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppTheme.secondaryColor,
                    ),
                  ),
                ),
              ],
            ),
          ] else ...[
            _buildStatusRow(pack),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _updateMap,
                    icon: const Icon(Icons.refresh, size: 18),
                    label: const Text('Update Map'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _deleteMap,
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Delete'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.errorColor,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildStatusRow(Map<String, dynamic> pack) {
    final expiry = _expiry(pack);
    final expired = expiry != null && expiry.isBefore(DateTime.now());
    final downloadedAt = (pack['downloaded_at'] as int?) ?? 0;
    final days = (pack['valid_days'] as int?) ?? 30;

    String dateText(int ms) {
      if (ms == 0) return '-';
      final dt = DateTime.fromMillisecondsSinceEpoch(ms);
      return '${dt.year}-${dt.month.toString().padLeft(2, '0')}-${dt.day.toString().padLeft(2, '0')}';
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              expired ? Icons.error_outline : Icons.check_circle_outline,
              size: 16,
              color: expired ? AppTheme.errorColor : AppTheme.successColor,
            ),
            const SizedBox(width: 6),
            Text(
              expired ? 'Map expired' : 'Map ready',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: expired ? AppTheme.errorColor : AppTheme.successColor,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          'Downloaded: ${dateText(downloadedAt)} · Valid: $days days · Expires: ${expiry != null ? dateText(expiry.millisecondsSinceEpoch) : '-'}',
          style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
        ),
        if (expired)
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'The map is out of date — update it to refresh roads and places.',
              style: TextStyle(fontSize: 12, color: AppTheme.errorColor),
            ),
          ),
      ],
    );
  }
}
