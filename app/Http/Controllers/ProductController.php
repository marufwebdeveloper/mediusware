<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;
use DB;
use Session;

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
        $data['products'] = $this->SearchProduct()?:Product::orderBy('id','desc')->paginate($perpage);
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

        
        $variants = DB::Select("SELECT product_variants.id,variant,variants.title from product_variants left join variants on variants.id=product_variants.variant_id WHERE product_variants.id in ( SELECT DISTINCT product_variant_one FROM product_variant_prices UNION SELECT DISTINCT product_variant_two FROM product_variant_prices UNION SELECT DISTINCT product_variant_three FROM product_variant_prices) ");

        $data['variants'] = array_reduce($variants,function($return,$data){
            $return["'".$data->title."'"][] = $data;
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
        $success = Product::create($request->all());
        if($success)echo 1;
        Session::flash('success', 'Data stored successfully');
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
        $product_variant = DB::table('product_variant_prices')
        ->leftJoin('product_variants as variant_one','variant_one.id','product_variant_prices.product_variant_one')
        ->leftJoin('product_variants as variant_two','variant_two.id','product_variant_prices.product_variant_two')
        ->leftJoin('product_variants as variant_three','variant_three.id','product_variant_prices.product_variant_three') 
        ->where('product_variant_prices.product_id',$product->id)
        ->select('product_variant_prices.*',
            'variant_one.variant as variant_one',
            'variant_one.variant_id as variant_id_one',

            'variant_two.variant as variant_two',
            'variant_two.variant_id as variant_id_two',

            'variant_three.variant as variant_three',
            'variant_three.variant_id as variant_id_three'
        )
        ->get(); 

        $variant_details = DB::table('product_variants')
        ->get(); 

        $images = DB::table('product_images')
        ->where('product_id',$product->id)
        ->get(); 
        
        return view('products.edit', compact('variants','product','product_variant','variant_details','images'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $product_id)
    {


        $success = Product::where('id', $product_id)
        ->update([
            'title' => $request['title'],
            'sku' => $request['sku'],
            'description' => $request['description']
        ]);

        $getVarientIds = array_column(DB::table('product_variant_prices')
        ->where('product_id',$product_id)
        ->select('id')
        ->get()->toArray(),'id');

        $newVarients = [];

        foreach ($request['product_variant_prices'] as $key => $value) {

            if(!$value['id']){
                $varientData = $this->PrepareNewVarients($value);
                if($varientData)
                $newVarients[] = $varientData;
            }

            if(in_array($value['id'], $getVarientIds)){

                if (($key = array_search($value['id'], $getVarientIds)) !== false) {
                    unset($getVarientIds[$key]);
                }

                DB::table('product_variant_prices')
                ->where('id', $value['id'])
                ->update([
                    'product_variant_one' => $value['product_variant_one'],
                    'product_variant_two' => $value['product_variant_two'],
                    'product_variant_three' => $value['product_variant_three'],
                    'price' => $value['price'],
                    'stock' => $value['stock']                
                ]);
            }     
        }

        if($getVarientIds){
            DB::table('product_variant_prices')
        ->whereIn('id', $getVarientIds)
        ->delete();
        }
        

        if($newVarients){
            DB::table('product_variant_prices')->insert($newVarients);
        }  

        $this->StoreImages($request['product_image'],$product_id);

        echo $success;
        Session::flash('success', 'Data updated successfully');
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

    public function ImageUpload(Request $request)
    {
        if($request->file('file')){
            $imageName = time().rand(1111,9999).'.'.$request->file->getClientOriginalExtension();
            $request->file->move(public_path('product-images'), $imageName);
              
            //return response()->json(['success'=>'We have successfully upload file.']);

            echo $imageName;


        }
    }




    private function SearchProduct(){
        $title = request()->title;
        $variant = request()->variant;
        $price_from = request()->price_from;
        $price_to = request()->price_to;
        $date = request()->date;        
        $atleastonce = false;

        $getProductId = DB::table("products");

        if(($price_from && $price_to) || $variant){
            $getProductId->leftJoin("product_variant_prices","product_variant_prices.product_id","products.id");
            $getProductId->leftJoin('product_variants as variant_one','variant_one.id','product_variant_prices.product_variant_one')
            ->leftJoin('product_variants as variant_two','variant_two.id','product_variant_prices.product_variant_two')
            ->leftJoin('product_variants as variant_three','variant_three.id','product_variant_prices.product_variant_three');
            if(($price_from && $price_to))
            $getProductId->whereBetween('price',[$price_from,$price_to]);
            $getProductId->where(function ($query) use ($variant) {
                $query->where('product_variant_one', '=', $variant)
                      ->orWhere('product_variant_two', '=', $variant)
                      ->orWhere('product_variant_three', '=', $variant);
            });
            $atleastonce = true;
        }

        if($title){
            $getProductId->where('title','like',"%$title%");
            $atleastonce = true;
        }
        if($date){
            $getProductId->where(DB::raw('DATE(products.created_at)'),$date);
            $atleastonce = true;
        }
        if($atleastonce){
            $getProductId = $getProductId->select(DB::raw("Distinct products.id"))->get();
            $ids = array_column($getProductId->toArray(),'id');
            return DB::table('products')->whereIn('id',$ids)->get();
        }
        
    }


    private function PrepareNewVarients($data){
        $varientData = [];
        if(
            (
                $data['product_variant_one'] ||
                $data['product_variant_two'] ||
                $data['product_variant_three']
            ) && $data['price'] && $data['stock']
        ){

            $varientData = [
                'product_variant_one' => $data['product_variant_one'],
                'product_variant_two' => $data['product_variant_two'],
                'product_variant_three' => $data['product_variant_three'],
                'price' => $data['price'],
                'stock' => $data['stock'],
                'product_id' => $data['product_id']
                
            ];
        }
        return $varientData;
    }

    private function StoreImages($images,$product_id){
        $get_images = DB::table('product_images')
        ->where('product_id',$product_id)
        ->get(); 

        $imageNames = array_column($get_images->toArray(),'file_path'); 

        $deletedImages = [];
        foreach($get_images as $img){
            if(!in_array($img->file_path,$images)){

                $deletedImages[] = $img->id;
            }
        }
        if($deletedImages){
            DB::table('product_images')
                ->whereIn('id', $deletedImages)
                ->delete();
        }
        
        $storeImage = [];
        foreach($images as $image){
            if(in_array($image,$imageNames)) continue;
            $storeImage[]=[
                'product_id' => $product_id,
                'file_path' => $image
            ];
        }
        if($storeImage){
            DB::table('product_images')->insert($storeImage);
        }
    }






}
