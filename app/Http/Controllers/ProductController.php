<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;
use DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index()
    {
        $perpage = 3;

        $data['products'] = Product::orderBy('id','desc')->paginate($perpage);
        $data['products_count'] = Product::count();
        $data['productper_page'] = $perpage; 

        $product_variants = DB::table('product_variant_prices')
        ->leftJoin('product_variants as variant_one','variant_one.id','product_variant_prices.product_variant_one')
        ->leftJoin('product_variants as variant_two','variant_two.id','product_variant_prices.product_variant_two')
        ->leftJoin('product_variants as variant_three','variant_three.id','product_variant_prices.product_variant_three')        
        ->select('product_variant_prices.*','variant_one.variant as variant_one','variant_two.variant as variant_two','variant_three.variant as variant_three')
        ->get()->toArray();

        $data['product_variants'] = array_reduce($product_variants,function($return,$data){
            $return[$data->product_id][] = $data;
            return $return;
       });

        return view('products.index',$data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        return view('products.edit', compact('variants'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }
}
