<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET all modifier groups + options for a menu item
    | GET /shop/menu/{menuItemId}/modifiers
    |--------------------------------------------------------------------------
    */
    public function index(Request $request, int $menuItemId)
    {
        $restaurant = $request->user()->restaurant;
        $menuItem   = MenuItem::where('id', $menuItemId)
            ->where('restaurant_id', $restaurant->id)
            ->with(['modifierGroups.options'])
            ->firstOrFail();

        return response()->json($menuItem->modifierGroups);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE a modifier group with its options
    | POST /shop/menu/{menuItemId}/modifiers
    |
    | Body:
    | {
    |   "name": "Choose Soup Base",
    |   "required": true,
    |   "min_selections": 1,
    |   "max_selections": 1,
    |   "sort_order": 0,
    |   "options": [
    |     { "name": "Tom Yum",      "additional_price": 0 },
    |     { "name": "Milk Broth",   "additional_price": 0 },
    |     { "name": "Clear Broth",  "additional_price": 0 }
    |   ]
    | }
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, int $menuItemId)
    {
        $request->validate([
            'name'                       => 'required|string|max:100',
            'required'                   => 'boolean',
            'min_selections'             => 'integer|min:0',
            'max_selections'             => 'integer|min:1',
            'sort_order'                 => 'integer',
            'options'                    => 'required|array|min:1',
            'options.*.name'             => 'required|string|max:100',
            'options.*.additional_price' => 'numeric|min:0',
            'options.*.sort_order'       => 'integer',
        ]);

        $restaurant = $request->user()->restaurant;
        $menuItem   = MenuItem::where('id', $menuItemId)
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $group = ModifierGroup::create([
                'menu_item_id'   => $menuItem->id,
                'name'           => $request->name,
                'required'       => $request->boolean('required', false),
                'min_selections' => $request->integer('min_selections', 0),
                'max_selections' => $request->integer('max_selections', 1),
                'sort_order'     => $request->integer('sort_order', 0),
            ]);

            foreach ($request->options as $i => $opt) {
                ModifierOption::create([
                    'modifier_group_id' => $group->id,
                    'name'              => $opt['name'],
                    'additional_price'  => $opt['additional_price'] ?? 0,
                    'is_available'      => true,
                    'sort_order'        => $opt['sort_order'] ?? $i,
                ]);
            }

            DB::commit();
            return response()->json($group->load('options'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed', 'error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE a modifier group (name, required, min/max)
    | PUT /shop/menu/modifiers/{groupId}
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, int $groupId)
    {
        $request->validate([
            'name'           => 'sometimes|string|max:100',
            'required'       => 'sometimes|boolean',
            'min_selections' => 'sometimes|integer|min:0',
            'max_selections' => 'sometimes|integer|min:1',
            'sort_order'     => 'sometimes|integer',
        ]);

        $restaurant = $request->user()->restaurant;
        $group      = ModifierGroup::whereHas('menuItem', function ($q) use ($restaurant) {
            $q->where('restaurant_id', $restaurant->id);
        })->findOrFail($groupId);

        $group->update($request->only([
            'name', 'required', 'min_selections', 'max_selections', 'sort_order'
        ]));

        return response()->json($group->load('options'));
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE a modifier group (cascades to options)
    | DELETE /shop/menu/modifiers/{groupId}
    |--------------------------------------------------------------------------
    */
    public function destroy(Request $request, int $groupId)
    {
        $restaurant = $request->user()->restaurant;
        $group      = ModifierGroup::whereHas('menuItem', function ($q) use ($restaurant) {
            $q->where('restaurant_id', $restaurant->id);
        })->findOrFail($groupId);

        $group->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | ADD an option to an existing group
    | POST /shop/menu/modifiers/{groupId}/options
    |--------------------------------------------------------------------------
    */
    public function addOption(Request $request, int $groupId)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'additional_price' => 'numeric|min:0',
            'sort_order'       => 'integer',
        ]);

        $restaurant = $request->user()->restaurant;
        $group      = ModifierGroup::whereHas('menuItem', function ($q) use ($restaurant) {
            $q->where('restaurant_id', $restaurant->id);
        })->findOrFail($groupId);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name'              => $request->name,
            'additional_price'  => $request->additional_price ?? 0,
            'is_available'      => true,
            'sort_order'        => $request->integer('sort_order', 0),
        ]);

        return response()->json($option, 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE a single option
    | PUT /shop/menu/modifiers/options/{optionId}
    |--------------------------------------------------------------------------
    */
    public function updateOption(Request $request, int $optionId)
    {
        $request->validate([
            'name'             => 'sometimes|string|max:100',
            'additional_price' => 'sometimes|numeric|min:0',
            'is_available'     => 'sometimes|boolean',
            'sort_order'       => 'sometimes|integer',
        ]);

        $restaurant = $request->user()->restaurant;
        $option     = ModifierOption::whereHas('group.menuItem', function ($q) use ($restaurant) {
            $q->where('restaurant_id', $restaurant->id);
        })->findOrFail($optionId);

        $option->update($request->only([
            'name', 'additional_price', 'is_available', 'sort_order'
        ]));

        return response()->json($option);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE a single option
    | DELETE /shop/menu/modifiers/options/{optionId}
    |--------------------------------------------------------------------------
    */
    public function destroyOption(Request $request, int $optionId)
    {
        $restaurant = $request->user()->restaurant;
        $option     = ModifierOption::whereHas('group.menuItem', function ($q) use ($restaurant) {
            $q->where('restaurant_id', $restaurant->id);
        })->findOrFail($optionId);

        $option->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
