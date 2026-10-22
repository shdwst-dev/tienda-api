<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /**
     * Un cliente solo ve sus propios pedidos; el admin los ve todos.
     */
    public function view(User $user, Pedido $pedido): Response
    {
        if ($user->esAdmin() || $pedido->user_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('No tienes permiso para ver este pedido.');
    }
}
