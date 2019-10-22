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
    
    public function responseMessage() {
        $basic_id = \Drupal::request()->query->get('basic_id');
        $basic_id = Html::escape(Xss::filter($basic_id));

        $merchant = $this->getMerchantData($basic_id);

        $bot = new LINEBot(new CurlHTTPClient($merchant->get('field_channel_access_token')->getString()), [
            'channelSecret' => $merchant->get('field_channel_secret')->getString()
        ]);
        

        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        if (is_null($signature)) {
            return new JsonResponse('Bad Request', 400, ['Content-Type'=> 'application/json']);
        }


        try {
            $events = $bot->parseEventRequest(json_encode(\Drupal::request()->query->all()), $signature);
        } catch (InvalidSignatureException $e) {
            return new JsonResponse('Bad Request', 400, ['Content-Type'=> 'application/json']);
        } catch (InvalidEventRequestException $e) {
            return new JsonResponse('Bad Request', 400, ['Content-Type'=> 'application/json']);
        }

        foreach ($events as $event) {
            if (!($event instanceof MessageEvent)) {
                continue;
            }
            
            if (!($event instanceof TextMessage)) {
                continue;
            }
            
            $replyText = $event->getText();
            $resp = $bot->replyText($event->getReplyToken(), $replyText);
        }

        return new JsonResponse('Ok', 200, ['Content-Type'=> 'application/json']);
    }

    private function getMerchantData($basic_id = null) {
        $merchant_data = \Drupal::service('entity.query')->get('node');
        $merchant_data = $merchant_data->condition('type', 'merchant')->condition('field_basic_id', $basic_id);
        $merchant_nid = $merchant_data->execute();

        $merchant = \Drupal::entityTypeManager()->getStorage('node')->load($merchant_nid);

        return $merchant;
    }
}