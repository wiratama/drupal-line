<?php
namespace Drupal\linebot\Form\BE;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

class LineBotSettingForm extends ConfigFormBase {
	public function getFormId() {
		return 'linebot_settings';
	}

	protected function getEditableConfigNames() {
		return [
			'linebot.settings',
		];
	}

	public function buildForm(array $form, FormStateInterface $form_state) {
		$config = $this->config('linebot.settings');

		$form['linebot.google_map_api'] = array(
			'#type' 	=> 'details',
			'#title' 	=> $this->t('Google map key'),
			'#open' 	=> true,

			'public_key'	=> [
				'#type' => 'textfield',
				'#title' => $this->t('Api key'),
				'#default_value' => $config->get('linebot.google_map_api.public_key'),
			],
			'secret_key'	=> [
				'#type' => 'textfield',
				'#title' => $this->t('Api key'),
				'#default_value' => $config->get('linebot.google_map_api.secret_key'),
			],
		);

		return parent::buildForm($form, $form_state);
	}

	public function submitForm(array &$form, FormStateinterface $form_state) {
		$field=$form_state->getValues();
		\Drupal::configFactory()->getEditable('linebot.settings')
			->set('linebot.google_map_api.public_key', $form_state->getValue('public_key'))
			->set('linebot.google_map_api.secret_key', $form_state->getValue('secret_key'))

			->save();
		
		parent::submitForm($form, $form_state);
	}

	public function defaultConfiguration() {
		$default = \Drupal::config('linebot.settings');
		return [
			'linebot.google_map_api.public_key' => $default->get('linebot.google_map_api.public_key'),
			'linebot.google_map_api.secret_key' => $default->get('linebot.google_map_api.secret_key'),
		];
	}
}
