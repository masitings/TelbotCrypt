<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use coinmarketcap\api\CoinMarketCap;

class TestController extends Controller
{
    public function data()
    {
        $response = CoinMarketCap::getCurrencyTicker('bitcoin');
        $data = collect($response);
        return $data->first();
    }

    private function priceDollar()
    {
        $base = 'https://free.currencyconverterapi.com/api/v6/convert?q=USD_IDR&compact=y';
        $get = file_get_contents($base);
        $val = collect(json_decode($get));
        $vals = number_format($val['USD_IDR']->val);
        return str_replace(',', '', $vals);
    }

    private function convertToIDR($amount)
    {
        $am = (int)$amount;
        $usd = $this->priceDollar();
        return number_format($am * $usd);
    }

    public function index()
    {
        $data = $this->data();
        $arr = [
            'Ranking Coin' => $data['rank'],
            'Nama Coin' => $data['name'],
            'Alias Coin' => $data['symbol'],
            'Harga USD' => '$'.number_format($data['price_usd']),
            'Harga IDR' => 'Rp.'.$this->convertToIDR($data['price_usd']),
            'Total Supply' => number_format($data['total_supply']).' BTC',
        ];
        return $arr;
        // $arr = [];
        // foreach ($data as $key) {
        //     $arr['nama'] = $key->name;
        // }
        // return $arr;
    }
}
