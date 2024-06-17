# simple-aliyun-rabbitMQ
A simple RabbitMq that encapsulates Alibaba Cloud

简单封装阿里云rabbitMQ消息发送和接收

## 引用实例化
```php
    /**
    * @param $host 接入点
    * @param $port 端口,一般默认端口5672
    * @param $virtualHost 资源隔离
    * @param $accessKey  阿里云的accessKey
    * @param $accessSecret 阿里云的accessSecret
    * @param $instanceId 实例 id（从阿里云 AMQP 版控制台获取）
    */
    $client = new AliRabbitMQ($host, $port, $virtualHost, $accessKey, $accessSecret, $instanceId);
    
```

## 发送消息send
```php
    $message = json_encode(['data'=>$data,'id'=>$id],JSON_UNESCAPED_UNICODE); //发送的消息 string
    $queueName = "queue"; //队列名
    
    $client->send($message,$queueName);
    
    $delay = 1000; //延时1s
    
    //使用下面的方式发送消息会带有这些参数，
    //['application_headers'=>$amqpTable,'content_type' => 'text/plain', 'delivery_mode' => 2]
    $client->sendWithProp($message,$queueName,$delay);
```
## 接收消息receive
```php
     $callback = function ($msg) {
            //此处定义处理业务逻辑
            echo ' [x] Received ', $msg->body, "\n";
            $headers = $msg->get('application_headers');
            echo ' [x] Received ', $headers->getNativeData()['delay'], "\n";
            
            //必要的消息接收应答，告知服务已接收到消息，否则服务商会继续推送数次消息
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };//队列回调处理函数，自定义处理业务逻辑
        
    $queueName = "queue"; //队列名
    
    $client->receive($queueName,$callback);
    
    //目前和receive方法相同，建议直接使用receive
    $client->receiveWithProp($queueName,$callback);
```