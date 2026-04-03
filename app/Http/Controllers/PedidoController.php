<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function index()
    {
        $pedidos = Pedido::with(['sede', 'detalles'])
            ->where('canal', 'whatsapp')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('pedidos.index', compact('pedidos'));
    }
}