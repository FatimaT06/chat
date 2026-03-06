<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('chat_token') || !session('chat_user')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}