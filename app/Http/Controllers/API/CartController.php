<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemModifier;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CartController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW CART  (returns items with their modifier selections)
    |--------------------------------------------------------------------------
    */
    public function viewCart(Request $request)
    {
        $cart = Cart::with([
             'items.menuItem.restaurant:id,name,latitude,longitude,image',
            'items.menuItem.modifierGroups.options',
            'items.modifiers.option.group',
        ])->where('user_id', $request->user()->id)->first();

        return response()->json(['cart' => $cart]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADD TO CART  (now validates and saves modifier selections)
    |--------------------------------------------------------------------------
    |
    | Body:
    | {
    |   "menu_item_id": 5,
    |   "quantity": 1,
    |   "selected_modifier_option_ids": [12, 17]   // optional
    | }
    |--------------------------------------------------------------------------
    */
    public function addToCart(Request $request)
    {
        $request->validate([
            'menu_item_id'                  => 'required|exists:menu_items,id',
            'quantity'                      => 'integer|min:1',
            'selected_modifier_option_ids'  => 'nullable|array',
            'selected_modifier_option_ids.*'=> 'integer|exists:modifier_options,id',
        ]);

        $user     = $request->user();
        $menuItem = MenuItem::with('modifierGroups.options')->findOrFail($request->menu_item_id);
        $qty      = $request->integer('quantity', 1);
        $selectedIds = $request->input('selected_modifier_option_ids', []);

        // ── Validate required modifier groups ─────────────────────────────
        foreach ($menuItem->modifierGroups as $group) {
            $pickedInGroup = collect($selectedIds)->filter(function ($optId) use ($group) {
                return $group->options->pluck('id')->contains($optId);
            });

            if ($group->required && $pickedInGroup->count() < $group->min_selections) {
                return response()->json([
                    'message' => "Please select at least {$group->min_selections} option(s) for \"{$group->name}\"",
                ], 422);
            }

            if ($pickedInGroup->count() > $group->max_selections) {
                return response()->json([
                    'message' => "You can only select up to {$group->max_selections} option(s) for \"{$group->name}\"",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            // ── Find existing cart item with SAME modifiers ────────────────
            // If the same item is added twice with different modifiers,
            // they become separate cart items (e.g. different soup base)
            $existingItem = null;

            $candidateItems = CartItem::where('cart_id', $cart->id)
                ->where('menu_item_id', $menuItem->id)
                ->with('modifiers')
                ->get();

            foreach ($candidateItems as $candidate) {
                $existingIds = $candidate->modifiers->pluck('modifier_option_id')->sort()->values()->toArray();
                $newIds      = collect($selectedIds)->sort()->values()->toArray();

                if ($existingIds === $newIds) {
                    $existingItem = $candidate;
                    break;
                }
            }

            if ($existingItem) {
                $existingItem->increment('quantity', $qty);
            } else {
                $existingItem = CartItem::create([
                    'cart_id'      => $cart->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $qty,
                ]);

                foreach ($selectedIds as $optionId) {
                    CartItemModifier::create([
                        'cart_item_id'       => $existingItem->id,
                        'modifier_option_id' => $optionId,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Added to cart']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed', 'error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DECREASE QUANTITY
    |--------------------------------------------------------------------------
    */
    public function decreaseQuantity(Request $request)
    {
        $request->validate([
            'menu_item_id'  => 'required|exists:menu_items,id',
            'cart_item_id'  => 'nullable|integer', // optional: target specific cart item
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) return response()->json(['message' => 'Cart not found'], 404);

        $query = CartItem::where('cart_id', $cart->id)
            ->where('menu_item_id', $request->menu_item_id);

        if ($request->cart_item_id) {
            $query->where('id', $request->cart_item_id);
        }

        $item = $query->with('modifiers')->first();
        if (!$item) return response()->json(['message' => 'Item not found'], 404);

        if ($item->quantity <= 1) {
            $item->modifiers()->delete();
            $item->delete();
        } else {
            $item->decrement('quantity');
        }

        return response()->json(['message' => 'Decreased']);
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */
    public function removeFromCart(Request $request)
    {
        $request->validate(['cart_item_id' => 'required|integer']);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) return response()->json(['message' => 'Cart not found'], 404);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('id', $request->cart_item_id)
            ->with('modifiers')
            ->first();

        if (!$item) return response()->json(['message' => 'Item not found'], 404);

        $item->modifiers()->delete();
        $item->delete();

        return response()->json(['message' => 'Removed']);
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CART
    |--------------------------------------------------------------------------
    */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->with('items.modifiers')
            ->first();

        if ($cart) {
            foreach ($cart->items as $item) {
                $item->modifiers()->delete();
            }
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Cart cleared']);
    }
}
