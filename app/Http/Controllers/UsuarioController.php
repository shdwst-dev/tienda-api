<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    /**
     * Solo admin (la ruta lleva rol:admin). La contraseña nunca sale: User la oculta con #[Hidden].
     */
    public function index(): JsonResponse
    {
        return response()->json(User::orderBy('id')->get(['id', 'name', 'email', 'rol', 'created_at']));
    }
}
