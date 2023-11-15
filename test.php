<?php
$qqGuildUrl='https://api.sgroup.qq.com';
$token = 'Bot id.asadsada....';
$guzzleOptions = ['verify' => false];
$guild = new \App\Libs\Guild\Guild($qqGuildUrl, $token, $guzzleOptions);
$guild->connect();
