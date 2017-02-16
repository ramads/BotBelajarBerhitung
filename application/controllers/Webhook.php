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

class Webhook extends CI_Controller {

  private $bot;
  private $events;
  private $signature;
  private $user;

  const STICKER = [
      'sad' => [
          1,8,9,16,101, 104
      ],
      'happy' => [
        2,4,5,13,14
      ]
  ];

  function __construct() {
    parent::__construct();
    $this->load->model('model_berhitung');
    // create bot object
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  public function index() {
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
       if(! isset($event['source']['userId'])) continue;

       // get user data from database
       $this->user = $this->model_berhitung->getUser($event['source']['userId']);

       // respond event
       if($event['type'] == 'message'){
           if(method_exists($this, $event['message']['type'].'Message')){
           $this->{$event['message']['type'].'Message'}($event);
           }
       }
       else {
           if(method_exists($this, $event['type'].'Callback')){
               $this->{$event['type'].'Callback'}($event);
           }
       }
    }
  }

  private function getRuleMessage() {
      return "Silakan kirim pesan \"MULAI\" untuk memulai kuis.
      \nAtau kirim pesan \"SESI\" untuk memilih sesi pembelajaran.";
  }

  private function sendMessage($event, $message) {
      $textMessageBuilder = new TextMessageBuilder($message);
      $this->bot->pushMessage($event['source']['userId'], $textMessageBuilder);
  }

  private function sendSticker($event, $packageId, $stickerId) {
      $stickerMessageBuilder = new StickerMessageBuilder($packageId, $stickerId);
      $this->bot->pushMessage($event['source']['userId'], $stickerMessageBuilder);
  }

  private function followCallback($event) {
      $res = $this->bot->getProfile($event['source']['userId']);
      if ($res->isSucceeded()) {
        $profile = $res->getJSONDecodedBody();
        // save user data
        $this->model_berhitung->saveUser($profile);

        // send welcome message
        $message = "Halo. Salam kenal, " . $profile['displayName'] . "!\n";
        $message .= $this->getRuleMessage();
        $this->sendMessage($event, $message);
        $this->sendSticker($event, 1, 13);
    }
  }

  private function textMessage($event) {
        $userMessage = $event['message']['text'];
        if($this->user['number'] == 0) {
            if(strtolower($userMessage) == 'mulai') {
                // reset score
                $this->model_berhitung->setScore($this->user['user_id'], 0);
                $lastSession = $this->model_berhitung->getLastSession($this->user['user_id']);
                $this->sendMessage($event, "Anda sedang berada di Sesi Belajar \"$lastSession\"");

                // send question
                $this->sendQuestion($event, $this->user['user_id']);
            } elseif(strtolower($userMessage) == 'sesi') {

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
                $response = $this->bot->pushMessage($this->user['user_id'], $messageBuilder);

            } elseif(strtolower($userMessage) == 'penjumlahan') {

            } elseif(strtolower($userMessage) == 'pengurangan') {

            } elseif(strtolower($userMessage) == 'perkalian') {

            } else {
                $textMessageBuilder = new TextMessageBuilder($this->getRuleMessage());
                $this->bot->pushMessage($event['source']['userId'], $textMessageBuilder);
            }

        // if user already begin test
        } else {
            $this->checkAnswer($event, $userMessage);
        }
    }

    public function sendQuestion($event, $user_id, $session) {
        // get question from database
        $question = $this->model_berhitung->getQuestion($user_id);
        $message = "Berapakah hasil dari ". strtoupper($session) ." berikut ini? :\n " . $question . " = .....";
        $this->sendMessage($event, $message);
    }

    private function checkAnswer($event, $message) {

        // if answer is true, increment score
        $result = $this->model_berhitung->isAnswerEqual($this->user['number'], $message);
        if($result['levelUpdated']){

        } elseif ($result['isEqual']) {
            $this->sendSticker($event, $this->getStickerRandom('happy'));
            $this->sendMessage($event, "Jawaban Kamu benar.\n Lanjutkan!!");
        } else {
            $this->sendSticker($event, $this->getStickerRandom('sad'));
            $this->sendMessage($event, "Jawaban Kamu salah.\nJawaban yang benar adalah \"". $result['answer'] ."\"");
        }

        $this->sendQuestion($event, $this->user['user_id'], $result['session']);
    }

    private function getStickerRandom($stickerName) {
      return self::STICKER[$stickerName][rand(0, count(self::STICKER[$stickerName]))];
    }
}
