<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\admin\BaseController;
use App\Helpers\Helper;
use Validator;
use App\Banners;
use Image;

class BannersController extends BaseController {
    /* Banners Listing */

    public function index() {
        try {
            return view('admin.banners.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('banners.index');
        }
    }

    public static function load_data_in_table(Request $request) {
        $user_data = Banners::query();
        if ($request->order == null) {
            $user_data = $user_data->orderBy('id', 'desc');
        }
        $searcharray = array();
        parse_str($request->fromValues, $searcharray);

        if (isset($searcharray) && !empty($searcharray)) {
            
        }
        return Datatables::of($user_data)
                        ->addColumn('image', function ($data) {
                            if ($data->image != '') {
                                return url($data->image);
                            }
                        })
                        ->addColumn('action', function ($data) {
                            $editLink = route("banners.edit", $data->id);
                            $viewLink = route("banners.show", $data->id);

                            return Helper::Action($editLink, $data->id, '', '', $data->id);
                        })
                        ->rawColumns(['status', 'action', 'profile_pic'])
                        ->make(true);
    }

    public function create() {
        return view('admin.banners.create');
    }

    public function store(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                        'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=600,min_height=350',
                        'url' => 'required|url',
            ]);
            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator->errors());
            }
            $banner = new Banners();
            $banner->url = $request->url;

            $path = public_path()."/assets/banners/";

           
                $images = $request->file('image');

                $image_name = date('YmdHis') . rand(1000, 9999) . '.' . $images->getClientOriginalExtension();

                 Image::make($request->file('image'))
                        ->resize(650, 350)->save($path.$image_name);

                        
                // \Storage::disk('public')->put('banners/' . $image_name, file_get_contents($images), 'public');

                $banner->image = \Storage::url('banners/') . $image_name;

            $banner->save();
            session()->flash('success', "Banner created successfully");
            return redirect()->route('banners.index');
        } catch (\Exception $ex) {
            return back()->withInput()->withErrors([$ex->getMessage()]);
        }
    }

    public function edit($id) {
        try {
            $data = Banners::find($id);
            return view('admin.banners.edit', compact('data'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('banners.index');
        }
    }

    public function update(Request $request, $id) {
        try {
            $validator = Validator::make($request->all(), [
                        'url' => 'required|url',
                        'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:width=600,height=350',
            ]);
            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator->errors());
            }
            $data = Banners::find($id);
            $data->url = $request->url;

            if (isset($request['image'])) {
                $images = $request->file('image');
                $image_name = date('YmdHis') . rand(1000, 9999) . '.' . $images->getClientOriginalExtension();

                \Storage::disk('public')->put('banners/' . $image_name, file_get_contents($images), 'public');
                $data->image = \Storage::url('banners/') . $image_name;
            }
            $data->save();
            session()->flash('success', "Banner updated successfully");
            return redirect()->route('banners.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return back()->withInput();
        }
    }

    /* End End */

    /* Delete Banners */

    public function destroy($id) {
        try {
            $data = Banners::where('id', $id)->delete();
            return Response::json($data);
        } catch (\Exception $e) {
            Log::error('BannersController->destroy' . $e->getCode());
        }
    }

  

}
