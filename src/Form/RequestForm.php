<?php

namespace Drupal\simpest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\simpest\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Class RequestForm.
 */
class RequestForm extends FormBase {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  protected $configPath = __DIR__ . '/../../config/FormValues';
  protected $scanDirs = ['clientFiles' => 'Clients', 'postDataFiles' => 'PostData'];

  /**
   * Constructs a new RequestForm object.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Gets the names and paths to the yaml files in a specified directory.
   *
   * @param $location
   *   The name of the directory in which the yaml files are stored.
   *
   * @return array
   *   Array of filenames keyed by their full paths.
   */
  protected function getSelectOptions($location) {
    $files = [];
    foreach ($this->scanDirs as $name => $dir) {
      if ($location == $dir) {
        $files = file_scan_directory(implode('/', [
          $this->configPath,
          $dir
        ]), '/.*\.yml/');
      }
    }
    $options = [];
    foreach ($files as $file) {
      $options[$file->uri] = $file->filename;
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => [
        'get' => 'get',
        'post' => 'post',
        'patch' => 'patch',
      ],
      '#weight' => '0',
    ];
    $form['client_config'] = [
      '#type' => 'select',
      '#title' => $this->t('Client config'),
      '#description' => $this->t('Select the configuration for the OAuth2 Client'),
      '#options' => $this->getSelectOptions('Clients'),
      '#weight' => '0',
    ];
    $form['post_data'] = [
      '#type' => 'select',
      '#title' => $this->t('Post Data'),
      '#description' => $this->t('Select the data to send as the body'),
      '#options' => $this->getSelectOptions('PostData'),
      '#weight' => '0',
      '#states' => array(
        'invisible' => array(
          'select[name="method"]' => array('value' => 'get'),
        ),
      ),
    ];
    $form['server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => 'http://localhost/lightning_api/docroot',
      '#weight' => '0',
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#default_value' => 'jsonapi/node/page'
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $request = new Request($values['server'], $this->httpClient);

    // Get and set token from provided client config.
    $client_config = $this->getRequestDataFromFile($values['client_config'], 'form_params');
    $request->setClientOptions($client_config);
    $request->getAndSetToken();

    if ($values['method'] != 'get') {
      $post_data = $this->getRequestDataFromFile($values['post_data'], 'data');
      $request->setPostData($post_data);
    }

    $response = $request->request($values['endpoint'], $values['method']);

    // Give some output based on the request type.
    if ($values['method'] == 'get') {
      drupal_set_message(serialize($response));
    }
    else {
      drupal_set_message('Data: ' . $values['method']);
      drupal_set_message('UUID: ' . $response->data->id);
    }
  }

  /**
   * Gets contents of a yaml file and optionally wraps it in an array key.
   * @param $filepath
   *   Path to a yaml file containing config.
   * @param null $wrapper
   *   Array key in which to wrap the data contained in the file.
   * @return array|mixed
   */
  protected function getRequestDataFromFile($filepath, $wrapper = null) {
    $data = Yaml::parseFile($filepath);
    if ($wrapper) {
      $data = [$wrapper => $data];
    }
    return $data;
  }

}
