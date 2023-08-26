<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function transactions(){
        return $this->belongsToMany(Transaction::class);
    }

    public function stock(){
        $result = DB::table('products')->join("product_transaction", "products.id" , "=", "product_transaction.product_id")->join("transactions", 'transactions.id', '=', 'product_transaction.transaction_id')
        ->selectRaw("SUM(product_transaction.quantity) as 'stock', SUM(product_transaction.quantity * products.price) as 'total_price'")->where('transactions.void', false)->where('products.id', $this->id)->groupBy('products.id')->first();
        if($result){
            $this->stock = $result->stock;
            $this->total_price = $result->total_price;
        }
        else{
            $this->stock = 0;
            $this->total_price = 0;
        }
    }
}
