<?php
/**
 * 简单封装的阿里云rabbitMQ收发消息类
 * @author admin 2024-06-15
 */

namespace Rmq1999\RmqAliyun;

use ErrorException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;


class AliRabbitMQ
{
    private $host;
    private $port;
    private $virtualHost;
    private $accessKey;
    private $accessSecret;
    private $instanceId;
    private $connection;
    private $channel;

    private $heartbeat = 1;

    /*
     * rabbitmq client 向Server发起connection,新建channel大约需要进行15+个TCP报文的传输，会消耗大量网络资源和Server端的资源，甚至引起Server端SYN flooding 攻击保护。
     * 因此我们建议消息的发送和消费尽量采用长链接的模式。
     * 对于php，可以采用rabbitmq提供的AMQPProxy来实现的长链接-参考https://github.com/cloudamqp/amqproxy
     */
    function __construct($host, $port, $virtualHost, $accessKey, $accessSecret, $instanceId)
    {

        $this->host        = $host;
        $this->port        = $port;
        $this->virtualHost = $virtualHost;
        $this->accessKey   = $accessKey;;
        $this->accessSecret = $accessSecret;;
        $this->instanceId = $instanceId;
        $this->connection = $this->getConnection();
        $this->channel    = $this->connection->channel();

    }

    private function getUser(): string
    {
        $t = '0:' . $this->instanceId . ':' . $this->accessKey;
        return base64_encode($t);
    }

    private function getPassword(): string
    {
        $ts    = (int)(microtime(true) * 1000);
        $value = utf8_encode($this->accessSecret);
        $key   = utf8_encode((string)$ts);
        $sig   = strtoupper(hash_hmac('sha1', $value, $key, FALSE));
        return base64_encode(utf8_encode($sig . ':' . $ts));
    }


    protected function getConnection(): AMQPStreamConnection
    {
        $username = $this->getUser();
        $password = $this->getPassword();
        return new AMQPStreamConnection($this->host, $this->port, $username, $password, $this->virtualHost, false,
                                        'AMQPLAIN', null, 'en_US', 3.0, 3.0, null, false, $this->heartbeat);
    }

    public function send($message, $queueName = 'queue')
    {
        // 生成唯一message_id
        $messageId = md5(uniqid() . time());
        $msg       = new AMQPMessage($message, ['message_id' => $messageId]);
        $this->channel->queue_declare($queueName, false, false, false, false);
        $this->channel->basic_publish($msg, '', $queueName);
        $this->channel->close();
        $this->connection->close();
    }

    public function sendWithProp($message, $queueName = 'queue', $delay = 0)
    {
        $amqpTable = new AMQPTable(["delay" => $delay]);
        $messageId = md5(uniqid() . time());
        $msg       = new AMQPMessage($message, ['application_headers' => $amqpTable, 'content_type' => 'text/plain', 'delivery_mode' => 2, 'message_id' => $messageId]); //生成消息
        $this->channel->queue_declare($queueName, false, false, false, false);
        $this->channel->basic_publish($msg, '', $queueName);
        $this->channel->close();
        $this->connection->close();
    }

    /**
     *
     *
     * @param string $queueName
     * @param        $callback
     *
     * @return void
     * @throws ErrorException
     * @author admin 2024-06-15
     */
    public function receive(string $queueName = 'queue', $callback = null)
    {
//        $callback = function ($msg) {
//            //echo ' [x] Received ', $msg->body, "\n";
//            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
//        };
        $this->channel->queue_declare($queueName, false, false, false, false);
        $this->channel->basic_consume($queueName, '', false, true, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->channel->close();
        $this->connection->close();
    }

    /**
     *
     *
     * @param string $queueName
     * @param $callback
     *
     * @return void
     * @throws ErrorException
     * @author admin 2024-06-15
     */
    public function receiveWithProp(string $queueName = 'queue', $callback = null)
    {
//        $callback = function ($msg) {
//            //echo ' [x] Received ', $msg->body, "\n";
//            $headers = $msg->get('application_headers');
//            //echo ' [x] Received ', $headers->getNativeData()['delay'], "\n";
//            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
//        };
        $this->channel->queue_declare($queueName, false, false, false, false);
        $this->channel->basic_consume($queueName, '', false, true, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->channel->close();
        $this->connection->close();
    }
}