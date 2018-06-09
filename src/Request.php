<?php

namespace Drupal\simpest;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;

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
   * The token to be sent as part of the header.
   */
  protected $token;

  /**
   * @var array
   *
   * An array of OAuth2 client options.
   */
  protected $clientOptions;

  public function __construct($server, $clientUUID, ClientInterface $client)
  {
    $this->setServer($server);
    $this->setCLientOptions($clientUUID);
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
   * Gets an OAuth2 token from the client sepcified in $this->clientOptions and
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
   */
  public function setServer($server) {
    if (!is_string($server)) {
      throw new \InvalidArgumentException("Server must be a string");
    }
    $this->server = $server;
  }

  /**
   * @param $data
   *   The client options for the consumer.
   */
  public function setClientOptions($data) {
    $this->clientOptions = $data;
  }

  /**
   * @param $data
   *   The data to post or patch.
   */
  public function setPostData($data) {
    $this->data = $data;
  }

  public function getClientOptions() {
    return $this->clientOptions;
  }

}