<?php

namespace App\Http\Controllers;

use App\Telegram;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Telegram\Bot\Api;
use coinmarketcap\api\CoinMarketCap;

class TelegramController extends Controller
{
    protected $telegram;
    protected $chat_id;
    protected $username;
    protected $text;

    public function __construct()
    {
    	$this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function getMe()
    {
    	$resp = $this->telegram->getMe();
    	return $resp;
    }

    public function setWebHook()
	{
	    $url = 'https://cryptbots.herokuapp.com/' . env('TELEGRAM_BOT_TOKEN') . '/webhook';
	    $response = $this->telegram->setWebhook(['url' => $url]);
	    return $response == true ? redirect()->back() : dd($response);
	}

    private function randOpenMsg()
    {
        $msg = [
            'Hallo bang, ada yang bisa di bantu ?', 'Hallo boss, gimana ? ada yang bisa di bantu ?', 'Uhh... Gimana pak ?', 'Iya pak ? Gimana ?', 'Oh gimana mas / mbak ?'
        ];
        return $msg[array_rand($msg)];
    }

	public function handleRequest(Request $request)
    {
        $this->chat_id = $request['message']['chat']['id'];
        $this->username = $request['message']['from']['username'];
        $this->text = $request['message']['text'];
        
        switch ($this->text) {
            case strpos($this->text, 'menu') !== false:
                $msg = $this->randOpenMsg();
                $this->showMenu($msg);
                break;
            case strpos($this->text, 'global') !== false:
                $this->showGlobal();
                break;
            case strpos($this->text, 'top') !== false:
                $this->getTicker();
                break;
            case strpos($this->text, 'info') !== false:
                $this->getCurrencyTicker();
                break;
            default:
                $this->checkDatabase();
        }
    }

    public function getCurrencyTicker()
    {
        $message = "Coin apa bapak ? ";
        Telegram::create([
            'username' => $this->username,
            'command' => __FUNCTION__
        ]);
        $this->sendMessage($message);
    }

    private function strposa($haystack, $needle, $offset=0) {
        if(!is_array($needle)) $needle = array($needle);
        foreach($needle as $query) {
            if(strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
        }
        return false;
    }

    private function clearMessage($inputan)
    {
        $input = strtolower($inputan);
        if (strpos($input, 'bitcoin') !== false) {
            return 'bitcoin';
        } elseif (strpos($input, 'litecoin') !== false) {
            return 'litecoin';
        } elseif (strpos($input, 'monero') !== false) {
            return 'monero';
        } elseif (strpos($input, 'etherum') !== false) {
            return 'etherum';
        }
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

    public function formatCoin($data)
    {
        $arr = [
            "Ranking Coin \xF0\x9F\x8C\x9F" => $data['rank'],
            'Nama Coin' => $data['name'],
            'Alias Coin' => $data['symbol'],
            "Harga USD \xF0\x9F\x92\xB0" => '$'.number_format($data['price_usd']),
            "Harga IDR \xF0\x9F\x92\xB0" => 'Rp.'.$this->convertToIDR($data['price_usd']),
            "Total Supply \xF0\x9F\x93\xA6" => number_format($data['total_supply']).' '.$data['symbol'],
        ];
        return $arr;
    }

    public function formatGlobal($data)
    {
        $arr = [
            'Total Market Cap (USD)' =>  "\xE2\x9C\x94 ".number_format($data['total_market_cap_usd']),
            'Total Volume Coin Selama 24 Jam' =>  "\xE2\x9C\x94 ".number_format($data['total_24h_volume_usd']),
            'Persentase Bitcoin di Market' =>  "\xE2\x9C\x94 ".$data['bitcoin_percentage_of_market_cap'].'%',
            'Kurs aktif' => "\xE2\x9C\x94 ". number_format($data['active_currencies']),
            'Aset aktif' =>  "\xE2\x9C\x94 ".number_format($data['active_assets']),
            'Market aktif' =>  "\xE2\x9C\x94 ".number_format($data['active_markets']),
        ];
        return $arr;
    }

    public function checkDatabase()
    {
        $telegram = Telegram::where('username', $this->username)->latest()->first();
        if ($telegram->command === 'getCurrencyTicker') {
            $clearMsg = $this->clearMessage($this->text);
            $response = CoinMarketCap::getCurrencyTicker($clearMsg);
            if (isset($response['error'])) {
                $message = 'Sorry no such cryptocurrency found buddy..';
            } else {
                $data = collect($response);
                $rep = $data->first();

                $message = $this->formatArray($this->formatCoin($rep));
            }
            Telegram::where('username', $this->username)->delete();
            $this->sendMessage($message, true);
        } else {
            $error = "Sorry pak, gak ada hasil untuk : .\n";
            $error .= "<b>".$this->text." 2 </b>";
            $this->showMenu($error);
        }
    }

    public function showMenu($info = null)
    {
        $message = '';
        if ($info) {
            $message .= $info . chr(10);
        }
        $message .= '-----------' . chr(10);
        $message .= 'Kalo mau nampilin total cap market tinggal tulis command "global" contoh "tampilin global"' . chr(10);
        $message .= '-----------' . chr(10);
        $message .= 'Beberapa list command yang tersedia : ' . chr(10);
        $message .= '"global" => nampilin total market cap' . chr(10);
        $message .= '"top" => nampilin 10 ranking coin' . chr(10);
        $message .= '"info" => nampilin info coin berdasarkan inputan' . chr(10);
 
        $this->sendMessage($message, true);
    }
 
    public function showGlobal()
    {
        $data = CoinMarketCap::getGlobalData();
        $this->sendMessage($this->formatArray($this->formatGlobal($data)), true);
    }
 
    public function getTicker()
    {
        $data = CoinMarketCap::getTicker();
        $formatted_data = "";
        foreach ($data as $datum) {
            $formatted_data .= $this->formatArray($this->formatCoin($datum));
            $formatted_data .= "-----------\n";
        }
        $this->sendMessage($formatted_data, true);
    }
 
    protected function formatArray($data)
    {
        $formatted_data = "";
        foreach ($data as $item => $value) {
            $item = str_replace("_", " ", $item);
            if ($item == 'last updated') {
                $value = Carbon::createFromTimestampUTC($value)->diffForHumans();
            }
            $formatted_data .= "<b>{$item}</b>\n";
            $formatted_data .= "\t".$value."\n";
        }
        return $formatted_data;
    }
 
    protected function sendMessage($message, $parse_html = false)
    {
        $data = [
            'chat_id' => $this->chat_id,
            'text' => $message,
        ];
        if ($parse_html) $data['parse_mode'] = 'HTML';
        $this->telegram->sendMessage($data);
    }
}
