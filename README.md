# QQ频道机器人PHP版实现



需要这个composer包

```shell
composer require phrity/websocket
```



test.php提供了调用方法，会自动创建ws连接监听消息、发送心跳

Guild.php为核心实现文件



```php
消息处理请替换以下代码↓

//如果是服务端推送，将消息派发到队列处理
if($receiveArr['op']==0){
  GuildMessage::dispatch($this->qqGuildUrl, $this->token, $this->guzzleOptions, $receiveArr);
}
```

