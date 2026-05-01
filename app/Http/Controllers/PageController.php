<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
     public function login(){
        return view ('login');
    }

     public function home(){
        return view ('home');
    }

     public function adminDashboard(){
        return view ('Admin.adminDashboard');
    }

    public function archives(){
        return view ('Admin.archives');
    }

    public function folders(){
        return view ('folders');
    }

    public function reports(){
        return view ('Admin.reports');
    }

    public function superAdminDashboard(){
        return view ('SuperAdmin.superAdminDashboard');
    }

    public function activityLogs(){
        return view ('SuperAdmin.activityLogs');
    }

     public function manageAdmins(){
        return view ('SuperAdmin.manageAdmins');
    }

    public function landingPage(){
        return view ('landingPage');
    }

    public function backup(){
        return view ('SuperAdmin.backup');
    }

    public function studentDashboard(){
        return view ('Student.stuDashboard');
    }


}
