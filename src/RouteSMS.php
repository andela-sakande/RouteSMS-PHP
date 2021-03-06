<?php

namespace Sirolad;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class RouteSMS
{
    /**
     * RouteSMS username
     *
     * @var $username
     */
    private $username;

    /**
     * RouteSMS password
     *
     * @var $password
     */
    private $password;

    /**
     * Guzzle client
     * @var $client
     */
    private $client;

    /**
     * Status constants
     */
    const SUCCESS = 1701;
    const INVALID_USERNAME_PASSWORD = 1703;
    const INVALID_TYPE = 1704; //Invalid value in "type" field
    const INVALID_MESSAGE = 1705;
    const INVALID_RECIPIENT = 1706;
    const INVALID_SENDER = 1707;
    const INVALID_DLR = 1708;
    const USER_VALIDATION_ERROR = 1709;
    const INTERNAL_ERROR = 1710;
    const INSUFFICIENT_CREDIT = 1025;
    const DND_NUMBER = 1032;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->client = new Client([
            'base_uri' => 'http://ngn.rmlconnect.net/bulksms/',
            'timeout' => 15
        ]);
    }

    /**
     * @param string $sender
     * @param string $recipient
     * @param $message
     * @param int $type
     * @param int $dlr
     * @return array
     * @throws \Exception
     */
    public function send(string $sender, string $recipient, $message, $type = 0, $dlr = 1)
    {
        if (!$message) {
            throw new \Exception('Message is required');
        }

        if ($recipient && $sender && $message) {
            $fragment = [
                'username' => $this->username,
                'password' => $this->password,
                'type' => $type,
                'dlr' => $dlr,
                'destination' => trim($recipient),
                'source' => $sender,
                'message' => trim($message)
            ];

            $url = 'bulksms?' . http_build_query($fragment);

            try {
                $response = $this->client->request('GET', $url);
                $response = $response->getBody()->getContents();
                //Handle bulk response
                if (strpos($response, ',')) {
                    $bulks = explode(',', $response);
                    foreach ($bulks as $bulk) {
                        $totalBulk []= $this->transformResponse($bulk);
                    }
                    return $totalBulk;
                }
                $result = explode('|', $response);

                switch ($result[0]) {
                    case (self::SUCCESS || self::DND_NUMBER):
                        return [$this->transformResponse($response)];
                        break;
                    case self::INVALID_USERNAME_PASSWORD:
                        throw new \Exception('Invalid username or password supplied');
                        break;
                    case self::INVALID_TYPE:
                        throw new \Exception('Invalid type supplied.');
                        break;
                    case self::INVALID_MESSAGE:
                        throw new \Exception('Invalid message. Message contains invalid characters');
                        break;
                    case self::INVALID_RECIPIENT:
                        throw new \Exception('Invalid recipient. Recipient must be numeric');
                        break;
                    case self::INVALID_SENDER:
                        throw new \Exception('Invalid sender. Sender must not be more than 11 characters');
                        break;
                    case self::INVALID_DLR:
                        throw new \Exception('Invalid dlr supplied');
                        break;
                    case self::USER_VALIDATION_ERROR:
                        throw new \Exception('User validation error');
                        break;
                    case self::INTERNAL_ERROR:
                        throw new \Exception('Internal error');
                        break;
                    case self::INSUFFICIENT_CREDIT:
                        throw new \Exception('Insufficient credit');
                        break;
                    default:
                        throw new \Exception('An error occurred with code ' .  $result[0]);
                }

            } catch (TransferException $e) {
                throw new \Exception('The following error occurred ' . $e->getMessage());
            }
        }
    }

    /**
     * @param $response
     * @return $array
     */
    protected function transformResponse(string $response)
    {
        $single = explode('|', $response. '|');

        list($status, $recipient, $messageId) = $single;

        $array = [
            "status" => $status,
            "recipient" => $recipient,
            "messageId" => $messageId
        ];

        return $array;

    }
}