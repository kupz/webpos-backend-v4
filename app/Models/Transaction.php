<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function products(){
        return $this->belongsToMany(Product::class)->withPivot("quantity", 'price');
    }
    
    public function total(){
        $this->total_quantity = 0;
        $this->total_price = 0;
        $this->products->map(function($product){
            $this->total_quantity += abs($product->pivot->quantity);
            $this->total_price += abs($product->pivot->price * $product->pivot->quantity);
        });
    }
}
