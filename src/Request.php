<?php

namespace Drupal\simpest;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\InvalidArgumentException;

/**
 * Authenticates via OAuth2 and makes requests to JSON API endpoints.
 */
class Request {

  /**
   * @var \GuzzleHttp\Client
   *
   * The client.
   */
  protected $client;

  /**
   * @var string
   *
   * The name of the server - including protocol.
   */
  protected $server;

  /**
   * @var array or null.
   *
   * The data to send as the body of the request.
   */
  protected $data;

  /**
   * @var string or null
   *
   * The OAuth2 token to be sent as part of the header.
   */
  protected $token;

  /**
   * @var array
   *
   * An array of OAuth2 client options.
   */
  protected $clientOptions;

  public function __construct($server, ClientInterface $client)
  {
    $this->setServer($server);
    $this->client = $client;
  }

  /**
   * Makes a request to the API using an optional OAuth token.
   *
   * @param string $endpoint
   *   Path to the API endpoint.
   * @param string $method
   *   The RESTful verb.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the request.
   */
  public function request($endpoint, $method = 'get') {
    $client = $this->client;

    $options = NULL;
    if ($this->token) {
      $options = [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->token,
          'Content-Type' => 'application/vnd.api+json'
        ],
      ];
    }
    if ($this->data) {
      $options['json'] = $this->data;
    }

    $url = $this->buildUrl($endpoint);

    $response = $client->$method($url, $options);
    $body = $this->decodeResponse($response);
    return $body;
  }

  /**
   * Gets an OAuth2 token from the client specified in $this->clientOptions and
   * stores it in $this->token.
   */
  public function getAndSetToken() {
    $client = $this->client;
    $url = $this->buildUrl('oauth/token');

    $response = $client->post($url, $this->clientOptions);
    $body = $this->decodeResponse($response);

    return $this->token = $body->access_token;
  }

  /**
   * Decodes a JSON response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   *
   * @return mixed
   *   The decoded response data. If the JSON parser raises an error, the test
   *   will fail, with the bad input as the failure message.
   *
   * @throws \HttpResponseException
   *   If the body doesn't contain an error.
   */
  protected function decodeResponse(ResponseInterface $response) {
    $body = (string) $response->getBody();
    $body = \GuzzleHttp\json_decode($body);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $body;
    }
    else {
      throw new \HttpResponseException("Bad response");
    }
  }

  /**
   * @param $endpoint
   *   The API endpoint.
   *
   * @return string
   *   Concatenated server and endpoint.
   */
  protected function buildURL($endpoint) {
    return implode('/', [$this->server, $endpoint]);
  }

  /**
   * @param $server
   *   The server used in all requests.
   *
   * @throws \Psr\Log\InvalidArgumentException
   *   If the provided server isn't a valid URL.
   */
  public function setServer($server) {
    if (!preg_match('/^https?:\/\/.*/', $server)) {
      throw new \InvalidArgumentException("Server must be a valid URL with protocol. E.g. https://foo.com or http://bar.com");
    }
    $this->server = $server;
  }

  /**
   * @param $data
   *   The client options for the consumer.
   *
   * @throws \Psr\Log\InvalidArgumentException
   *   If the provided client configuration doesn't include the proper keys.
   */
  public function setClientOptions($data) {
    $required_keys = ['grant_type', 'client_id', 'client_secret', 'username', 'password'];
    if (!empty(array_diff($required_keys, array_keys($data['form_params'])))) {
      throw new InvalidArgumentException('Client configuration must include the following keys: grant_type, client_id, client_secret, username, and password');
    }
    $this->clientOptions = $data;
  }

  /**
   * @param $data
   *   The data to post or patch.
   */
  public function setPostData($data) {
    if (!is_array($data)) {
      throw new InvalidArgumentException('Post data must be an array.');
    }
    $this->data = $data;
  }

  public function getClientOptions() {
    return $this->clientOptions;
  }

}