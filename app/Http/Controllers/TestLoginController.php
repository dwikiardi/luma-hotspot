<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestLoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $admin = Admin::where("email", "admin@luma.com")->first();
        
        if ($admin && Auth::guard("admin")->login($admin)) {
            return response()->json([
                "success" => true,
                "message" => "Logged in successfully",
                "user" => Auth::guard("admin")->user()->email
            ]);
        }
        
        return response()->json([
            "success" => false,
            "message" => "Login failed"
        ]);
    }
}
