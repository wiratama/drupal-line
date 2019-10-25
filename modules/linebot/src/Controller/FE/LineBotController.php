<?php

namespace Drupal\linebot\Controller\FE;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

// drupal component
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;

// symfony http foundation
use Symfony\Component\HttpFoundation\JsonResponse;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\SignatureValidator as SignatureValidator;


use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;

class LineBotController extends ControllerBase {
    protected $state;

    public function __construct()
    {
        $this->state = [
            '1' => 'first_follow',
            '2' => 'send_otp',
            '3' => 'validate_otp',
            '4' => 'offer_subscription',
            '5' => 'paid_subscription',
            '6' => 'list_voucher',
            '7' => 'expired_subscription_reminder',
            '8' => 'expired_subscription',
            '9' => 'extend_subscription',
            '10' => 'paid_extend_subscription',
            '11' => 'show_card_list',
            '12' => 'show_card_detail',
            '13' => 'show_voucher_list',
            '14' => 'show_voucher_used',
            '15' => 'show_expired_subscribtion',
            '16' => 'show_active_subscribtion',
            '17' => 'show_subscribtion',
            '18' => 'redirect_to_tada',
            '19' => 'back_from_tada',
        ];
    }

    public function home()
    {
        echo "index";
        // var_dump(file_exists(getcwd().'/modules/linebot/config/creds'));
        
        die();
    }
    
    public function responseMessage()
    {
        $basic_id = \Drupal::request()->get('bot_id');
        $basic_id = Html::escape(Xss::filter($basic_id));

        $merchant = $this->getMerchantData($basic_id);

        $bot = new LINEBot(new CurlHTTPClient($merchant['channel_access_token']), [
            'channelSecret' => $merchant['channel_secret']
        ]);

        $signature = \Drupal::request()->headers->get(HTTPHeader::LINE_SIGNATURE);
        if (is_null($signature)) {
            return new JsonResponse('Bad Request', 400);
        }
        
        try {
            $events = $bot->parseEventRequest(\Drupal::request()->getContent(), $signature);
        } catch (InvalidSignatureException $e) {
            \Drupal::logger('line_bot')->error($e.'-e1');
            return new JsonResponse('Bad Request', 400);
        } catch (InvalidEventRequestException $e) {
            \Drupal::logger('line_bot')->error($e.'-e2');
            return new JsonResponse('Bad Request', 400);
        }
        
        foreach ($events as $key_event=>$event) {
            $current_state = $this->getCurrentState();
            $state = $current_state;
            $lineId = $event->getUserId();
            
            if (!($event instanceof MessageEvent)) {
                continue;
            }
            
            if (!($event instanceof TextMessage)) {
                continue;
            }
            
            $replyText = $event->getText();
            if ($event instanceof FollowEvent) {
                $state = 1;
            } else {
                $is_regirtered = $this->getuserLineID($lineId);
                if(!$is_regirtered) {
                    $state = 1;
                }
            }
            
            if($current_state==1 and $this->validPhone($input_message)) {
                $state = 2;
            }

            if($current_state==2) {
                $state = 3;
            }
            
            $currentresponse = $this->botResponse($state, $replyText);
            $bot->replyText($event->getReplyToken(), $currentresponse);
        }

        return new JsonResponse('Ok', 200);
    }

    private function getMerchantData($basic_id = null)
    {
        $merchant = [];

        $merchant_data = \Drupal::service('entity.query')->get('node');
        $merchant_data = $merchant_data->condition('type', 'merchant')->condition('field_basic_id', $basic_id)->range(0,1);
        $merchant_nid = $merchant_data->execute();
        $merchants = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($merchant_nid);
        if($merchants) {
            foreach($merchants as $merch) {
                $merchant = [
                    'basic_id'             => $merch->get('field_basic_id')->getString(),
                    'channel_access_token' => $merch->get('field_channel_access_token')->getString(),
                    'channel_id'           => $merch->get('field_channel_id')->getString(),
                    'channel_secret'       => $merch->get('field_channel_secret')->getString(),
                ];
            }
        }

        return $merchant;
    }


    private function getuserLineID($lineID = null)
    {
        $lineID = $basic_id = Html::escape(Xss::filter($lineID));

        $query = \Drupal::database()->select('customer', 'm')
                ->fields('m')
                ->condition('line_id', $lineID);
        $results = $query->execute()->fetch();

        // return $results;
        return true;
    }

    private function botResponse($state = null, $input_message = '')
    {
        $dialogFlowResponse = [];
        $responseDialogFlow = '';
        $linebot_service_dialogflow = \Drupal::service('linebot_service.dialogflow');
        $params = array(
            'text' => $input_message,
        );
        $dialogFlowResponse = $linebot_service_dialogflow->getDialogFlowIntents($params);

        switch ($state) {
            case '1':
                $stateResponse = 'Silakan masukkan nomor handphone Anda untuk melanjutkan. Contoh: +6281312345938';
                $this->setCurrentState($state);
                break;
            
            case '2':
                $stateResponse = 'Masukkan kode verifikasi yang sudah kami kirimkan melalui SMS ke nomer '.$input_message;
                $this->setCurrentState($state);
                break;
            
                case '2':
                $stateResponse = 'Terimakasih. Kami sedang menvalidasi kode otp anda, mohon menunggu';
                $this->setCurrentState($state);
                break;

            default:
                $stateResponse = '';
                break;
        }

        $responseDialogFlow = '';
        if($dialogFlowResponse['intentName']=='greetingIntent') {
            $responseDialogFlow = $dialogFlowResponse['responseText'].'
'.$stateResponse ;
        } else if($dialogFlowResponse['intentName']=='fallBackIntent - fallback') {
                $responseDialogFlow = $dialogFlowResponse['responseText'].'
'.$stateResponse ;
        }
        $responseToUser = $responseDialogFlow.' - '.$state;

        // \Drupal::logger('line_bot')->error($dialogFlowResponse['intentName']);
        // \Drupal::logger('line_bot')->error($state);
        // \Drupal::logger('line_bot')->error($stateResponse);
        

        return $responseToUser;
    }

    private function setCurrentState($state = null)
    {
        $session = \Drupal::request()->getSession();
        $session->set('current_bot_state', $state);

        return true;
    }

    private function getCurrentState()
    {
        $session = \Drupal::request()->getSession();
        $state = $session->get('current_bot_state');

        return ($state) ? $state : 1;
    }

    private function validPhone($phone = ''){
        $patern = "/(\()?(\+62|62|0)(\d{2,3})?\)?[ .-]?\d{2,4}[ .-]?\d{2,4}[ .-]?\d{2,4}/";    
        $is_phone = false;
        if (preg_match($patern, $phone)) {
            $is_phone = true;
        }
    
        return $is_phone;
      }    
}