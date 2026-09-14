<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class ProductDeepDive extends Component
{
    public function render()
    {
        $products = Product::query()
            ->notDemo()
            ->with(['brand', 'colors', 'silhouettes', 'graphics', 'materials'])
            ->latest('created_at')
            ->get();

        return view('livewire.product-deep-dive', [
            'products' => $products,
        ])->layout('layouts.app', ['title' => 'Products — Fashion Intelligence']);
    }
}
