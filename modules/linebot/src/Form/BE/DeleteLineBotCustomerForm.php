<?php

namespace Drupal\linebot\Form\BE;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Element;

class DeleteLineBotCustomerForm extends ConfirmFormBase {
    public function getFormId() {
        return 'linebot_delete_form';
    }

    public $cid;

    public function getQuestion() { 
        return t('Do you want to delete %cid?', array('%cid' => $this->cid));
    }
  
    public function getCancelUrl() {
        return new Url('linebot.be-customer-index');
    }
    
    public function getDescription() {
        return t('Only do this if you are sure!');
    }

    public function getConfirmText() {
        return t('Delete it!');
    }

    public function getCancelText() {
        return t('Cancel');
    }

    public function buildForm(array $form, FormStateInterface $form_state, $cid = NULL) {
        $this->id = $cid;
        return parent::buildForm($form, $form_state);
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
    }


    public function submitForm(array &$form, FormStateInterface $form_state) {
        $query = \Drupal::database();
        $query->delete('linebot_customer')
            ->condition('customer_id',$this->id)
            ->execute();
        if($query == TRUE){
            drupal_set_message("succesfully deleted");
        } else{
            drupal_set_message(" not succesfully deleted");
        }
        $form_state->setRedirect('linebot.be-customer-index');
    }
    
}