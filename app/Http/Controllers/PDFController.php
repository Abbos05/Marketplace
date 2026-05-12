<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF; // ← Dompdf
use App\Models\User;

class PDFController extends Controller
{
    public function download($txId)
    {
        $tx = \App\Models\Transaction::with('product')->findOrFail($txId);
        $status = $tx->status == 'failed' ? 'Отказано' : 'Успешно';
        $buyer = User::withTrashed()->where('id', $tx->buyer_id)->first();
        $seller = User::withTrashed()->where('id', $tx->seller_id)->first();
    
        $buyerName = $buyer ? ($buyer->name . ($buyer->trashed() ? ' (пользователь удален)' : '')) : 'Аноним';
        $sellerName = $seller ? ($seller->name . ($seller->trashed() ? ' (пользователь удален)' : '')) : 'Аноним';
    
        $data = [
            'type' => $status,
            'product_title' => $tx->product->title ?? 'Неизвестно',
            'product_id' => $tx->product->id,
            'buyer' => $buyerName, // <-- Используем обработанное имя
            'seller' => $sellerName, // <-- Используем обработанное имя
            'price' => $tx->product->price ?? 'Не указано',
            'date' => \Carbon\Carbon::parse($tx->created_at)->format('d.m.Y H:i'),
            'tx_id' => $tx->id,
        ];
    
        // Явно указываем кодировку для DomPDF
        $pdf = PDF::loadView('pdf.certificate', $data)
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    
        return $pdf->download("Транзакция_{$tx->id}.pdf");
    }

}