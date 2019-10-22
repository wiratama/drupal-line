<?php

namespace Drupal\linebot\Form\BE;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\RedirectResponse;

// symfony valdiation
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineBotCustomerForm extends FormBase {
    public function getFormId() {
        return 'linebot_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        $record = [];

        $customer_id =  \Drupal::request()->query->get('customer_id');
        if (isset($customer_id)) {
            $query = \Drupal::database()->select('linebot_customer', 'm')
                ->condition('customer_id', (int)$customer_id)
                ->fields('m');
            $record = $query->execute()->fetchAssoc();
        }

        $form['phone'] = array(
            '#type' => 'textfield',
            '#title' => t('Phone:'),
            '#required' => TRUE,
            '#default_value' => (isset($record['phone']) && $customer_id) ? $record['phone']:'',
        );
    
        $form['line_id'] = array(
            '#type' => 'textfield',
            '#title' => t('Line ID:'),
            '#required' => TRUE,
            '#default_value' => (isset($record['line_id']) && $customer_id) ? $record['line_id']:'',
        );

        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => 'save',
            //'#value' => t('Submit'),
        );
    
        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        $validator = Validation::createValidator();
        
        $not_blak_phone = $validator->validate($form_state->getValue('phone'), [
            new NotBlank(),
        ]);
        if (0 !== count($not_blak_phone)) {
            foreach ($not_blak_phone as $violation) {
                $form_state->setErrorByName('phone', $this->t($violation->getMessage()));
            }
        }

        $not_blak_line_id = $validator->validate($form_state->getValue('line_id'), [
            new NotBlank(),
        ]);
        if (0 !== count($not_blak_line_id)) {
            foreach ($not_blak_line_id as $violation) {
                $form_state->setErrorByName('line_id', $this->t($violation->getMessage()));
            }
        }
        
        parent::validateForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $record = [];
        if (isset($customer_id)) {
            $query = \Drupal::database()->select('linebot_customer', 'm')
                ->condition('customer_id', (int)$customer_id)
                ->fields('m');
            $record = $query->execute()->fetchAssoc();
        }

        $field     = $form_state->getValues();
        $phone     = $field['phone'];
        $line_id   = $field['line_id'];

        // set timezone
        $config = \Drupal::config('system.date');
        date_default_timezone_set($config->get('timezone.default'));
        $request_time = \Drupal::time()->getCurrentTime();
        $created_on = $updated_on = date('Y-m-d H:i:s', $request_time);

        if (isset($customer_id)) {
            $field  = array(
                'phone'        => $phone,
                'line_id'      => $line_id,
                'updated_on'   => $updated_on,
            );
            $query = \Drupal::database();
            $query->update('linebot_customer')
                ->fields($field)
                ->condition('customer_id', (int)$customer_id)
                ->execute();
            drupal_set_message("succesfully updated");
            $form_state->setRedirect('linebot.be-customer-index');
        } else {
            $field  = array(
                'phone'        => $phone,
                'line_id'      => $line_id,
                'created_on'   => $created_on,
                'updated_on'   => $updated_on,
            );
            $query = \Drupal::database();
            $query ->insert('linebot_customer')
                    ->fields($field)
                    ->execute();
            drupal_set_message("succesfully saved");
            $form_state->setRedirect('linebot.be-customer-index');
        }
    }
    
    private function isUnique($value, $field) {
        $value = (string)$value;
        $query = \Drupal::database()->select('linebot_customer', 'cp')
                ->fields('cp', [$field])
                ->condition('cp.'.$field, $value);
        
        $record = $query->execute()->fetchAssoc();

        if(!$record) {
            return true;
        } else {
            return false;
        }
    }
}