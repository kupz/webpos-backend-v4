<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    

    // MIDDLEWARE: auth:api
    // ROUTE: GET: api/products
    public function index(Request $request){
        $products = Product::where('user_id', $request->user()->id)->get();
        $products->map(function ($product){
            $product->stock();
        });
        return response()->json(['ok' => true, 'data' => $products], 200);
    }

    // MIDDLEWARE: auth:api
    // ROUTE: POST api/products
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'sku' => [
                'sometimes', 'max:200', 'string',
                Rule::unique('products')->where(fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ],
            'name' => 'required|max:200|string',
            'description' => 'required|max:200|string',
            'barcode' => 'sometimes|max:200|string|unique:products',
            'price' => 'required|max:200|string',
            'image' => 'sometimes|image|max:8192'
        ]);
        if($validator->fails()){
            return response()->json(['ok' => false, 'message' => "Request didn't pass the validation.", 'errors' => $validator->errors()], 400);
        }
        else{
            $validated = $validator->safe()->except(['image']);
            if(!empty($request->file('image'))){
                $validated['extension'] = $request->file('image')->getClientOriginalExtension();
            }
            $product = $request->user()->products()->create($validated);
            if(!empty($request->file('image'))){
                $request->file('image')->storeAs('public/uploads', $product->id . "." . $validated['extension']);
            }
            $request->user()->logs()->create([
                'table_name' => 'products',
                'object_id' => $product->id,
                'label' => 'product-store',
                'description' => "Product ($product->id) has been created!",
                'properties' => json_encode(array_merge($validated, ['user-agent' => $request->userAgent(), 'token' => $request->user()->token()->id])),
                'ip' => $request->ip()
            ]);
            return response()->json(["ok" => true, 'message' => "Product has been created!", 'data' => $product], 200);
        }
    }

    // MIDDLEWARE: auth:api
    // ROUTE: PATCH: api/products/{id}
    public function update(Request $request, Product $product){
        if($product->user_id === $request->user()->id){
            $validator = Validator::make($request->all(), [
                'sku' => [
                    "sometimes", "max:200", "string",
                    Rule::unique('products')->where(fn (Builder $query) => $query->where('user_id', $request->user()->id))->ignore($product->id)
                ],
                'name' => 'required|max:200|string',
                'description' => 'required|max:200|string',
                'barcode' => 'sometimes|max:200|string|unique:products,barcode,' . $product->id,
                'price' => 'required|max:200|string',
                'image' => 'nullable|image|max:8192'
            ]);
            if($validator->fails()){
                return response()->json(['ok' => false, 'message' => "Request didn't pass the validation.", 'errors' => $validator->errors()], 400);
            }
            else{
                $validated = $validator->safe()->except(['image']);
                $changes = [];
                if($request->file('image')){
                    $validated['extension'] = $request->file('image')->getClientOriginalExtension();
                    $request->file('image')->storeAs('public/uploads', $product->id . "." . $validated['extension']);
                }
                foreach($validated as $key => $value){
                    if($value != $product[$key]){
                        $changes[$key] = ["old" => $product[$key], "new" => $value];
                    }
                }
                $product->update($validated);
                $request->user()->logs()->create([
                    'table_name' => 'products',
                    'object_id' => $product->id,
                    'label' => 'product-update',
                    'description' => "Product ($product->id) has been updated!",
                    'properties' => json_encode(array_merge(["changes" => $changes], ['user-agent' => $request->userAgent(), 'token' => $request->user()->token()->id])),
                    'ip' => $request->ip()
                ]);
                return response()->json(['ok' => true, 'message' => 'Product has been updated!', 'data' => $product], 200);
            }
        }
        else{
            return response()->json(['ok' => false, 'message' => 'Unauthenticated!'], 401);
        }
    }

    public function top5Price(Request $request){
        $top5 = DB::table('products')
            ->join('product_transaction', 'products.id' , '=', 'product_transaction.product_id')
            ->join('transactions', 'transactions.id', '=', 'product_transaction.transaction_id')
            ->selectRaw("ABS(SUM(product_transaction.quantity * product_transaction.price)) as 'total_outbound_price', products.*")
            ->where('transactions.type', 1)
            ->where('transactions.void', false)
            ->where('transactions.user_id', $request->user()->id)
            ->groupBy('products.id')
            ->orderBy('total_outbound_price', 'DESC')
            ->limit(10)
            ->get();
        return response()->json(['data' => $top5, 'ok' => true]);
    }

    

    public function top5Quantity(Request $request){
        $top5 = DB::table('products')
            ->join('product_transaction', 'products.id' , '=', 'product_transaction.product_id')
            ->join('transactions', 'transactions.id', '=', 'product_transaction.transaction_id')
            ->selectRaw("ABS(SUM(product_transaction.quantity)) as 'total_outbound_quantity', products.*")
            ->where('transactions.void', false)
            ->where('transactions.user_id', $request->user()->id)
            ->groupBy('products.id')
            ->orderBy('total_outbound_quantity', 'DESC')
            ->limit(10)
            ->get();
        return response()->json(['data' => $top5, 'ok' => true]);
    }

    public function dailySales(Request $request){
        $sales = DB::table('products')
            ->join('product_transaction', 'products.id', '=', 'product_transaction.product_id')
            ->join('transactions', 'transactions.id', '=', 'product_transaction.transaction_id')
            ->selectRaw("SUM(ABS(product_transaction.quantity) * product_transaction.price) as total_sales, DATE_FORMAT(transactions.created_at, '%Y-%m-%d') transaction_date")
            ->whereRaw("transactions.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 15 DAY) AND NOW()")
            ->where('transactions.void', false)
            ->where('transactions.user_id', $request->user()->id)
            ->groupBy('transaction_date')
            ->orderBy('transaction_date', 'ASC')
            ->get();
        return response()->json(['data' => $sales, 'ok' => true]);

    }


    
    public function dailyQuantity(Request $request){
        $quantity = DB::table('products')
            ->join('product_transaction', 'products.id', '=', 'product_transaction.product_id')
            ->join('transactions', 'transactions.id', '=', 'product_transaction.transaction_id')
            ->selectRaw("SUM(ABS(product_transaction.quantity)) as total_quantity, DATE_FORMAT(transactions.created_at, '%Y-%m-%d') transaction_date")
            ->whereRaw("transactions.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 15 DAY) AND NOW()")
            ->where('transactions.void', false)
            ->where('transactions.user_id', $request->user()->id)
            ->groupBy('transaction_date')
            ->orderBy('transaction_date', 'ASC')
            ->get();
        return response()->json(['data' => $quantity, 'ok' => true]);

    }

}
