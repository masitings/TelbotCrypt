<?php

namespace App\Http\Controllers;

use App\Telegram;
use Carbon\Carbon;
use coinmarketcap\api\CoinMarketCap;
use Exception;
use Illuminate\Http\Request;
use Telegram\Bot\Api;

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

	public function handleRequest(Request $request)
    {
        $this->chat_id = $request['message']['chat']['id'];
        $this->username = $request['message']['from']['username'];
        $this->text = $request['message']['text'];
        
        if (strpos($this->text, 'menu') !== false) {
            $this->showMenu(); 
        } elseif (strpos($this->text, 'global') !== false) {
            $this->getGlobal();
        } elseif (strpos($this->text, 'top') !== false) {
            $this->getTicker();
        } elseif (strpos($this->text, 'coin') !== false) {
            $this->getCurrencyTicker();
            if ($this->processCoin() != false) {
                $this->sendMessage($this->processCoin(), true);
            } else {
                $this->sendMessage('SALAH OM', true);
            }
            // $this->checkDatabase();
        } else {
            $error = "Sorry, no such cryptocurrency found.\n";
            $error .= "Please select one of the following options";
            $this->showMenu($error);
        }
        // switch ($this->text) {
        //     case '/start':
        //     case '/menu':
        //         $this->showMenu();
        //         break;
        //     case '/getGlobal':
        //         $this->showGlobal();
        //         break;
        //     case '/getTicker':
        //         $this->getTicker();
        //         break;
        //     case '/getCurrencyTicker':
        //         $this->getCurrencyTicker();
        //         break;
        //     default:
        //         $this->checkDatabase();
        // }
    }

    private function strposa($haystack, $needles=array(), $offset=0) {
        $chr = array();
        foreach($needles as $needle) {
                $res = strpos($haystack, $needle, $offset);
                if ($res !== false) $chr[$needle] = $res;
        }
        if(empty($chr)) return false;
        return min($chr);
    }

    public function processCoin()
    {
        $coin = [
            'bitcoin', 'btc', 'litecoin', 'ltc', 'monero', 'xmr'
        ];
        if ($this->strposa($this->text, $coin, 1)) {
            return $coin;
        } else {
            return false;
        }
    }

    public function showMenu($info = null)
    {
        $message = '';
        if ($info) {
            $message .= $info . chr(10);
        }
        $message .= '/menu (Menampilkan menu)' . chr(10);
        $message .= '/getGlobal (Menampilkan total seluruh coin)' . chr(10);
        $message .= '/getTicker (Menampilkan 10 ranking coin.)' . chr(10);
        $message .= '/getCurrencyTicker (Menampilkan berdasarkan inputan)' . chr(10);
 
        $this->sendMessage($message);
    }
 
    public function showGlobal()
    {
        $data = CoinMarketCap::getGlobalData();
 
        $this->sendMessage($this->formatArray($data), true);
    }
 
    public function getTicker()
    {
        $data = CoinMarketCap::getTicker();
        $formatted_data = "";
 
        foreach ($data as $datum) {
            $formatted_data .= $this->formatArray($datum);
            $formatted_data .= "-----------\n";
        }
 
        $this->sendMessage($formatted_data, true);
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
 
    public function checkDatabase()
    {
        try {
            $telegram = Telegram::where('username', $this->username)->latest()->firstOrFail();
 
            if ($telegram->command == 'getCurrencyTicker') {
                $response = CoinMarketCap::getCurrencyTicker($this->text);
 
                if (isset($response['error'])) {
                    $message = 'Sorry no such cryptocurrency found';
                } else {
                    $message = $this->formatArray($response[0]);
                }
 
                Telegram::where('username', $this->username)->delete();
 
                $this->sendMessage($message, true);
            }
        } catch (Exception $exception) {
            $error = "Sorry, no such cryptocurrency found.\n";
            $error .= "Please select one of the following options";
            $this->showMenu($error);
        }
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
