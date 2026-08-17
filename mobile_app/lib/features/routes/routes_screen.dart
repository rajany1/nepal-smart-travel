import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import "../../core/services/localization_service.dart";
import '../../config/themes/app_theme.dart';
import '../../core/models/route_model.dart';
import '../../providers/route_provider.dart';
import 'route_detail_screen.dart';

/// Curated routes (trekking + city itineraries) with type/difficulty filters.
class RoutesScreen extends StatefulWidget {
  const RoutesScreen({super.key});

  @override
  State<RoutesScreen> createState() => _RoutesScreenState();
}

class _RoutesScreenState extends State<RoutesScreen> {
  String? _typeFilter;
  String? _difficultyFilter;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RouteProvider>().fetchRoutes();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RouteProvider>();
    final routes = provider.routes;

    return Scaffold(
      appBar: AppBar(title: Text(context.t('Routes & Treks'))),
      body: Column(
        children: [
          _buildFilterBar(),
          Expanded(
            child: provider.isLoading && routes.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : routes.isEmpty
                    ? _buildEmpty()
                    : RefreshIndicator(
                        onRefresh: () => provider.fetchRoutes(
                          type: _typeFilter,
                          difficulty: _difficultyFilter,
                        ),
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                          itemCount: routes.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 12),
                          itemBuilder: (context, i) => _RouteCard(route: routes[i]),
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterBar() {
    return SizedBox(
      height: 46,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        children: [
          _FilterChipWidget(
            label: context.t('All'),
            selected: _typeFilter == null && _difficultyFilter == null,
            onTap: () => _applyFilters(type: null, difficulty: null),
          ),
          _FilterChipWidget(
            label: context.t('Trekking'),
            selected: _typeFilter == 'trekking',
            onTap: () => _applyFilters(type: 'trekking', difficulty: null),
          ),
          _FilterChipWidget(
            label: context.t('Itineraries'),
            selected: _typeFilter == 'itinerary',
            onTap: () => _applyFilters(type: 'itinerary', difficulty: null),
          ),
          _FilterChipWidget(
            label: context.t('Easy'),
            selected: _difficultyFilter == 'easy',
            onTap: () => _applyFilters(
                type: null, difficulty: _difficultyFilter == 'easy' ? null : 'easy'),
          ),
          _FilterChipWidget(
            label: context.t('Moderate'),
            selected: _difficultyFilter == 'moderate',
            onTap: () => _applyFilters(
                type: null,
                difficulty: _difficultyFilter == 'moderate' ? null : 'moderate'),
          ),
          _FilterChipWidget(
            label: context.t('Challenging'),
            selected: _difficultyFilter == 'challenging',
            onTap: () => _applyFilters(
                type: null,
                difficulty:
                    _difficultyFilter == 'challenging' ? null : 'challenging'),
          ),
          _FilterChipWidget(
            label: context.t('Hard'),
            selected: _difficultyFilter == 'hard',
            onTap: () => _applyFilters(
                type: null, difficulty: _difficultyFilter == 'hard' ? null : 'hard'),
          ),
        ],
      ),
    );
  }

  void _applyFilters({String? type, String? difficulty}) {
    setState(() {
      _typeFilter = type;
      _difficultyFilter = difficulty;
    });
    context.read<RouteProvider>().fetchRoutes(
          type: type,
          difficulty: difficulty,
        );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.route, size: 56, color: AppTheme.textSecondary),
          const SizedBox(height: 12),
          Text(context.t('No routes found')),
          const SizedBox(height: 4),
          Text(
            context.t('Routes are being curated. Check back soon!'),
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
          ),
        ],
      ),
    );
  }
}

class _FilterChipWidget extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChipWidget({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? AppTheme.primaryColor : AppTheme.surfaceColor,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: selected ? AppTheme.primaryColor : AppTheme.dividerColor,
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: AppTheme.textSm,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : AppTheme.textSecondary,
            ),
          ),
        ),
      ),
    );
  }
}

class _RouteCard extends StatelessWidget {
  final CuratedRouteModel route;

  const _RouteCard({required this.route});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => RouteDetailScreen(routeId: route.id)),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppTheme.dividerColor),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 110,
              width: double.infinity,
              decoration: BoxDecoration(
                gradient: route.isTrekking
                    ? const LinearGradient(
                        colors: [Color(0xFFB45309), Color(0xFF78350F)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : const LinearGradient(
                        colors: [AppTheme.primaryColor, AppTheme.primaryLight],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
              ),
              child: Stack(
                children: [
                  Center(
                    child: Icon(
                      route.isTrekking ? Icons.hiking : Icons.route,
                      size: 44,
                      color: Colors.white.withOpacity(0.9),
                    ),
                  ),
                  Positioned(
                    top: 10,
                    left: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.95),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${route.durationDays} ${context.t('days')}',
                        style: const TextStyle(
                          fontSize: AppTheme.textSm,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    top: 10,
                    right: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.95),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        route.isTrekking ? context.t('Trekking') : context.t('Itinerary'),
                        style: TextStyle(
                          fontSize: AppTheme.textSm,
                          fontWeight: FontWeight.bold,
                          color: route.isTrekking ? const Color(0xFFB45309) : AppTheme.primaryColor,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    route.title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      if (route.difficulty != null)
                        _InfoBadge(
                          icon: Icons.signal_cellular_alt,
                          label: route.difficultyLabel,
                        ),
                      if (route.totalDistanceKm != null)
                        _InfoBadge(
                          icon: Icons.straighten,
                          label: '${route.totalDistanceKm!.round()} km',
                        ),
                      if (route.maxAltitudeM != null)
                        _InfoBadge(
                          icon: Icons.terrain,
                          label: '${route.maxAltitudeM} m',
                        ),
                      if (route.startingPoint != null)
                        _InfoBadge(
                          icon: Icons.flag,
                          label: route.startingPoint!,
                        ),
                    ],
                  ),
                  if (route.description != null && route.description!.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      route.description!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppTheme.textSecondary,
                        fontSize: AppTheme.textSm,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoBadge extends StatelessWidget {
  final IconData icon;
  final String label;

  const _InfoBadge({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: AppTheme.primaryLight.withOpacity(0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: AppTheme.primaryColor),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: AppTheme.textXs,
              fontWeight: FontWeight.w600,
              color: AppTheme.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
