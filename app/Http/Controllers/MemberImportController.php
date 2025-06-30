<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemberImportController extends Controller
{
    public function index()
{
    return inertia('Members/Import');

}


}
