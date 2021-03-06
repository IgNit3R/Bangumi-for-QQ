<?php
/**
 * Created by PhpStorm.
 * User: Cocoa
 * Date: 2018/7/5
 * Time: 0:52
 */
require_once '../access.php';
require_once './dmhy.php';
//access
if(empty($argv[1])) {
    die("No auth");
}
else {
    //access
    constant('password')==$argv[1]?:die("error auth");
    echo "access";
}

//默认只支持个人发送
$type='send_private_msg';
//所有要发送的用户
$send_users=array();
//全列出所有注册信息
$sql="select user_id,user_qq,dmhy_keyword,dmhy_lastpubDate,dmhy_moe
                              from bgm_users
                              where dmhy_open=1";
$result=\access\sql_query($type,"597320012",$sql);
while($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){
    $send_users[]=$row;
}
//依次处理用户消息
for($i=0;$i<count($send_users);++$i){
    //sql读取dmhy信息
    $id=$send_users[$i]['user_id'];
    $to=$send_users[$i]['user_qq'];
    $keyword=$send_users[$i]['dmhy_keyword'];
    $lastpubDate=$send_users[$i]['dmhy_lastpubDate'];
    //True代表dmhy,False代表Moe
    $dmhy_moe=$send_users[$i]['dmhy_moe']==0?true:false;
    //msg
    // $msg="第[".$id."]位魔法少女[".$to."]"
    //     ."\n关键字:\n[".$keyword."]"
    //     ."\n上次更新时间:\n[".$lastpubDate."]"
    //     ."\n";
    $msg="第[{$id}]位魔法少女[{$to}]\n关键字:\n[{$keyword}]\n上次更新时间:\n[{$lastpubDate}]\n";
    //回复标志位
    $need_reply=true;
    $decode_keyword=urlencode($keyword);
    if($dmhy_moe){
        //DMHY RSS
        $url="https://share.dmhy.org/topics/rss/rss.xml?keyword=$decode_keyword";
    }else{
        //Moe RSS
        $url="https://bangumi.moe/rss/search/$decode_keyword";
    }

    //file name
    $file_name="./RSS/{$decode_keyword}.xml";
    //读取或更新
    $rss_file=null;
    //$last_mtime=filemtime($file_name);
    $current_time=date("U");
    //\access\send_msg($type,597320012 ,$last_mtime.'   '.$current_time,constant('token'));
    if(file_exists($file_name)&&($current_time-filemtime($file_name))<500){
    	
    	$rss_file=file_get_contents($file_name);
    }else{
	    //file get
	    $rss_file=file_get_contents($url,0,null,0,120000);
	    //file_put_contents('test.xml',$rss_file);
		//处理xml
		if(false===strrpos($rss_file,"</rss>")){
		    $last_item=strrpos($rss_file,"<item>");
		    $over_last_item=strrpos($rss_file,"</item>");
		    if($over_last_item>$last_item){
		        $last_item=$over_last_item+7;
		    }
		    $rss_file=substr($rss_file,0,($last_item))."</channel></rss>";
		}

	    file_put_contents($file_name,$rss_file);
    }
    //load xml
    if($xml=simplexml_load_string($rss_file)){
        //将 SimpleXMLElement 转化为普通数组
        //$jsonStr = json_encode($xml);
        //$xmlArray = json_decode($jsonStr,true);
        //最后要更新的最新Date
        $need_set_date=$lastpubDate;
        //item计数器
        $itemNum=1;
        //只进行一次
        //当前进度到channel标签下
        foreach($xml->children()->children() as $channel){
            //itemsMsg
            $itemsMsg="";
            //标志全部结束
            $channelOver=false;
            //当前进度到item标签下

            if($channel->getName()=="item"){
                //当前ItemMsg
                $currentItemMsg="\n----------------\n===编号 [ {$itemNum} ]===";
                //一些参数
                $itemOver=false;
                //进行Item解析
                foreach($channel->children() as $item){
                    //$msg=$item->getName().": ".$item;
                    //echo $item->getName() . ": " . $item . "\n\r";
                    switch ($item->getName()){
                        case "title":
                            $currentItemMsg.="\n$item";
                            break;
                        case "link":
                            $currentItemMsg.="\nURL:\n$item";
                            break;
                        case "pubDate":
                            //第一个item最新
                            if($itemNum===1){
                                $time=strtotime($item);
                                $need_set_date=date("Y-m-d H:i:s",$time);
                                //echo  date("Y-m-d H:i:s",$time);
                            }
                            $time=strtotime($item);
                            $currentTime=date("Y-m-d H:i:s",$time);
                            if($time<=strtotime($lastpubDate)){
                                //如果没有任何更新则无需回复
                                if($itemNum===1)
                                    $need_reply=false;
                                $itemOver=true;
                            }elseif($itemNum===1){
                                $need_set_date=date("Y-m-d H:i:s",$time);
                            }
                            $currentItemMsg.="\n--------\n发布时间: $currentTime";
                            //过时消息
                            //$itemOver=true;
                            break;
                        case "description":
                            //$currentItemMsg.="\n描述: ".$item;
                            $pic_url=\dmhy\DescriptionDecode($item);
                            if($pic_url!==false){
                                $currentItemMsg="\n----------------\n[CQ:image,file={$pic_url}]{$currentItemMsg}";
                            }

                            break;
                        case "enclosure":
                            //echo $item->attributes()."\n";
                            if($dmhy_moe){
                                //dmhy
                                $magnet=explode("&",$item->attributes());
                                $currentItemMsg.="\n磁力链接:\n$magnet[0]";
                            }else{
                                //moe
                                $TorrentEncode_result=\dmhy\TorrentEncode($item->attributes());
                                $currentItemMsg.="\n种子链接:\n$TorrentEncode_result";
                            }

                            break;
                        case "author":
                            $currentItemMsg.="\n\n发布人: [ {$item} ]";
                            break;
                        case "category":
                            $currentItemMsg.="\n资源分类: [ {$item} ]";
                            break;
                        default:
                            break;

                    }
                    //echo $itemNum;
                }
                //echo $currentItemMsg."\n\n\n";
                //完成一个Item
                if($itemOver||$itemNum>constant("max_items")){
                    $channelOver=true;
                    break;
                }else{
                    $itemsMsg.=$currentItemMsg;
                    ++$itemNum;
                }

            }else{
                continue;
            }
            if($channelOver){
                break;
            }
            $msg.=$itemsMsg;
            if($itemNum%constant("once_items_num")==0){

                //分条发送
                if($need_reply){
                    \access\send_msg($type,$to ,$msg,constant('token'));
                }
                $part_num=$itemNum/constant("once_items_num")+1;
                $msg="\n关键字:\n[ {$keyword} ]\n上次更新时间:\n[{$lastpubDate}]\n第[{$part_num}]部分\n";
            }
        }
        //避免没有结果时还会回复
        if($itemNum==1){
            $need_reply=false;
        }
    }else{
        die("xml获取失败");
    }
    //消息内容
    //$msg="XXXX";

    //最后更新LastDate
    if($need_set_date!=$lastpubDate){
        $update_sql="UPDATE bgm_users
                                SET dmhy_lastpubDate='$need_set_date'
                                WHERE user_qq=$to";
        \access\sql_query($type,$to,$update_sql);
    }
    //回复QQ
    if($need_reply&&$itemNum%constant("once_items_num")!=0){
        \access\send_msg($type,$to ,$msg,constant('token'));
    }
}

//$to=;
//$keyword=;
//$url='https://share.dmhy.org/topics/rss/rss.xml?keyword=%E4%BC%91%E6%AD%A2%E7%AC%A6';
//DMHY RSS
//$xml=simplexml_load_file($url);

//时间
//$aa=strtotime("Mon, 02 Jul 2018 19:42:02 +0800");
//echo  date("Y-m-d H:i:s",$aa);
//date('Y-m-d H:i:s');

