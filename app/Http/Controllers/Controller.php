<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base Controller
 * 
 * Parent controller class for all application controllers.
 * Provides common functionality like authorization and validation.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
