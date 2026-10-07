<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('app');
    }

    public function login(): string
    {
        return view('login');
    }
}
