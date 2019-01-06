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

	public function handleRequest(Request $request)
    {
        $this->chat_id = $request['message']['chat']['id'];
        $this->username = $request['message']['from']['username'];
        $this->text = $request['message']['text'];
        
        switch ($this->text) {
            case strpos($this->text, 'menu') !== false:
                $msg = "Hallo bang / mbak ? Ada yang bisa dibantu ?.\n";
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

    private function strposa($haystack, $needles=array(), $offset=0) {
        $chr = array();
        foreach($needles as $needle) {
                $res = strpos($haystack, $needle, $offset);
                if ($res !== false) $chr[$needle] = $res;
        }
        if(empty($chr)) return false;
        return min($chr);
    }

    private function clearMessage()
    {
        if ($this->strposa($this->text, ['btc', 'bitcoin'], 1)) {
            return 'bitcoin';
        } elseif ($this->strposa($this->text, ['ltc', 'litecoin'], 1)) {
            return 'litecoin';
        } elseif ($this->strposa($this->text, ['xmr', 'monero'], 1)) {
            return 'monero';
        } elseif ($this->strposa($this->text, ['eth', 'etherum'], 1)) {
            return 'etherum';
        } else {
            return false;
        }
    }

    public function checkDatabase()
    {
        $telegram = Telegram::where('username', $this->username)->latest()->first();
        if ($telegram->command === 'getCurrencyTicker') {
            $clearMsg = $this->clearMessage();
            if ($clearMsg) {
                $response = CoinMarketCap::getCurrencyTicker($this->text);
                if (isset($response['error'])) {
                    $message = 'Sorry no such cryptocurrency found buddy..';
                } else {
                    $message = $this->formatArray($response[0]);
                }
                Telegram::where('username', $this->username)->delete();
                $this->sendMessage($message, true);
            } else {
                $error = "Sorry pak, gak ada hasil untuk : .\n";
                $error .= "<b>".$this->text."</b>";
                $this->showMenu($error, true);
            }
        } else {
            $error = "Sorry pak, gak ada hasil untuk : .\n";
            $error .= "<b>".$this->text."</b>";
            $this->showMenu($error, true);
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
