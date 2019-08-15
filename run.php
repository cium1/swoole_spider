<?php
/**
 * User:    Yejia
 * Email:   ye91@foxmail.com
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'Http.php';

//初始化变量
$requestUrl = 'http://huaban.com/partner/uc/aimeinv/pins/';
$imageUrl = 'http://hbimg.b0.upaiyun.com/';
$dir = __DIR__ . DIRECTORY_SEPARATOR . 'images/';

//初始化管道
$requestChan = new Chan(100);
$parserChan = new Chan(100);
$downloadChan = new Chan(100);

//入口Url
go(function () use ($requestChan, $requestUrl) {
    $requestChan->push($requestUrl);
});

//Worker
for ($i = 1; $i <= 100; $i++) {

    go(function () use ($requestChan, $parserChan) {
        while (true) {
            $url = $requestChan->pop();
            get_html($url, $parserChan);
        }
    });

    go(function () use ($parserChan, $requestChan, $downloadChan, $requestUrl) {
        while (true) {
            $html = $parserChan->pop();
            parse($html, $downloadChan, $requestChan, $requestUrl);
        }
    });

    go(function () use ($downloadChan, $dir, $imageUrl) {
        while (true) {
            download($downloadChan->pop(), $dir, $imageUrl);
        }
    });

}

//挂载
swoole_event_wait();

/**
 * 获取内容
 *
 * @param string $url  Url地址
 * @param Chan   $chan 解析管道
 */
function get_html(string $url, Chan $chan)
{
    $chan->push(Http::get($url));
}

/**
 * 下载文件
 *
 * @param string $url      文件Url
 * @param string $dir      存储目录
 * @param string $imageUrl 文件基础Url
 */
function download(string $url, string $dir, string $imageUrl)
{
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    if (!strpos($url, '-')) {
        return;
    }
    if (substr($url, 0, 2) == '//') {
        $url = 'http:' . $url;
    }
    Http::download($imageUrl . $url, $dir . $url . '.png');
    echo $dir, $url, '.png', PHP_EOL;
}

/**
 * 解析数据
 *
 * @param string $html         html内容
 * @param Chan   $downloadChan 下载管道
 * @param Chan   $requestChan  请求管道
 * @param string $requestUrl   请求Url
 */
function parse(string $html, Chan $downloadChan, Chan $requestChan, string $requestUrl)
{
    //解析图片
    preg_match_all('/"key":"(.*?)"/', $html, $images);
    if (!empty($images) && !empty($images[1])) {
        foreach ($images[1] as $image) {
            $downloadChan->push($image);
        }
    }
    //解析下一页
    preg_match_all('/"pin_id":(\d+),/', $html, $next);
    if (!empty($next) && !empty($next[1])) {
        $requestChan->push($requestUrl . "?max=" . end($next[1]) . "&limit=8&wfl=1");
    }
}