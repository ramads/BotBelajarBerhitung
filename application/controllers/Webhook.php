<?php defined('BASEPATH') OR exit('No direct script access allowed');

// SDK for create bot
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;

// SDK for build message
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;

// SDK for build button and template action
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Webhook extends CI_Controller
{

    private $bot;
    private $events;
    private $signature;
    private $user;

    const
        penjumlahan = 'penjumlahan',
        pengurangan = 'pengurangan',
        perkalian = 'perkalian',

        STICKER = [
            'sad' => [
                //1 => [1, 8, 9, 16, 101, 104, 109, 111, 121, 123, 127, 131, 133, 135, 401],
                1, 8, 9, 16, 101, 104, 109, 111, 121, 123, 127, 131, 133, 135, 401
            ],
            'happy' => [
                //1 => [2, 4, 5, 13, 14, 106, 120, 125, 134, 138, 407, 410],
                2, 4, 5, 13, 14, 106, 120, 125, 134, 138, 407, 410
            ]
        ];

    function __construct()
    {
        parent::__construct();
        $this->load->model('model_berhitung');
        // create bot object
        $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
        $this->bot = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo "Hello Coders!\nSemangat coding hari ini !";
            header('HTTP/1.1 400 Only POST method allowed');
            exit;
        }

        // get request
        $body = file_get_contents('php://input');
        $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
        $this->events = json_decode($body, true);

        // log every event requests);
        $this->model_berhitung->log_events($this->signature, $body);

        foreach ($this->events['events'] as $event) {

            // skip group and room event
            if (!isset($event['source']['userId'])) continue;

            // get user data from database
            $this->user = $this->model_berhitung->getUser($event['source']['userId']);

            // respond event
            if ($event['type'] == 'message') {
                if (method_exists($this, $event['message']['type'] . 'Message')) {
                    $this->{$event['message']['type'] . 'Message'}($event);
                }
            } else {
                if (method_exists($this, $event['type'] . 'Callback')) {
                    $this->{$event['type'] . 'Callback'}($event);
                }
            }
        }
    }

    /**
     * Method respon event yg memiliki type Callback
     * dipanggil saat user menambahkan bot sebagai teman
     * @param $event
     */
    private function followCallback($event)
    {
        $res = $this->bot->getProfile($event['source']['userId']);
        if ($res->isSucceeded()) {
            $profile = $res->getJSONDecodedBody();
            // save user data
            $this->model_berhitung->saveUser($profile);

            // send welcome message
            $message = "Halo. Salam kenal, " . $profile['displayName'] . "!\n";
            $message .= "Bot ini merupakan Bot untuk belajar perhitungan sederhana yang hanya baru meliputi penjumlahan, pengurangan dan perkalian saja. Semoga bermanfaat.\n\n";
            $message .= $this->getRuleMessage();

            $this->sendMessage($event['source']['userId'], $message);
            $this->sendSticker($event['source']['userId'], 1, 13);
        }
    }

    private function textMessage($event)
    {
        $userId = $event['source']['userId'];
        $userMessage = $event['message']['text'];

        // jika state user belum mulai pembelajaran
        if ($this->model_berhitung->getUserState($userId) == 0) {
            if (strtolower($userMessage) == 'mulai') {
                // set state mulai belajar (1)
                $this->setState($userId, 1);

                $this->sendStartQuestion($userId);

            } elseif (strtolower($userMessage) == 'sesi') {
                // set state belum belajar (0)
                $this->setState($userId, 0);

                // tampilkan pilihan sesi belajar
                $this->sendSessionOption($userId);
            } elseif (strtolower($userMessage) == self::penjumlahan) {
                $this->setState($userId, 1);
                $this->model_berhitung->setSession($userId, self::penjumlahan);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::penjumlahan));
                // send question
                $this->sendQuestion($userId, self::penjumlahan);
            } elseif (strtolower($userMessage) == self::pengurangan) {
                $this->setState($userId, 1);
                $this->model_berhitung->setSession($userId, self::pengurangan);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::pengurangan));
                $this->sendQuestion($userId, self::pengurangan);
            } elseif (strtolower($userMessage) == self::perkalian) {
                $this->setState($userId, 1);
                $this->model_berhitung->setSession($userId, self::perkalian);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::perkalian));
                // send question
                $this->sendQuestion($userId, self::perkalian);
            } else {
                $this->sendMessage($userId, $this->getRuleMessage());
            }

        // jika state user telah memulai pembelajaran
        } else {
            if (strtolower($userMessage) == 'mulai') {
                $this->sendStartQuestion($userId);
            } elseif (strtolower($userMessage) == 'sesi') {
                $this->setState($userId, 0);
                $this->sendSessionOption($userId);
            } elseif (strtolower($userMessage) == self::penjumlahan) {
                $this->model_berhitung->setSession($userId, self::penjumlahan);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::penjumlahan));
                $this->sendQuestion($userId, self::penjumlahan);
            } elseif (strtolower($userMessage) == self::pengurangan) {
                $this->model_berhitung->setSession($userId, self::pengurangan);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::pengurangan));
                $this->sendQuestion($userId, self::pengurangan);
            } elseif (strtolower($userMessage) == self::perkalian) {
                $this->model_berhitung->setSession($userId, self::perkalian);
                $this->sendMessage($userId, $this->getSessionInfoMessage($userId, self::perkalian));
                $this->sendQuestion($userId, self::perkalian);
            } elseif (is_numeric($userMessage)) {
                $this->checkAnswer($userId, $userMessage);
            } else {
                $this->sendMessage($userId, $this->getRuleMessage());
            }
        }
    }

    private function getRuleMessage()
    {
        return "Silakan kirim pesan \"MULAI\" untuk mulai belajar.\nAtau kirim pesan \"SESI\" untuk memilih Sesi pembelajaran.";
    }

    private function getSessionInfoMessage($userId, $sessionName)
    {
        return "Kamu sedang berada di Sesi Belajar " . strtoupper($sessionName)
            . " Level " . $this->model_berhitung->getUserLevel($userId);
    }

    private function getLevelUpMessage($newLevel)
    {
        return "Jawaban Kamu Benarrrr...
            \nKamu sekarang naik ke Level $newLevel
            .\nSoalnya bakalan lebih sulit sekarang. Lanjutkan!!";
    }

    private function sendMessage($userId, $message)
    {
        $textMessageBuilder = new TextMessageBuilder($message);
        $this->bot->pushMessage($userId, $textMessageBuilder);
    }

    private function sendSticker($userId, $packageId, $stickerId)
    {
        $stickerMessageBuilder = new StickerMessageBuilder($packageId, $stickerId);
        $this->bot->pushMessage($userId, $stickerMessageBuilder);
    }

    private function setState($userId, $state)
    {
        // update number progress
        $this->model_berhitung->setUserState($userId, $state);
    }

    private function sendSessionOption($userId)
    {
        $options = [
            new MessageTemplateActionBuilder("PENJUMLAHAN", "PENJUMLAHAN"),
            new MessageTemplateActionBuilder("PENGURANGAN", "PENGURANGAN"),
            new MessageTemplateActionBuilder("PERKALIAN", "PERKALIAN")
        ];
        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(
            "Sesi Belajar",
            "Pilih salah satu dari Sesi Belajar di bawah ini!",
            null, $options);
        // build message
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
        // send message
        $this->bot->pushMessage($userId, $messageBuilder);
    }

    private function sendStartQuestion($userId)
    {
        // kirim pertanyaan berdasarkan sesi terakhir user
        $lastSession = $this->model_berhitung->getLastSession($userId);
        $this->sendMessage($userId, $this->getSessionInfoMessage($userId, $lastSession));
        $this->sendQuestion($userId, $lastSession);
    }

    public function sendQuestion($userId, $session)
    {
        // get question from database
        $question = $this->model_berhitung->getQuestion($userId);
        $message = "Berapakah hasil dari " . strtoupper($session) . " berikut ini? :\n " . $question . " = .....";
        $this->sendMessage($userId, $message);
    }

    private function checkAnswer($userId, $message)
    {
        // if answer is true, increment score
        $result = $this->model_berhitung->isAnswerEqual($userId, $message);
        if ($result['levelUpdated']) {
            $this->sendSticker($userId, 1, $this->getStickerRandom('happy'));
            $this->sendMessage($userId, $this->getLevelUpMessage($result['level'] + 1));
        } elseif ($result['isEqual']) {
            $this->sendSticker($userId, 1, $this->getStickerRandom('happy'));
            $this->sendMessage($userId, "Jawaban Kamu benar.\n Lanjutkan!!");
        } else {
            $this->sendSticker($userId, 1, $this->getStickerRandom('sad'));
            $this->sendMessage($userId, "Jawaban Kamu salah.\nJawaban yang benar adalah \"" . $result['answer'] . "\"");
        }

        $this->sendQuestion($userId, $result['session']);
    }

    private function getStickerRandom($stickerName)
    {
        return self::STICKER[$stickerName][rand(0, count(self::STICKER[$stickerName]))];
    }
}
