// Deterministic coverage for the canonical displayable-place pipeline:
//
//   merge (nearby-combined + bbox + Nepal-wide)
//     → dedupe by placeIdentityKey / 100 m DB-vs-OSM rule
//     → drop category == "All"
//     → displayablePlaces  (single source for BOTH Nearby and the map)
//
// No widgets, no network, no device: pure data-path assertions plus a
// memoization check against PlaceProvider.

import 'package:flutter_test/flutter_test.dart';
import 'package:nepal_smart_travel/providers/place_provider.dart';

PlaceModel _place({
  required dynamic id,
  required String name,
  String? category = 'Hotels',
  double lat = 27.717,
  double lng = 85.324,
  String source = 'seed',
  double? distanceKm,
}) =>
    PlaceModel(
      id: id,
      name: name,
      category: category,
      latitude: lat,
      longitude: lng,
      source: source,
      distanceKm: distanceKm,
    );

/// Kathmandu Valley window used by the viewport assertions.
const _vpMinLat = 27.60, _vpMaxLat = 27.80, _vpMinLng = 85.20, _vpMaxLng = 85.45;

List<PlaceModel> _cull(List<PlaceModel> places) => placesWithinViewport(
      places,
      minLat: _vpMinLat,
      maxLat: _vpMaxLat,
      minLng: _vpMinLng,
      maxLng: _vpMaxLng,
    );

void _report(String scenario, {required int raw, required int displayable}) {
  // ignore: avoid_print
  print('  [$scenario] raw=$raw displayable=$displayable');
}

