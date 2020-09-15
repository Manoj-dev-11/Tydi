<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\admin\BaseController;
use App\Helpers\Helper;
use Validator;
use App\User;
use Illuminate\Support\Facades\Auth;

class BusinessController extends BaseController {
    /* User Listing */

    public function index() {
        try {
            return view('admin.business.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('business.index');
        }
    }

    public static function load_data_in_table(Request $request) {
        $user_data = User::where('role_id', '=', 2);
        if ($request->order == null) {
            $user_data = $user_data->orderBy('id', 'desc');
        }
        $searcharray = array();
        parse_str($request->fromValues, $searcharray);

        if (isset($searcharray) && !empty($searcharray)) {
            if ($searcharray['searchByRole'] != '') {
                $user_data->where("role.id", '=', $searcharray['searchByRole']);
            }
            if ($searcharray['searchByStatus'] != '') {
                $user_data->where("users.status", '=', $searcharray['searchByStatus']);
            }
        }
        return Datatables::of($user_data)
                        ->addColumn('status', function ($data) {
                            return Helper::Status($data);
                        })
                        ->addColumn('profile_pic', function ($data) {
                            if ($data->profile_pic && $data->profile_pic != '') {
                                return Helper::displayProfilePath() . $data->profile_pic;
                            }
                        })
                        ->addColumn('action', function ($data) {
                            $editLink = route("business.edit", $data->id);
                            $viewLink = route("business.show", $data->id);
                            return Helper::Action($editLink, $data->id, $viewLink);
//                            return Helper::Action('','', $viewLink);
                        })
                        ->rawColumns(['status', 'action', 'profile_pic'])
                        ->make(true);
    }

    public function create() {
        return view("admin.business.create");
    }

    public function store(Request $request) {
        try {

            $validator = Validator::make($request->all(), [
                        'name' => 'required',
                        "email" => "required|email|unique:users,email",
                        "password" => "required",
                        "confirm_password" => "required",
                        "address" => "required",
                        "latitude" => "required",
                        "longitude" => "required",
                        "phone_number" => "required",
                        "description" => "required",
                        "main_image" => "required|image",
                        "logo" => "required|image",
            ]);
            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator->errors());
            }

            $request->merge(['role_id' => '2']);

            $user = User::signIn($request);
            
            if ($user) {
                $userdata = json_decode(json_encode($user), true);
                $userdata['password'] = $request->password;
                Mail::send('mail.businesscreated', $userdata, function ($message) use ($userdata) {
                    $message->to($userdata['email']);
                    $message->subject('Admin create new account');
                });
            }

            session()->flash('success', "Business created successfully");
            return redirect()->route('business.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return back()->withInput();
        }
    }

    public function edit($id) {
        try {
            $data = User::find($id);
            if ($data->profile_pic && $data->profile_pic != '') {
                $data->profile_pic = Helper::displayProfilePath() . $data->profile_pic;
            }
            return view('admin.business.edit', compact('data'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('business.index');
        }
    }

    public function update(Request $request, $id) {
        try {
            $validator = Validator::make($request->all(), [
                        'name' => 'required',
                        "email" => "required|email|unique:users,email,$id",
                        "address" => "required",
                        "latitude" => "required",
                        "longitude" => "required",
                        "phone_number" => "required",
                        "description" => "required",
                        "main_image" => "image",
                        "logo" => "image",
            ]);
            if ($validator->fails()) {
                return back()->withInput()->withErrors($validator->errors());
            }

            $data = User::find($id);
            $data->name = $request->name;
            $data->email = $request->email;
            if (!empty($request->password)) {
                $data->password = bcrypt($request->password);
            }
            if (!empty($request->role_id)) {
                $data->role_id = $request->role_id;
            }
            $data->phone_number = $request->phone_number;
            $data->address = $request->get("address");
            $data->latitude = $request->get("latitude");
            $data->longitude = $request->get("longitude");
            $data->description = $request->get("description");

            if (isset($request['main_image'])) {
                $images = $request->file('main_image');
                $image_name = date('YmdHis') . rand(1000, 9999) . '.' . $images->getClientOriginalExtension();
                \Storage::disk('public')->put('business/' . $image_name, file_get_contents($images), 'public');
                $data->main_image = \Storage::url('business/') . $image_name;
            }

            if (isset($request['logo'])) {
                $images = $request->file('logo');
                $image_name = date('YmdHis') . rand(1000, 9999) . '.' . $images->getClientOriginalExtension();
                \Storage::disk('public')->put('business/' . $image_name, file_get_contents($images), 'public');
                $data->logo = \Storage::url('business/') . $image_name;
            }
            $data->save();

            session()->flash('success', "Business updated successfully");
            return redirect()->route('business.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return back()->withInput();
        }
    }

    /* End End */

    /* Delete User */

    public function destroy($id) {
        try {
            $data = User::where('id', $id)->delete();
            return Response::json($data);
        } catch (\Exception $e) {
            Log::error('UserController->destroy' . $e->getCode());
        }
    }

    /* Show User Details */

    public function show($id) {
        try {
            $data = User::getUserDetails($id);
            return view('admin.business.show', compact('data'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('business.index');
        }
    }

    public static function visited_users_list(Request $request) {
        $date = !empty($request->get("date")) ? $request->get("date") : "";
        $all_visited_users = \App\Checkin::where("business_id", $request->get("business_id"))
                ->leftJoin("users", "users.id", "business_checkin.user_id")
                ->select("users.id as user_id", "users.name", "users.email", "users.phone_number", "users.profile_pic", "business_checkin.created_at as checkin_date");
        if (!empty($date)) {
            $all_visited_users->whereBetween("business_checkin.created_at", [$date . " 00:00:00", $date . " 23:59:59"]);
        }
//        $all_visited_users->orderBy("business_checkin.created_at","desc");
        return Datatables::of($all_visited_users)
                        ->addColumn('profile_pic', function ($data) {
                            if ($data->profile_pic && $data->profile_pic != '') {
                                return Helper::displayProfilePath() . $data->profile_pic;
                            }
                        })
                        ->rawColumns(['profile_pic'])
                        ->make(true);
    }

}
