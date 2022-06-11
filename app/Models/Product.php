<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;

class Product extends Model
{
    protected $fillable = [
        'title', 'sku', 'description'
    ];

    /*
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }
    */

}
