<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CuratedRoute;
use App\Models\Place;
use App\Models\PlaceCategories;
use App\Models\RewardOffer;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        $featured = Place::where('is_active', true)
            ->where('is_featured', true)
            ->with('category')
            ->orderBy('average_rating', 'desc')
            ->limit(6)
            ->get();

        if ($featured->isEmpty()) {
            $featured = Place::where('is_active', true)->with('category')
                ->orderBy('average_rating', 'desc')->limit(6)->get();
        }

        $offers = RewardOffer::with('business')->active()->limit(4)->get();
        $routes = CuratedRoute::active()->limit(3)->get();
        $placesCount = Place::where('is_active', true)->count();

        return view('web.home', compact('featured', 'offers', 'routes', 'placesCount'));
    }

    public function places(Request $request)
    {
        $query = Place::with('category')->where('is_active', true);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($w) => $w->where('name', 'like', "%$q%")->orWhere('district', 'like', "%$q%"));
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        $places = $query->orderBy('average_rating', 'desc')->paginate(24)->withQueryString();
        $categories = PlaceCategories::where('id', '!=', 1)->orderBy('name')->get();
        $districts = Place::where('is_active', true)->whereNotNull('district')->distinct()->pluck('district')->sort()->values();

        return view('web.places', compact('places', 'categories', 'districts'));
    }

    public function placeShow(Request $request, $id)
    {
        $place = Place::with(['category', 'images'])
            ->where('is_active', true)
            ->where(fn($q) => $q->where('id', $id)->orWhere('uuid', $id))
            ->first();

        if (!$place) abort(404);

        $reviews = $place->approvedReviews()->latest()->take(5)->get();

        $similar = Place::where('is_active', true)
            ->where('category_id', $place->category_id)
            ->where('id', '!=', $place->id)
            ->limit(4)
            ->get();

        return view('web.place_show', compact('place', 'reviews', 'similar'));
    }

    public function categoryPage(string $type)
    {
        $map = [
            'hotels' => 'Hotels',
            'restaurants' => 'Restaurants',
            'attractions' => 'Attractions',
            'cafes' => 'Cafe',
            'activities' => 'Activities',
        ];
        $name = $map[$type] ?? abort(404);

        $category = PlaceCategories::where('name', $name)->first();
        $places = Place::with('category')
            ->where('is_active', true)
            ->where('category_id', $category?->id)
            ->orderBy('average_rating', 'desc')
            ->paginate(24);

        $title = ucfirst($type);
        return view('web.places', compact('places', 'title'));
    }

    public function routes()
    {
        $routes = CuratedRoute::active()->orderBy('duration_days')->paginate(12);
        return view('web.routes', compact('routes'));
    }

    public function routeShow(CuratedRoute $route)
    {
        if (!$route->is_active) abort(404);
        $places = $route->waypointPlaces();
        return view('web.route_show', compact('route', 'places'));
    }

    public function offers()
    {
        $offers = RewardOffer::with('business')->active()
            ->orderBy('created_at', 'desc')
            ->paginate(24);
        return view('web.offers', compact('offers'));
    }
}
