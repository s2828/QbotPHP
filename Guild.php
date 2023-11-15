<?php

namespace App\Libs\Guild;

use App\Jobs\GuildMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Middleware\PingResponder;

class Guild
{
    private $qqGuildUrl = '';
    private $token = '';
    private $guzzleOptions = [];
    private $s = '';
    private $session_id = '';
    private $time0 = 0;
    private $seconds = 0;

    public function __construct(String $qqGuildUrl, String $token, Array $guzzleOptions)
    {
        set_time_limit(0);
        ini_set('memory_limit','-1');
        $this->qqGuildUrl = $qqGuildUrl;
        $this->token = $token;
        $this->guzzleOptions = $guzzleOptions;
    }

    /**
     * @param $token
     * @return mixed
     * 获取Gateway
     */
    private function getGateway(String $token): string
    {
        $response = $this->constructHttp($token)->get($this->constructUrl('/gateway'));
        return $response['url'];
    }

    /**
     * @param String $uri
     * @return string
     * 构造请求URL
     */
    private function constructUrl(String $uri): string
    {
        return $this->qqGuildUrl.$uri;
    }

    /**
     * @param String $token
     * @return \Illuminate\Http\Client\PendingRequest
     * 构造HTTP请求
     */
    private function constructHttp(String $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions($this->guzzleOptions)->withHeaders(['Authorization'=>$token]);
    }

    /**
     * @param String $token
     * @param Client $client
     * @return String
     * WS身份验证，返回SessionID
     */
    private function identify(String $token, Client $client): string
    {
        $data = [
            'op' => 2,
            'd' => [
                'token' => $token,
                'intents' => 2081166851,
//                'shard' => [0, 1],
                'properties' => []
            ]
        ];
        $client->text(json_encode($data));
        $receive = $client->receive()->getContent();
        //var_dump($receive);
        return json_decode($receive,1)['d']['session_id'];
    }

    /**
     * 建立WS连接
     */
    public function connect()
    {

        //获取WS连接路径
        $gateway = $this->getGateway($this->token);

        //创建连接
        $this->s = '';

        $client = new Client($gateway);

        //获取心跳间隔
        $this->seconds = intval($this->getHeartBeat($client));

        //身份鉴权
        $this->session_id = $this->identify($this->token, $client);


        //首次心跳
        $this->time0 = time();
        $client->text(json_encode(['op'=>1, 'd'=>null]));

        //消息监听
        $client
            ->setTimeout($this->seconds)
            // Add standard middlewares
            ->addMiddleware(new PingResponder())
            // Listen to incoming Text messages
            ->onText(function (Client $client, Connection $connection, Message $message) {
                //接收消息
                $receive = $message->getContent();
                //将消息转换为数组
                $receiveArr =json_decode($receive, 1);

                //如果op存在
                if (isset($receiveArr['op'])){

                    //排除心跳pong
                    //if($receiveArr['op']!=11){}

                    //如果是服务端推送，将消息派发到队列处理
                    if($receiveArr['op']==0){
                        GuildMessage::dispatch($this->qqGuildUrl, $this->token, $this->guzzleOptions, $receiveArr);
                    }

                    //如果服务端通知重连
                    if($receiveArr['op'] == 7){
                        $client->text(json_encode(['op'=>6, 'd'=>['token'=>$this->token, 'session_id'=>$this->session_id, 's'=>Redis::get('s')]]));
                    }

                }


            })
            ->onTick(function (Client $client){

                //检测是否到心跳时间
                $time1 = time();
                if($time1 - $this->time0 > $this->seconds - 20){
                    $client->text(json_encode(['op'=>1, 'd'=>Redis::get('s')]));
                    $this->time0 = $time1;
                    //Storage::append('heart.log',$time1);
                }

            })
            ->onError(function (Client $client){
                //重新连接
                $client->text(json_encode(['op'=>6, 'd'=>['token'=>$this->token, 'session_id'=>$this->session_id, 's'=>$this->s]]));
            })
            ->start();

    }

    /**
     * @param $client
     * @return float
     * 获得心跳时间
     */
    public function getHeartBeat($client){
        $receive = $client->receive()->getContent();
        $initReceive = json_decode($receive,1);
        return floor($initReceive['d']['heartbeat_interval']/1000);
    }




}
