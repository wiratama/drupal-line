<?php

namespace Drupal\linebot\Controller\BE;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

class LineBotCustomerController extends ControllerBase {
    public function index() {
        $header_table = array(
            'phone' => t('Phone'),
            'line_id'=>t('Line ID'),
            'opt' => t('operations'),
            'opt1' => t('operations'),
        );

        // check submit request
        $input_filter = [];
        if(!empty(\Drupal::request()->query->get('phone'))) {
            $input_filter['phone'] = (string)\Drupal::request()->query->get('phone');
        }
        
        $query = \Drupal::database()->select('linebot_customer', 'm');
        $query->fields('m');
        $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
        
        if(!empty($input_filter)) {
            // phone
            if(array_key_exists('phone', $input_filter)) {
                if(!empty($input_filter['phone'])) {
                    $query = $query->condition('phone', '%'.$input_filter['phone'].'%', 'LIKE');
                }
            }
        }    
        $results = $query->execute()->fetchAll();

        $rows = [];
        foreach($results as $data){
            $delete = Url::fromUserInput('/admin/structure/linebot/delete-customer/'.$data->customer_id);
            $edit   = Url::fromUserInput('/admin/structure/linebot/add-customer/'.$data->customer_id);

            $rows[] = array(
                'phone' => $data->phone,
                'line_id'=> $data->line_id,
                \Drupal::l('Delete', $delete),
                \Drupal::l('Edit', $edit),
            );
        }

        $form['form'] = [
            '#type'  => 'form',
            '#method' => 'get',
            'filters' => [
                '#type'  => 'fieldset',
                '#title' => $this->t('Filter'),
                '#open'  => true,
                
                // phone
                'phone'   =>[
                    '#title'    => 'Phone',
                    '#name'     => 'phone',
                    '#type'     => 'search',
                    '#value'    => (isset($input_filter['phone'])) ? $input_filter['phone']:'',
                ],

                // action
                'actions'   =>[
                    '#type'      => 'actions',
                    'submit'     =>[
                        '#type'  => 'submit',
                        '#value' => $this->t('Submit')
                    ]
                ],
            ],
        ];
        
        $form[] = array(
            '#theme' => 'table', // you can write #type also instead of #theme.
            '#attributes' => array(
                'id' => array(
                    'promotion-table',
                ),
                'class' => array(
                    'promotion-table',
                ),
            ),
            '#header' => $header_table,
            '#empty' => t('No data found'),
            '#rows' => $rows,
        );
        $form[] = ['#type' => 'pager'];

        return $form;
    
    }

    public function autocompleteProduct(Request $request) {
        $nids = $results = [];

        if ($input = $request->request->get('q')) {
            $input =  Html::escape(Xss::filter($input));
            $input = Tags::explode($input);
            $input = Unicode::strtolower(array_pop($input));

            $query = \Drupal::entityQuery('node')
                ->condition('status', 1)
                ->condition('type','produk')
                ->condition('title', $input, 'CONTAINS')->range(0, 10);
            $nids = $query->execute();

            if(!empty($nids)) {
                $nodes = Node::loadMultiple($nids);
                foreach($nodes as $node) {
                    $nid = $node->get('nid')->getString();
                    $path = Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString();
                    $url = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();
                    $results[$nid] = [
                        'product_id'     =>$nid,
                        'product_title'  =>$node->get('title')->getString(),
                    ];
                }
            }
        }

        return new JsonResponse($results);
    }
}