<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;   
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

use App\Models\Transaction;
use App\Models\Product;

class TransactionController extends Controller
{
    // MIDDLEWARE - auth:api
    // ROUTE - GET: api/transactions
    public function index(Request $request){
        $transactions = $request->user()->transactions->reverse()->values();
        $transactions->map(function ($transaction)  {
            $transaction->products;
            $transaction->total();
        });
        return response()->json(['ok' => true, 'data' => $transactions], 200);
    }

    // MIDDLEWARE - auth:api
    // ROUTE - POST: api/transactions
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'cart' => 'array|required|min:1',
            'cart.*' => 'required|array',
            'type' => 'required|min:1|max:3', // 1 = Invoice, 2 = Receive, 3 = Pull-out
            'cart.*.product' => [
                'required',
                Rule::exists('products', 'id')->where(fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ],
            'cart.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max: 1000000000'
            ],
        ]);
        if($validator->fails()){
            return response()->json(['ok' => false, 'errors' => $validator->errors(), 'message' => "Request didn't pass the validation!"], 400);
        }
        else{
            $validated = $validator->validated();
            $transaction = Transaction::create(['user_id' => $request->user()->id, 'type' => $validated['type']]);
            foreach ($validated['cart'] as $item){
                $product = Product::where('id', $item['product'])->where('user_id', $request->user()->id)->first();
                $transaction->products()->attach($product->id, ['price' => $product->price, 'quantity' => $validated['type'] == 1 || $validated['type'] == 3 ? $item['quantity'] * -1 : $item['quantity']]);
            }
            $request->user()->logs()->create([
                'table_name' => 'transactions',
                'object_id' => $transaction->id,
                'label' => 'transaction-store',
                'description' => "Transaction ($transaction->id) has been created!",
                'properties' => json_encode(array_merge($validated, ['user-agent' => $request->userAgent(), 'token' => $request->user()->token()->id])),
                'ip' => $request->ip()
            ]);
            $transaction->products;
            $transaction->total();
            return response()->json(['ok' => true, 'data' => $transaction, 'message' => "Transaction has been created!"], 200);
        }



    }

    // MIDDLEWARE - auth:api
    // ROUTE - DELETE: api/transactions/{transaction}
    public function void (Request $request, Transaction $transaction){
        if($transaction->user->id === $request->user()->id){
            $transaction->void = !$transaction->void;
            $transaction->save();
            $request->user()->logs()->create([
                'table_name' => 'transactions',
                'object_id' => $transaction->id,
                'label' => 'transaction-store',
                'description' => $transaction->void ? "Transaction ($transaction->id) has been voided!" : "Transaction ($transaction->id) has been unvoided!",
                'properties' => json_encode(['user-agent' => $request->userAgent(), 'token' => $request->user()->token()->id]),
                'ip' => $request->ip()
            ]);
            $transaction->total();
            return response()->json(['ok' => true, 'message' => $transaction->void ? 'Transaction has been voided!' : 'Transaction has been unvoided!', 'data' => $transaction], 200);
        }
        else{
            return response()->json(['ok' => false, 'message' => "Not Found!"], 404);
        }
    }
}
