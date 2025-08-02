<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index()
    {
        return Auth::user()->favorites()->pluck('feature_name');
    }

    public function store(Request $request)
    {
        $request->validate(['feature.name' => 'required|string']);
        Auth::user()->favorites()->firstOrCreate(['feature_name' => $request->input('feature.name')]);
        return response()->json(['message' => 'Favorited']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['feature.name' => 'required|string']);
        Auth::user()->favorites()->where('feature_name', $request->input('feature.name'))->delete();
        return response()->json(['message' => 'Unfavorited']);
    }
}
