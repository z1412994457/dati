<?php
echo "<pre>";
$starttime = microtime(TRUE);
system("cls");
$start = microtime(TRUE);
require_once './aip-php-sdk-2.1.0/AipOcr.php';

// 你的 APPID AK SK
const APP_ID = '10698847';
const API_KEY = 'ceRxaSZU9UXt51o7i1nqBAwo';
const SECRET_KEY = 'jgD9oGo35jOIXtMpjMFBXi06XbQfDpvC';
const DEV = false;

// 获取答题APP简称（百万英雄为bw，冲顶大会为cd，芝士超人为zs）
$type = !empty($argv['1'])?$argv['1']:'bw';

$client = new AipOcr(APP_ID, API_KEY, SECRET_KEY);
system("adb shell screencap -p /sdcard/screenshot_1.png");
system("adb pull /sdcard/screenshot_1.png  screenshot_1.png");
system("adb shell rm /sdcard/screenshot_1.png");
//exit;
$src_img = './screenshot_1.png';
$src_croped = './cut.png';
function get_count($text)
{
    $text = str_replace(',', '', $text);
    $count = trim($text,'<span class="nums_text">百度为您找到相关结果约 个</span>');
    return $count;
}
if(filesize($src_img)>0){
    $img_size = getimagesize($src_img);
}else{
    echo red('请以开发者模式连接电脑'),PHP_EOL;die;
}

$w = $img_size[0];
$h = $img_size[1];

// 剪裁
$source = imagecreatefrompng($src_img);
$croped = imagecreatetruecolor($w, $h);
if($type=='cd'){
    // 冲顶大会
    imagecopy($croped, $source, 0, 0, 50,300, $w-100,680);
}else if($type=='zs'){
    // 芝士超人
    imagecopy($croped, $source, 0, 0, 50,300, $w-100,800);
}else{
    // 百万英雄  $type=='bw'
    imagecopy($croped, $source, 0, 0, 0,150, $w-50,800);
}
// 保存
imagepng($croped, $src_croped);
imagedestroy($croped);

$image = file_get_contents($src_croped);


/**
//1.使用固定的公式计算新的宽高
$x = $w/2;
$y = $h/2;
//2.生成目标图像资源
$small = imagecreatetruecolor($x,$y);

//3.进行缩放
imagecopyresampled($small,$source,0,0,0,0,$x,$y,$w,$h);

// 保存
imagepng($small, $src_small_img);
imagedestroy($small);
 */


$respon = $client->basicAccurate($image);
$titles = $respon['words_result'];
$ans = '';
$tmp = [];
foreach ($titles as $k => $v) {
    $tmp[] = $v['words'];
}
// 所有选项
$select = array_slice($tmp,count($tmp)-3,3);
// 问题内容
$ans = implode('', array_slice($tmp,0,count($tmp)-3));
// 剔除问题字符串左侧序号
$ans = preg_replace('/^[1-9]\\d*|\\./u', '', $ans);
// 剔除问题字符串？
$ans = trim($ans,'?');
echo '问题：',$ans,PHP_EOL;
echo PHP_EOL,'以下为搜索结果：',PHP_EOL;
$after_baidu = microtime(TRUE);
echo PHP_EOL,PHP_EOL,'Baidu程序用时：'.($after_baidu-$start).'秒',PHP_EOL;
$pmi = $a_count = $q_count = $qa_count = [];
$post1=[];
$match=[];
$cont=[];
$urls =[
    '0'=>'http://www.baidu.com/s?wd='.urlencode($ans),
];
foreach ($select as $k => $v){
    $urls[$k+1]='http://www.baidu.com/s?wd='.urlencode($ans.$v);
}
// 用curl抓取百度内容
$mh = curl_multi_init();
foreach ($urls as $k =>$v){
    $curl[$k] = curl_init($v);
    $header = array (
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Cookie: BAIDUID=8FED4D50E991C57623BB494286257D1B:FG=1; BIDUPSID=8FED4D50E991C57623BB494286257D1B; PSTM=1576992383; BD_UPN=1a314753; H_PS_PSSID=; BDORZ=FFFB88E999055A3F8A630C64834BD6D0; Hm_lvt_4e5bdf78b2b9fcb88736fc67709f2806=1576992386; BDRCVFR[EsW7bJxgFS0]=mk3SLVN4HKm; delPer=0; BD_CK_SAM=1; PSINO=6; H_PS_645EC=5d1f0U%2BvpQbyK2oJzvcyxpjbiKbanVOMfgErdlMOFSjHI%2Ft8RW9nvvm8%2B0Ie53Uf3X9z0lU; BDSVRTM=0; Hm_lpvt_4e5bdf78b2b9fcb88736fc67709f2806=1576997420; ZD_ENTRY=baidu',
        'Host: www.baidu.com',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.25 Safari/537.36 Core/1.70.3741.400 QQBrowser/10.5.3863.400'
    );
    curl_setopt ($curl[$k], CURLOPT_HTTPHEADER, $header );
    curl_setopt($curl[$k], CURLOPT_HEADER, 0);  //0表示不输出Header，1表示输出
    curl_setopt($curl[$k], CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl[$k], CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl[$k], CURLOPT_SSL_VERIFYHOST, false);
    curl_multi_add_handle($mh, $curl[$k]);

}
do {
    $n=curl_multi_exec($mh,$active);
} while ($active);
foreach ($urls as $k=>$v ){
    curl_error($curl[$k]);
    $cont[$k] =curl_multi_getcontent($curl[$k]);
    curl_close($curl[$k]);
    curl_multi_remove_handle($mh , $curl[$k]);
}

curl_multi_close($mh);

foreach ($cont as $k =>$v){
    if($k>=1){
        $regex4="/<span class=\"nums_text\".*?>.*?<\/span>/ism";
        preg_match($regex4,$v,$match[$k]);
        $qa_count[$k] = get_count($match[$k][0]);
    }else if($k==0){

        $regex4="/<div class=\"c-abstract.*?\" .*?>.*?<\/div>/ism";
        preg_match($regex4,$v,$post1);
        $regex4="/<h3 class=\"t\">.*?<\/h3>/ism";
        preg_match($regex4,$v,$t);
        $post1=$t[0].$post1[0];
        if($post1==''){
            $regex4="/<div class=\"c-span24 c-span-last\" *?>.*?<\/div>/ism";
            preg_match($regex4,$v,$post1);
            $regex4="/<h3 class=\"t\">.*?<\/h3>/ism";
            preg_match($regex4,$v,$t);
            echo '详细内容:<div style="height:100px;width:1200px;color:red;overflow: hidden">'.$t[0].$post1[0].'</div></div>';
        }
            echo '详细内容:<div style="height:100px;width:1200px;color:red;overflow: hidden">'.$post1.'</div></div>';

    }
}

$endtime = microtime(TRUE);
$time = $endtime -$starttime;
echo $time;
echo '
========================
';
if($qa_count[1] >$qa_count[2]){
    if($qa_count[1]>$qa_count[3]){
        echo "答案：1";
    }else{
        echo "答案: 2";
    }
}elseif ($qa_count[2] > $qa_count[3]){
    echo "答案: 2";

}elseif ($qa_count[3] >$q_count[2]){
    echo "答案: 3";
}
