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
    public function home() {
        echo "index";
        // var_dump(file_exists(getcwd().'/modules/linebot/config/creds'));
        
        die();
    }
    
    public function responseMessage() {
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
            $lineId = $event->getUserId();
            
            if (!($event instanceof MessageEvent)) {
                continue;
            }
            
            if (!($event instanceof TextMessage)) {
                continue;
            }
            
            $replyText = $event->getText();
            if ($event instanceof FollowEvent) {
                $state = 'greeting';
            } else {
                $is_regirtered = $this->getuserLineID($lineId);
                if(!$is_regirtered) {
                    $state = 'greeting';
                }
            }
            
            $dialogFlowResponse = $this->botState($state, $replyText);
            // $bot->replyText($event->getReplyToken(), $dialogFlowResponse['responseText']);
            $bot->replyText($event->getReplyToken(), 'dialog_flow_data :'.json_encode($dialogFlowResponse));
        }

        return new JsonResponse('Ok', 200);
    }

    private function getMerchantData($basic_id = null) {
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

        return $results;
    }

    private function botState($state = null, $input_message = '')
    {
        $currentState = [];
        
        if($state == 'greeting') {
            $linebot_service_dialogflow = \Drupal::service('linebot_service.dialogflow');
            $params = array(
                'text' => $input_message,
            );
            $currentState = $linebot_service_dialogflow->getDialogFlowIntents($params);
        }

        return $currentState;
    }
}