void main() {
  group('isDisplayablePlace — the one `category == "All"` check', () {
    test('drops every form of the placeholder category', () {
      final allVariants = ['All', 'all', ' ALL ', 'all'];
      for (final category in allVariants) {
        expect(
          isDisplayablePlace(_place(id: 1, name: 'x', category: category)),
          isFalse,
          reason: 'category "$category" must not be displayable',
        );
      }
    });

    test('keeps every real category (null, empty, and normal values)', () {
      for (final category in [null, '', 'Hotels', 'Restaurant', 'Other', 'Trekking Routes']) {
        expect(
          isDisplayablePlace(_place(id: 1, name: 'x', category: category)),
          isTrue,
          reason: 'category "$category" must be displayable',
        );
      }
    });
  });

  group('placeIdentityKey', () {
    test('collapses the two DB id formats to one key', () {
      expect(placeIdentityKey(_place(id: 35, name: 'A')), 'db:35'); // bbox / all
      expect(placeIdentityKey(_place(id: '35', name: 'A')), 'db:35');
      expect(placeIdentityKey(_place(id: 'admin_35', name: 'A')), 'db:35'); // combined
    });

    test('keeps OSM nodes in their own namespace', () {
      expect(placeIdentityKey(_place(id: 'osm_node/9315343446', name: 'A')), 'osm:node/9315343446');
      expect(placeIdentityKey(_place(id: 'osm_way/123', name: 'A')), 'osm:way/123');
      expect(placeIdentityKey(_place(id: 35, name: 'A')),
          isNot(placeIdentityKey(_place(id: 'osm_node/35', name: 'A'))));
    });
  });

  group('buildDisplayablePlaces', () {
    test('1. payload of only DB `All` records yields nothing displayable', () {
      final dbAll = [
        for (var id = 1; id <= 5; id++)
          _place(id: id, name: 'Seed $id', category: 'All'),
      ];
      final nearbyAll = [
        _place(id: 'admin_1', name: 'Seed 1', category: 'All'),
        _place(id: 'osm_node/42', name: 'OSM 42', category: 'All'),
      ];

      final displayable = buildDisplayablePlaces(
        nearbyPlaces: nearbyAll,
        viewportPlaces: dbAll,
        nepalPlaces: dbAll,
      );

      _report('only-DB-All', raw: nearbyAll.length + dbAll.length, displayable: displayable.length);
      expect(displayable, isEmpty);
      expect(displayable.every((p) => !isDisplayablePlace(p)), isTrue);
    });

    test('2. DB `All` plus one categorized DB row → exactly that row', () {
      final dbAll = [
        for (var id = 1; id <= 191; id++)
          _place(id: id, name: 'Seed $id', category: 'All'),
      ];
      final other = _place(
        id: 193,
        name: "Rajendra's House",
        category: 'Other',
        source: 'user_submitted',
      );

      final displayable = buildDisplayablePlaces(
        nearbyPlaces: [],
        viewportPlaces: dbAll,
        nepalPlaces: [...dbAll, other],
      );

      _report('DB-All+1-categorized', raw: dbAll.length + 1, displayable: displayable.length);
      expect(displayable, hasLength(1));
      expect(displayable.single.name, "Rajendra's House");
      expect(displayable.single.category, 'Other');
    });

    test('3. DB `All` plus categorized OSM place → OSM place survives (map input non-empty)', () {
      final dbAll = [
        for (var id = 1; id <= 191; id++)
          _place(id: id, name: 'Seed $id', category: 'All'),
      ];
      final osmCafe = _place(
        id: 'osm_node/111',
        name: 'Thamel Coffee House',
        category: 'Cafe',
        source: 'openstreetmap',
        lat: 27.715,
        lng: 85.312,
      );

      final displayable = buildDisplayablePlaces(
        nearbyPlaces: [osmCafe],
        viewportPlaces: dbAll,
        nepalPlaces: dbAll,
      );
      final forMap = _cull(displayable);

      _report('DB-All+OSM-categorized', raw: dbAll.length + 1, displayable: displayable.length);
      expect(displayable, hasLength(1));
      expect(displayable.single.id, 'osm_node/111');
      // Edge case: after excluding `All` the map must not be empty while a
      // categorized OSM place exists.
      expect(forMap, isNotEmpty);
      expect(forMap.single.id, 'osm_node/111');
    });

    test('4. same place as DB row and live OSM node → one entry, DB row wins', () {
      const name = 'Pashupatinath Temple';
      final dbCombined =
          _place(id: 'admin_193', name: name, category: 'Temples', lat: 27.7108, lng: 85.3490);
      final dbBbox =
          _place(id: 193, name: name, category: 'Temples', lat: 27.7108, lng: 85.3490);
      // ~55 m away (0.0005°) — inside the server's 100 m cross-source window.
      final osmCopy = _place(
        id: 'osm_node/9315343446',
        name: name,
        category: 'Place of Worship',
        source: 'openstreetmap',
        lat: 27.7108 + 0.0005,
        lng: 85.3490,
      );
      // Same name but ~1.1 km away — a genuinely different place.
      final osmOther = _place(
        id: 'osm_node/9315343447',
        name: name,
        category: 'Place of Worship',
        source: 'openstreetmap',
        lat: 27.7108 + 0.01,
        lng: 85.3490,
      );

      final merged = buildDisplayablePlaces(
        nearbyPlaces: [dbCombined, osmCopy, osmOther],
        viewportPlaces: [dbBbox],
        nepalPlaces: [dbBbox],
      );

      _report('DB+OSM-duplicate', raw: 4, displayable: merged.length);
      expect(merged, hasLength(2), reason: 'only the ≤100 m copy collapses');
      expect(
        merged.map(placeIdentityKey),
        containsAll(['db:193', 'osm:node/9315343447']),
      );
      expect(merged.map((p) => p.id), isNot(contains('osm_node/9315343446')));
      // The surviving DB row is the combined-payload one (nearest source wins).
      expect(
        merged.firstWhere((p) => placeIdentityKey(p) == 'db:193').id,
        'admin_193',
      );
    });

    test('5. empty nearby-combined still surfaces DB categorized rows', () {
      final dbCategorized = [
        _place(id: 10, name: 'Swayambhunath', category: 'Temples'),
        _place(id: 11, name: 'Hotel Yak & Yeti', category: 'Hotels'),
      ];

      final displayable = buildDisplayablePlaces(
        nearbyPlaces: [],
        viewportPlaces: dbCategorized,
        nepalPlaces: dbCategorized,
      );

      _report('empty-nearby', raw: dbCategorized.length, displayable: displayable.length);
      expect(displayable, hasLength(2));
      expect(displayable.map((p) => p.id), containsAll([10, 11]));
    });

    test('6. categories other than `All` are all retained', () {
      final categories = ['Hotels', 'Restaurant', 'Other', 'Trekking Routes', 'Hospitals', null];
      final places = [
        for (var i = 0; i < categories.length; i++)
          _place(id: i, name: 'Place $i', category: categories[i]),
        _place(id: 99, name: 'Placeholder', category: 'All'),
      ];

      final displayable = buildDisplayablePlaces(
        nearbyPlaces: places,
        viewportPlaces: places,
        nepalPlaces: places,
      );

      _report('categories-preserved', raw: places.length, displayable: displayable.length);
      expect(displayable, hasLength(categories.length));
      expect(displayable.map((p) => p.category).toSet(), {...categories});
      expect(displayable.any((p) => !isDisplayablePlace(p)), isFalse);
    });

    test('trust order: nearby-combined row wins a key tie over bbox/Nepal-wide', () {
      final combined = _place(
        id: 'admin_55',
        name: 'Boudhanath Stupa',
        category: 'Temples',
        distanceKm: 0.4,
      );
      final bbox = _place(id: 55, name: 'Boudhanath Stupa', category: 'Temples');

      final merged = buildDisplayablePlaces(
        nearbyPlaces: [combined],
        viewportPlaces: [bbox],
        nepalPlaces: [bbox],
      );

      expect(merged, hasLength(1));
      expect(merged.single.id, 'admin_55', reason: 'distance/images from combined payload are kept');
    });
  });

  group('viewport helpers', () {
    test('isWithinViewport applies the 0.15° cushion', () {
      final inside = _place(id: 1, name: 'in', lat: 27.75, lng: 85.32);
      final justOutside = _place(id: 2, name: 'edge', lat: 27.60 - 0.05, lng: 85.32);
      final farOutside = _place(id: 3, name: 'far', lat: 26.0, lng: 85.32);

      expect(isWithinViewport(inside, minLat: _vpMinLat, maxLat: _vpMaxLat,
          minLng: _vpMinLng, maxLng: _vpMaxLng), isTrue);
      expect(isWithinViewport(justOutside, minLat: _vpMinLat, maxLat: _vpMaxLat,
          minLng: _vpMinLng, maxLng: _vpMaxLng), isTrue, reason: 'margin keeps edge pins warm');
      expect(isWithinViewport(farOutside, minLat: _vpMinLat, maxLat: _vpMaxLat,
          minLng: _vpMinLng, maxLng: _vpMaxLng), isFalse);
    });

    test('placesWithinViewport keeps order and drops out-of-frame rows', () {
      final places = [
        _place(id: 1, name: 'a', lat: 27.71, lng: 85.32),
        _place(id: 2, name: 'b', lat: 26.50, lng: 85.32),
        _place(id: 3, name: 'c', lat: 27.75, lng: 85.35),
      ];

      final culled = _cull(places);
      expect(culled.map((p) => p.id), [1, 3]);
    });
  });

  group('PlaceProvider memoization', () {
    test('7. rebuilds only when a source changes, never between reads', () {
      final provider = PlaceProvider();
      provider.setCachedPlaces([
        _place(id: 'admin_7', name: 'Nearby One', category: 'Hotels', distanceKm: 0.2),
        _place(id: 'osm_node/9', name: 'Nearby OSM', category: 'Cafe'),
      ]);
      provider.restoreViewportPlaces(
        [_place(id: 7, name: 'BBox One', category: 'Temples')],
        minLat: _vpMinLat, maxLat: _vpMaxLat, minLng: _vpMinLng, maxLng: _vpMaxLng,
      );

      final first = provider.displayablePlaces;
      final keys = provider.viewportPlaceKeys;

      // Re-reads on the same data are memoized — camera movement and rebuilds
      // must not re-run the merge.
      expect(identical(provider.displayablePlaces, first), isTrue);
      expect(identical(provider.viewportPlaceKeys, keys), isTrue);

      final rawCount = provider.places.length +
          provider.viewportPlaces.length +
          provider.nepalPlaces.length;
      _report('memoized-read', raw: rawCount, displayable: first.length);
      // admin_7 and 7 are the same DB row; OSM node is separate.
      expect(first, hasLength(2));

      // Any source mutation invalidates the memo exactly once.
      provider.setViewportPlacesDirect([_place(id: 7, name: 'BBox One', category: 'Temples')]);
      final second = provider.displayablePlaces;
      expect(identical(second, first), isFalse);
      expect(second, hasLength(2));

      provider.setCachedPlaces([
        _place(id: 'admin_7', name: 'Nearby One', category: 'Hotels'),
        _place(id: 'admin_8', name: 'Nearby Two', category: 'Hotels'),
      ]);
      final third = provider.displayablePlaces;
      expect(identical(third, second), isFalse);
      expect(
        third.map(placeIdentityKey).toSet(),
        {'db:7', 'db:8'},
        reason: 'admin_7 and bbox 7 are the same row; admin_8 is new',
      );

      // Every entry the provider hands to either consumer is displayable.
      expect(third.every((p) => isDisplayablePlace(p)), isTrue);
    });
  });
}
