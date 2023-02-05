<?
#futaba.phpのmysql版。
#作りかけです。
#そのままでも動きますが、実用には耐えないでしょう。
#未実装のもの→サムネイルの有無、画像検査、画像だけ消した時にログへの反映、みんなで削除ボタン

extract($_POST,EXTR_SKIP);
extract($_GET,EXTR_SKIP);
extract($_COOKIE,EXTR_SKIP);
$upfile_name=$_FILES["upfile"]["name"];
$upfile=$_FILES["upfile"]["tmp_name"];

define(SQLLOG, 'imglog');		//ログファイル名(テーブル名)
define(IMG_DIR, 'src/');		//画像保存ディレクトリ。futaba.phpから見て
define(THUMB_DIR,'thumb/');		//サムネイル保存ディレクトリ
define(TITLE, 'この画像掲示板はＭｙＳＱＬを使っています。');		//タイトル（<title>とTOP）
define(HOME,  '../');			//「ホーム」へのリンク
define(MAX_KB, '100');			//投稿容量制限 KB（phpの設定により2Mまで
define(MAX_W,  '100');			//投稿サイズ幅（これ以上はwidthを縮小
define(MAX_H,  '100');			//投稿サイズ高さ
define(PAGE_DEF, '10');			//一ページに表示する記事
define(LOG_MAX,  '200');		//ログ最大行数
define(ADMIN_PASS, '掲示板の管理パスワードをここに書く');	//管理者パス
define(RE_COL, '789922');               //＞が付いた時の色
define(PHP_SELF, 'futaba.php');	//このスクリプト名
define(PHP_SELF2, 'futaba.htm');	//入り口ファイル名
define(PHP_EXT, '.htm');		//1ページ以降の拡張子
define(RENZOKU, '15');			//連続投稿秒数
define(RENZOKU2, '30');		//画像連続投稿秒数
define(MAX_RES, '10');		//強制sage!レス数
define(USE_THUMB, 1);		//サムネイルを作る する:1 しない:0
define(PROXY_CHECK, 1);		//proxyの書込みを制限する y:1 n:0
define(DISP_ID, 0);		//IDを表示する 強制:2 する:1 しない:0
define(BR_CHECK, 15);		//改行を抑制する行数 しない:0

$path = realpath("./").'/'.IMG_DIR;
ignore_user_abort(TRUE);
$badstring = array("dummy_string","dummy_string2"); //拒絶する文字列
$badfile = array("dummy","dummy2"); //拒絶するファイルのmd5

$badip = array("addr1\\.dummy\\.com","addr2\\.dummy\\.com"); //拒絶するホスト
$addinfo='<LI>MySQLテストちゅう。そのうち消えます。<LI>ソースは<a href="futaba.php.txt">このへん</a>。';

if(!$con=mysql_connect("localhost","mysql","ＳＱＬの接続パスワードをここに書く")){
  echo "接続失敗";                #↑mysqlはここではDBのユーザ名
  exit;
}

$db_id=mysql_select_db("bbs",$con);  #bbsはここではDBの名前
  if(!$db_id){echo "mysql_select_db失敗<br>";}

if (!table_exist(SQLLOG)) {
  echo (SQLLOG."テーブルを作成します<br>\n");
  $result = mysql_call("create table ".SQLLOG." (primary key(no),
    index (resto),index (root),index (time),
    no    int not null auto_increment,
    now   text,
    name  text,
    email text,
    sub   text,
    com   text,
    host  text,
    pwd   text,
    ext   text,
    w     int,
    h     int,
    tim   text,
    time  int,
    md5   text,
    fsize int,
    root  timestamp,
    resto int)");
  if(!$result){echo "テーブル作成失敗<br>";}
}

/* 記事部分 */
function updatelog($resno=0){
  global $path;

  $find = false;
  $resno=(int)$resno;
  if($resno){
    $result = mysql_call("select * from ".SQLLOG." where root>0 and no=$resno");
    if($result){
      $find = mysql_fetch_row($result);
      mysql_free_result($result);
    }
    if(!$find) error("該当記事がみつかりません");
  }
  if($resno){
    if(!$treeline=mysql_call("select * from ".SQLLOG." where root>0 and no=".$resno." order by root desc")){echo "sql失敗4<br>";}
  }else{
    if(!$treeline=mysql_call("select * from ".SQLLOG." where root>0 order by root desc")){echo "sql失敗4<br>";}
  }

  //最後の書き込み番号
  if(!$result=mysql_call("select max(no) from ".SQLLOG)){echo "sql失敗96<br>";}
  $row=mysql_fetch_array($result);
  $lastno=(int)$row[0];
  mysql_free_result($result);

  $counttree=mysql_num_rows($treeline);
  if(!$counttree){
    $logfilename=PHP_SELF2;
    $dat='';
    head($dat);
    form($dat,$resno);
    $fp = fopen($logfilename, "w");
    set_file_buffer($fp, 0);
    rewind($fp);
    fputs($fp, $dat);
    fclose($fp);
    chmod($logfilename,0666);
  }
  for($page=0;$page<$counttree;$page+=PAGE_DEF){
    $dat='';
    head($dat);
    form($dat,$resno);
    if(!$resno){
      $st = $page;
    }
    $dat.='<form action="'.PHP_SELF.'" method=POST>';

  for($i = $st; $i < $st+PAGE_DEF; $i++){
    list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,)=mysql_fetch_row($treeline);
    if(!$no){break;}

    // URLとメールにリンク
    if($email) $name = "<a href=\"mailto:$email\">$name</a>";
    $com = auto_link($com);
    $com = eregi_replace("(^|>)(&gt;[^<]*)", "\\1<font color=".RE_COL.">\\2</font>", $com);
    // 画像ファイル名
    $img = $path.$tim.$ext;
    $src = IMG_DIR.$tim.$ext;
    // <imgタグ作成
    $imgsrc = "";
    if($ext){
      $size = $fsize;//altにサイズ表示
      if($w && $h){//サイズがある時
        if(@is_file(THUMB_DIR.$tim.'s.jpg')){
          $imgsrc = "<small>サムネイルを表示しています.クリックすると元のサイズを表示します.</small><br><a href=\"".$src."\" target=_blank><img src=".THUMB_DIR.$tim.'s.jpg'.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }else{
          $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }
      }else{//それ以外
        $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left hspace=20 alt=\"".$size." B\"></a>";
      }
      $dat.="画像タイトル：<a href=\"$src\" target=_blank>$tim$ext</a>-($size B)<br>$imgsrc";
    }
    // メイン作成
    $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
    $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
    if(!$resno) $dat.="[<a href=".PHP_SELF."?res=$no>返信</a>]";
    $dat.="\n<blockquote>$com</blockquote>";

     // そろそろ消える。
     if($lastno-LOG_MAX*0.95>$no){
      $dat.="<font color=\"#f00000\"><b>このスレは古いので、もうすぐ消えます。</b></font><br>\n";
     }

    if(!$resline=mysql_call("select * from ".SQLLOG." where resto=".$no." order by no")){echo "sql失敗5<br>";}
    $countres=mysql_num_rows($resline);
#      $dat.=$no.' ';//res表示

    if(!$resno){
     $s=$countres - 10;
     if($s<0){$s=0;}
     elseif($s>0){
      $dat.="<font color=\"#707070\">レス".
             $s."件省略。全て読むには返信ボタンを押してください。</font><br>\n";
     }
    }else{$s=0;}

    while($resrow=mysql_fetch_row($resline)){ //resのループ
      if($s>0){$s--;continue;}
      list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,)=$resrow;
      if(!$no){break;}

      // URLとメールにリンク
      if($email) $name = "<a href=\"mailto:$email\">$name</a>";
      $com = auto_link($com);
      $com = eregi_replace("(^|>)(&gt;[^<]*)", "\\1<font color=".RE_COL.">\\2</font>", $com);
      // メイン作成
      $dat.="<table border=0><tr><td nowrap align=right valign=top>&gt;&gt;</td><td bgcolor=#F0E0D6>\n";
      $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
      $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
      $dat.="<blockquote>$com</blockquote>";
      $dat.="</td></tr></table>\n";
    }
    $dat.="<br clear=left><hr>\n";
    clearstatcache();//ファイルのstatをクリア
    mysql_free_result($resline);
    $p++;
    if($resno){break;} //res時はtree1行だけ
  }
$dat.='<table align=right><tr><td nowrap align=center>
<input type=hidden name=mode value=usrdel>【記事削除】[<input type=checkbox name=onlyimgdel value=on>画像だけ消す]<br>
削除キー<input type=password name=pwd size=8 maxlength=8 value="">
<input type=submit value="削除"></form></td></tr></table>
<script language="JavaScript"><!--
l();
//--></script>';

    if(!$resno){ //res時は表示しない
      $prev = $st - PAGE_DEF;
      $next = $st + PAGE_DEF;
    // 改ページ処理
      $dat.="<table align=left border=1><tr>";
      if($prev >= 0){
        if($prev==0){
          $dat.="<form action=\"".PHP_SELF2."\" method=get><td>";
        }else{
          $dat.="<form action=\"".$prev/PAGE_DEF.PHP_EXT."\" method=get><td>";
        }
        $dat.="<input type=submit value=\"前のページ\">";
        $dat.="</td></form>";
      }else{$dat.="<td>最初のページ</td>";}

      $dat.="<td>";
      for($i = 0; $i < $counttree ; $i+=PAGE_DEF){
        if($i&&!($i%(PAGE_DEF*2))){$dat.="<br>";}
        if($st==$i){$dat.="[<b>".($i/PAGE_DEF)."</b>] ";}
        else{
          if($i==0){$dat.="[<a href=\"".PHP_SELF2."\">0</a>] ";}
          else{$dat.="[<a href=\"".($i/PAGE_DEF).PHP_EXT."\">".($i/PAGE_DEF)."</a>] ";}
        }
      }
      $dat.="</td>";

      if($p >= PAGE_DEF && $counttree > $next){
        $dat.="<form action=\"".$next/PAGE_DEF.PHP_EXT."\" method=get><td>";
        $dat.="<input type=submit value=\"次のページ\">";
        $dat.="</td></form>";
      }else{$dat.="<td>最後のページ</td>";}
        $dat.="</tr></table><br clear=all>\n";
    }
    foot($dat);
    if($resno){echo $dat;break;}
    if($page==0){$logfilename=PHP_SELF2;}
    else{$logfilename=$page/PAGE_DEF.PHP_EXT;}
    $fp = fopen($logfilename, "w");
    set_file_buffer($fp, 0);
    rewind($fp);
    fputs($fp, $dat);
    fclose($fp);
    chmod($logfilename,0666);
  }
  mysql_free_result($treeline);
}

function mysql_call($query){
  $ret=mysql_query($query);
  if(!$ret){
#echo "error!!<br>";
    echo $query."<br>";
#    echo mysql_errno().": ".mysql_error()."<br>";
  }
  return $ret;
}

/* ヘッダ */
function head(&$dat){
  $dat.='<html><head>
<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=Shift_JIS">
<!-- meta HTTP-EQUIV="pragma" CONTENT="no-cache" -->
<STYLE TYPE="text/css">
<!--
body,tr,td,th { font-size:12pt }
a:hover { color:#DD0000; }
span { font-size:20pt }
small { font-size:10pt }
-->
</STYLE>
<title>'.TITLE.'</title>
<script language="JavaScript"><!--
function l(e){var P=getCookie("pwdc"),N=getCookie("namec"),i;with(document){for(i=0;i<forms.length;i++){if(forms[i].pwd)with(forms[i]){if(!pwd.value)pwd.value=P;}if(forms[i].name)with(forms[i]){if(!name.value)name.value=N;}}}};function getCookie(key, tmp1, tmp2, xx1, xx2, xx3) {tmp1 = " " + document.cookie + ";";xx1 = xx2 = 0;len = tmp1.length;	while (xx1 < len) {xx2 = tmp1.indexOf(";", xx1);tmp2 = tmp1.substring(xx1 + 1, xx2);xx3 = tmp2.indexOf("=");if (tmp2.substring(0, xx3) == key) {return(unescape(tmp2.substring(xx3 + 1, xx2 - xx1 - 1)));}xx1 = xx2 + 1;}return("");}
//--></script>
</head>
<body bgcolor="#FFFFEE" text="#800000" link="#0000EE" vlink="#0000EE">
<p align=right>
[<a href="'.HOME.'" target="_top">ホーム</a>]
[<a href="'.PHP_SELF.'?mode=admin">管理用</a>]
<p align=center>
<font color="#800000" size=5>
<b><SPAN>'.TITLE.'</SPAN></b></font>
<hr width="90%" size=1>
';
}
/* 投稿フォーム */
function form(&$dat,$resno,$admin=""){
  global $addinfo;
  $maxbyte = MAX_KB * 1024;
  $no=$resno;
  if($resno){
    $msg .= "[<a href=\"".PHP_SELF2."\">掲示板に戻る</a>]\n";
    $msg .= "<table width='100%'><tr><th bgcolor=#e04000>\n";
    $msg .= "<font color=#FFFFFF>レス送信モード</font>\n";
    $msg .= "</th></tr></table>\n";
  }
  if($admin){
    $hidden = "<input type=hidden name=admin value=\"".ADMIN_PASS."\">";
    $msg = "<h4>タグがつかえます</h4>";
  }
  $dat.=$msg.'<center>
<form action="'.PHP_SELF.'" method="POST" enctype="multipart/form-data">
<input type=hidden name=mode value="regist">
'.$hidden.'
<input type=hidden name="MAX_FILE_SIZE" value="'.$maxbyte.'">
';
if($no){$dat.='<input type=hidden name=resto value="'.$no.'">
';}
$dat.='<table cellpadding=1 cellspacing=1>
<tr><td bgcolor=#eeaa88><b>おなまえ</b></td><td><input type=text name=name size="28"></td></tr>
<tr><td bgcolor=#eeaa88><b>E-mail</b></td><td><input type=text name=email size="28"></td></tr>
<tr><td bgcolor=#eeaa88><b>題　　名</b></td><td><input type=text name=sub size="35">
<input type=submit value="送信する"></td></tr>
<tr><td bgcolor=#eeaa88><b>コメント</b></td><td><textarea name=com cols="48" rows="4" wrap=soft></textarea></td></tr>
';
if(!$resno){
$dat.='<tr><td bgcolor=#eeaa88><b>添付File</b></td>
<td><input type=file name=upfile size="35">
[<label><input type=checkbox name=textonly value=on>画像なし</label>]</td></tr>
';}
$dat.='<tr><td bgcolor=#eeaa88><b>削除キー</b></td><td><input type=password name=pwd size=8 maxlength=8 value=""><small>(記事の削除用。英数字で8文字以内)</small></td></tr>
<script language="JavaScript"><!--
l();
//--></script>
<tr><td colspan=2>
<small>
<LI>添付可能ファイル：GIF, JPG, PNG ブラウザによっては正常に添付できないことがあります。
<LI>最大投稿データ量は '.MAX_KB.' KB までです。sage機能付き。
<LI>画像は横 '.MAX_W.'ピクセル、縦 '.MAX_H.'ピクセルを超えると縮小表示されます。
'.$addinfo.'</small></td></tr></table></form></center><hr>';
}

/* フッタ */
function foot(&$dat){
  $dat.='
<center>
<small><!-- GazouBBS v3.0 --><!-- ふたば改0.8 -->
- <a href="http://php.s3.to" target=_top>GazouBBS</a> + <a href="http://www.2chan.net/" target=_top>futaba</a>-
</small>
</center>
</body></html>';
}

function error($mes,$dest=''){
  global $upfile_name,$path;
  if(is_file($dest)) unlink($dest);
  head($dat);
  echo $dat;
  echo "<br><br><hr size=1><br><br>
        <center><font color=red size=5><b>$mes<br><br><a href=".PHP_SELF2.">リロード</a></b></font></center>
        <br><br><hr size=1>";
  die("</body></html>");
}
/* オートリンク */
function auto_link($proto){
  $proto = ereg_replace("(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)","<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",$proto);
  return $proto;
}

function  proxy_connect($port) {
  $fp = @fsockopen ($_SERVER["REMOTE_ADDR"], $port,$a,$b,2);
  if(!$fp){return 0;}else{return 1;}
}
/* 記事書き込み */
function regist($name,$email,$sub,$com,$url,$pwd,$upfile,$upfile_name,$resto){
  global $path,$badstring,$badfile,$badip,$pwdc,$textonly;

  // 時間
  $time = time();
  $tim = $time.substr(microtime(),2,3);

  // アップロード処理
  if($upfile&&file_exists($upfile)){
    $dest = $path.$tim.'.tmp';
    move_uploaded_file($upfile, $dest);
    //↑でエラーなら↓に変更
    //copy($upfile, $dest);
    $upfile_name = CleanStr($upfile_name);
    if(!is_file($dest)) error("アップロードに失敗しました<br>サーバがサポートしていない可能性があります",$dest);
    $size = getimagesize($dest);
    if(!is_array($size)) error("アップロードに失敗しました<br>画像ファイル以外は受け付けません",$dest);
    $md5 = md5_of_file($dest);
    foreach($badfile as $value){if(ereg("^$value",$md5)){
      error("アップロードに失敗しました<br>同じ画像がありました",$dest); //拒絶画像
    }}
    chmod($dest,0666);
    $W = $size[0];
    $H = $size[1];
    $fsize = filesize($dest);
    if($fsize>MAX_KB * 1024) error("アップロードに失敗しました<br>サイズが大きすぎます<br>".MAX_KB."Kバイトまで",$dest);
    switch ($size[2]) {
      case 1 : $ext=".gif";break;
      case 2 : $ext=".jpg";break;
      case 3 : $ext=".png";break;
      case 4 : $ext=".swf";break;
      case 5 : $ext=".psd";break;
      case 6 : $ext=".bmp";break;
      case 13 : $ext=".swf";break;
      default : $ext=".xxx";break;
    }

    // 画像表示縮小
    if($W > MAX_W || $H > MAX_H){
      $W2 = MAX_W / $W;
      $H2 = MAX_H / $H;
      ($W2 < $H2) ? $key = $W2 : $key = $H2;
      $W = ceil($W * $key);
      $H = ceil($H * $key);
    }
    $mes = "画像 $upfile_name のアップロードが成功しました<br><br>";
  }

  if($_FILES["upfile"]["error"]==2){
    error("アップロードに失敗しました<br>画像サイズが大きすぎます<br>".MAX_KB."Kバイトまで",$dest);
  }
  if($upfile_name&&$_FILES["upfile"]["size"]==0){
    error("アップロードに失敗しました<br>画像サイズが大きすぎるか、<br>または画像がありません。",$dest);
  }

  //最後の書き込み番号
  if(!$result=mysql_call("select max(no) from ".SQLLOG)){echo "sql失敗387<br>";}
  $row=mysql_fetch_array($result);
  $lastno=(int)$row[0];
  mysql_free_result($result);

  // ログ行数オーバー
  if(!$result=mysql_call("select no,ext,tim from ".SQLLOG." where no<=".($lastno-LOG_MAX))){echo "sql失敗393<br>";}
  else{
    while($resrow=mysql_fetch_row($result)){
      list($dno,$dext,$dtim)=$resrow;
      if(!mysql_call("delete from ".SQLLOG." where no=".$dno)){echo "sql失敗396<br>";}
      if($dext){
        if(is_file($path.$dtim.$dext)) unlink($path.$dtim.$dext);
        if(is_file(THUMB_DIR.$dtim.'s.jpg')) unlink(THUMB_DIR.$dtim.'s.jpg');
      }
    }
    mysql_free_result($result);
  }

  $find = false;
  $resto=(int)$resto;
  if($resto){
    if(!$result = mysql_call("select * from ".SQLLOG." where root>0 and no=$resto")){echo "sql失敗403<br>";}
    else{
      $find = mysql_fetch_row($result);
      mysql_free_result($result);
    }
    if(!$find) error("スレッドがありません",$dest);
  }

  foreach($badstring as $value){if(ereg($value,$com)||ereg($value,$sub)||ereg($value,$name)||ereg($value,$email)){
  error("拒絶されました(str)",$dest);};}
  if($_SERVER["REQUEST_METHOD"] != "POST") error("不正な投稿をしないで下さい(post)",$dest);
  // フォーム内容をチェック
  if(!$name||ereg("^[ |　|]*$",$name)) $name="";
  if(!$com||ereg("^[ |　|\t]*$",$com)) $com="";
  if(!$sub||ereg("^[ |　|]*$",$sub))   $sub=""; 

  if(!$resto&&!$textonly&&!is_file($dest)) error("画像がありません",$dest);
  if(!$com&&!is_file($dest)) error("何か書いて下さい",$dest);

  $name=ereg_replace("管理","\"管理\"",$name);
  $name=ereg_replace("削除","\"削除\"",$name);

  if(strlen($com) > 1000) error("本文が長すぎますっ！",$dest);
  if(strlen($name) > 100) error("本文が長すぎますっ！",$dest);
  if(strlen($email) > 100) error("本文が長すぎますっ！",$dest);
  if(strlen($sub) > 100) error("本文が長すぎますっ！",$dest);
  if(strlen($resto) > 10) error("異常です",$dest);
  if(strlen($url) > 10) error("異常です",$dest);

  //ホスト取得
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);

  foreach($badip as $value){ //拒絶host
   if(eregi("$value$",$host)){
    error("拒絶されました(host)",$dest);
  }}
  if(eregi("^mail",$host)
    || eregi("^ns",$host)
    || eregi("^dns",$host)
    || eregi("^ftp",$host)
    || eregi("^prox",$host)
    || eregi("^pc",$host)
    || eregi("^[^\.]\.[^\.]$",$host)){
    $pxck = "on";
  }
  if(eregi("ne\\.jp$",$host)||
    eregi("ad\\.jp$",$host)||
    eregi("bbtec\\.net$",$host)||
    eregi("aol\\.com$",$host)||
    eregi("uu\\.net$",$host)||
    eregi("asahi-net\\.or\\.jp$",$host)||
    eregi("rim\\.or\\.jp$",$host)
    ){$pxck = "off";}
  else{$pxck = "on";}

  if($pxck=="on" && PROXY_CHECK){
    if(proxy_connect('80') == 1){
      error("ＥＲＲＯＲ！　公開ＰＲＯＸＹ規制中！！(80)",$dest);
    } elseif(proxy_connect('8080') == 1){
      error("ＥＲＲＯＲ！　公開ＰＲＯＸＹ規制中！！(8080)",$dest);
    }
  }

  // No.とパスと時間とURLフォーマット
  srand((double)microtime()*1000000);
  if($pwd==""){
    if($pwdc==""){
      $pwd=rand();$pwd=substr($pwd,0,8);
    }else{
      $pwd=$pwdc;
    }
  }

  $c_pass = $pwd;
  $pass = ($pwd) ? substr(md5($pwd),2,8) : "*";
  $youbi = array('日','月','火','水','木','金','土');
  $yd = $youbi[gmdate("w", $time+9*60*60)] ;
  $now = gmdate("y/m/d",$time+9*60*60)."(".(string)$yd.")".gmdate("H:i",$time+9*60*60);
  if(DISP_ID){
    if($email&&DISP_ID==1){
      $now .= " ID:???";
    }else{
      $now.=" ID:".substr(crypt(md5($_SERVER["REMOTE_ADDR"].'idの種'.gmdate("Ymd", $time+9*60*60)),'id'),-8);
    }
  }
  //テキスト整形
  $email= CleanStr($email);  $email=ereg_replace("[\r\n]","",$email);
  $sub  = CleanStr($sub);    $sub  =ereg_replace("[\r\n]","",$sub);
  $url  = CleanStr($url);    $url  =ereg_replace("[\r\n]","",$url);
  $resto= CleanStr($resto);  $resto=ereg_replace("[\r\n]","",$resto);
  $com  = CleanStr($com);
  // 改行文字の統一。 
  $com = str_replace( "\r\n",  "\n", $com); 
  $com = str_replace( "\r",  "\n", $com);
  // 連続する空行を一行
  $com = ereg_replace("\n((　| )*\n){3,}","\n",$com);
  if(!BR_CHECK || substr_count($com,"\n")<BR_CHECK){
    $com = nl2br($com);		//改行文字の前に<br>を代入する
  }
  $com = str_replace("\n",  "", $com);	//\nを文字列から消す。

  $name=ereg_replace("◆","◇",$name);
  $name=ereg_replace("[\r\n]","",$name);
  $names=$name;
  $name = CleanStr($name);
  if(ereg("(#|＃)(.*)",$names,$regs)){
    $cap = $regs[2];
    $cap=strtr($cap,"&amp;", "&");
    $cap=strtr($cap,"&#44;", ",");
    $name=ereg_replace("(#|＃)(.*)","",$name);
    $salt=substr($cap."H.",1,2);
    $salt=ereg_replace("[^\.-z]",".",$salt);
    $salt=strtr($salt,":;<=>?@[\\]^_`","ABCDEFGabcdef"); 
    $name.=" </b>◆".substr(crypt($cap,$salt),-10)."<b>";
  }

  if(!$name) $name="名無し";
  if(!$com) $com="本文なし";
  if(!$sub) $sub="無題"; 

  // 二重投稿チェック
  $query="select time from ".SQLLOG." where com='".mysql_escape_string($com)."' ".
         "and host='".mysql_escape_string($host)."' ".
         "and no>".($lastno-20);  //コメントが同じ
  if(!$result=mysql_call($query)){echo "sql失敗510<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&!$upfile_name)error("連続投稿はもうしばらく時間を置いてからお願い致します",$dest);

  $query="select time from ".SQLLOG." where time>".($time - RENZOKU)." ".
         "and host='".mysql_escape_string($host)."' ";  //前の投稿から短時間
  if(!$result=mysql_call($query)){echo "sql失敗577<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&!$upfile_name)error("連続投稿はもうしばらく時間を置いてからお願い致します",$dest);

  // アップロード処理
  if($dest&&file_exists($dest)){

  $query="select time from ".SQLLOG." where time>".($time - RENZOKU2)." ".
         "and host='".mysql_escape_string($host)."' ";  //前の投稿から短時間
  if(!$result=mysql_call($query)){echo "sql失敗586<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&$upfile_name)error("画像連続投稿はもうしばらく時間を置いてからお願い致します",$dest);

  //画像重複チェック
    $result = mysql_call("select tim,ext,md5 from ".SQLLOG." where md5='".$md5."'");
    if($result){
      list($timp,$extp,$md5p) = mysql_fetch_row($result);
      mysql_free_result($result);
#      if($timp&&file_exists($path.$timp.$extp)){ #}
      if($timp){
        error("アップロードに失敗しました<br>同じ画像があります",$dest);
      }
    }
  }

  $restoqu=(int)$resto;
  if($resto){ //res,root処理の区別
    $rootqu="0";
    if(!$resline=mysql_call("select * from ".SQLLOG." where resto=".$resto)){echo "sql失敗581<br>";}
    $countres=mysql_num_rows($resline);
    mysql_free_result($resline);
    if(!stristr($email,'sage') && $countres < MAX_RES){
      $query="update ".SQLLOG." set root=now() where no=$resto"; //resならage処理
      if(!$result=mysql_call($query)){echo "sql失敗527<br>";}
    }
  }else{$rootqu="now()";} //rootなら現時刻を書く
  
  $query="insert into ".SQLLOG." (now,name,email,sub,com,host,pwd,ext,w,h,tim,time,md5,fsize,root,resto) values (".
"'".$now."',".
"'".mysql_escape_string($name)."',".
"'".mysql_escape_string($email)."',".
"'".mysql_escape_string($sub)."',".
"'".mysql_escape_string($com)."',".
"'".mysql_escape_string($host)."',".
"'".mysql_escape_string($pass)."',".
"'".$ext."',".
(int)$W.",".
(int)$H.",".
"'".$tim."',".
(int)$time.",".
"'".$md5."',".
(int)$fsize.",".
$rootqu.",".
(int)$resto.")";
  if(!$result=mysql_call($query)){echo "sql失敗2登録<br>";}  //post記事の登録

    //クッキー保存
  setcookie ("pwdc", $c_pass,time()+7*24*3600);  /* 1週間で期限切れ */
  if(function_exists("mb_internal_encoding")&&function_exists("mb_convert_encoding")
      &&function_exists("mb_substr")){
    if(ereg("MSIE|Opera",$_SERVER["HTTP_USER_AGENT"])){
      $i=0;$c_name='';
      mb_internal_encoding("SJIS");
      while($j=mb_substr($names,$i,1)){
        $j = mb_convert_encoding($j, "UTF-16", "SJIS");
        $c_name.="%u".bin2hex($j);
        $i++;
      }
      header("Set-Cookie: namec=$c_name; expires=".gmdate("D, d-M-Y H:i:s",time()+7*24*3600)." GMT",false);
    }else{
      $c_name=$names;
      setcookie ("namec", $c_name,time()+7*24*3600);  /* 1週間で期限切れ */
    }
  }

  if($dest&&file_exists($dest)){
    rename($dest,$path.$tim.$ext);
    if(USE_THUMB){thumb($path,$tim,$ext);}
  }
  updatelog();

  echo "<html><head><META HTTP-EQUIV=\"refresh\" content=\"1;URL=".PHP_SELF2."\"></head>";
  echo "<body>$mes 画面を切り替えます</body></html>";
}

//サムネイル作成
function thumb($path,$tim,$ext){
  if(!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG"))return;
  $fname=$path.$tim.$ext;
  $thumb_dir = THUMB_DIR;     //サムネイル保存ディレクトリ
  $width     = MAX_W;            //出力画像幅
  $height    = MAX_H;            //出力画像高さ
  // 画像の幅と高さとタイプを取得
  $size = GetImageSize($fname);
  switch ($size[2]) {
    case 1 :
      if(function_exists("ImageCreateFromGIF")){
        $im_in = @ImageCreateFromGIF($fname);
        if($im_in){break;}
      }
      if(!is_executable(realpath("./gif2png"))||!function_exists("ImageCreateFromPNG"))return;
      @exec(realpath("./gif2png")." $fname",$a);
      if(!file_exists($path.$tim.'.png'))return;
      $im_in = @ImageCreateFromPNG($path.$tim.'.png');
      unlink($path.$tim.'.png');
      if(!$im_in)return;
      break;
    case 2 : $im_in = @ImageCreateFromJPEG($fname);
      if(!$im_in){return;}
       break;
    case 3 :
      if(!function_exists("ImageCreateFromPNG"))return;
      $im_in = @ImageCreateFromPNG($fname);
      if(!$im_in){return;}
      break;
    default : return;
  }
  // リサイズ
  if ($size[0] > $width || $size[1] >$height) {
    $key_w = $width / $size[0];
    $key_h = $height / $size[1];
    ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
    $out_w = ceil($size[0] * $keys) +1;
    $out_h = ceil($size[1] * $keys) +1;
  } else {
    $out_w = $size[0];
    $out_h = $size[1];
  }
  // 出力画像（サムネイル）のイメージを作成
  if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
    $im_out = ImageCreateTrueColor($out_w, $out_h);
  }else{$im_out = ImageCreate($out_w, $out_h);}
  // 元画像を縦横とも コピーします。
  ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
  // サムネイル画像を保存
  ImageJPEG($im_out, $thumb_dir.$tim.'s.jpg',60);
  chmod($thumb_dir.$tim.'s.jpg',0666);
  // 作成したイメージを破棄
  ImageDestroy($im_in);
  ImageDestroy($im_out);
}
//gdのバージョンを調べる
function get_gd_ver(){
  if(function_exists("gd_info")){
    $gdver=gd_info();
    $phpinfo=$gdver["GD Version"];
  }else{ //php4.3.0未満用
    ob_start();
    phpinfo(8);
    $phpinfo=ob_get_contents();
    ob_end_clean();
    $phpinfo=strip_tags($phpinfo);
    $phpinfo=stristr($phpinfo,"gd version");
    $phpinfo=stristr($phpinfo,"version");
  }
  $end=strpos($phpinfo,".");
  $phpinfo=substr($phpinfo,0,$end);
  $length = strlen($phpinfo)-1;
  $phpinfo=substr($phpinfo,$length);
  return $phpinfo;
}
//ファイルmd5計算 php4.2.0未満用
function md5_of_file($inFile) {
 if (file_exists($inFile)){
  if(function_exists('md5_file')){
    return md5_file($inFile);
  }else{
    $fd = fopen($inFile, 'r');
    $fileContents = fread($fd, filesize($inFile));
    fclose ($fd);
    return md5($fileContents);
  }
 }else{
  return false;
}}
/* テキスト整形 */
function CleanStr($str){
  global $admin;
  $str = trim($str);//先頭と末尾の空白除去
  if (get_magic_quotes_gpc()) {//￥を削除
    $str = stripslashes($str);
  }
  if($admin!=ADMIN_PASS){//管理者はタグ可能
    $str = htmlspecialchars($str);//タグっ禁止
    $str = str_replace("&amp;", "&", $str);//特殊文字
  }
  return str_replace(",", "&#44;", $str);//カンマを変換
}

//テーブルがあるかどうか 0=なし 1=あり
function table_exist($table){
  $result = mysql_call("show tables like '$table'");
  if(!$result){return 0;}
  $a = mysql_fetch_row($result);
  mysql_free_result($result);
  return $a;
}

/* ユーザー削除 */
function usrdel($no,$pwd){
  global $path,$pwdc,$onlyimgdel;
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
  $delno = array();
  $delflag = FALSE;
  reset($_POST);
  while ($item = each($_POST)){
    if($item[1]=='delete'){array_push($delno,$item[0]);$delflag=TRUE;}
  }
  if($pwd==""&&$pwdc!="") $pwd=$pwdc;
  $countdel=count($delno);

  $flag = FALSE;
  for($i = 0; $i<$countdel; $i++){
    if(!$result=mysql_call("select no,ext,tim,pwd,host from ".SQLLOG." where no=".$delno[$i])){echo "sql失敗727<br>";}
    else{
      while($resrow=mysql_fetch_row($result)){
        list($dno,$dext,$dtim,$dpass,$dhost)=$resrow;
        if(substr(md5($pwd),2,8) == $dpass || substr(md5($pwdc),2,8) == $dpass ||
            $dhost == $host || ADMIN_PASS==$pwd){
          $flag = TRUE;
          $delfile = $path.$dtim.$dext;	//削除ファイル
          if(!$onlyimgdel){
            if(!mysql_call("delete from ".SQLLOG." where no=".$dno)){echo "sql失敗736<br>";} //既に消えてるかも
          }
          if(is_file($delfile)) unlink($delfile);//削除
          if(is_file(THUMB_DIR.$dtim.'s.jpg')) unlink(THUMB_DIR.$dtim.'s.jpg');//削除
        }
      }
      mysql_free_result($result);
    }
  }
  if(!$flag) error("該当記事が見つからないかパスワードが間違っています");
}

/* パス認証 */
function valid($pass){
  if($pass && $pass != ADMIN_PASS) error("パスワードが違います");

  head($dat);
  echo $dat;
  echo "[<a href=\"".PHP_SELF2."\">掲示板に戻る</a>]\n";
  echo "[<a href=\"".PHP_SELF."\">ログを更新する</a>]\n";
  echo "<table width='100%'><tr><th bgcolor=#E08000>\n";
  echo "<font color=#FFFFFF>管理モード</font>\n";
  echo "</th></tr></table>\n";
  echo "<p><form action=\"".PHP_SELF."\" method=POST>\n";
  // ログインフォーム
  if(!$pass){
    echo "<center><input type=radio name=admin value=del checked>記事削除 ";
    echo "<input type=radio name=admin value=post>管理人投稿<p>";
    echo "<input type=hidden name=mode value=admin>\n";
    echo "<input type=password name=pass size=8>";
    echo "<input type=submit value=\" 認証 \"></form></center>\n";
    die("</body></html>");
  }
}

/* 管理者削除 */
function admindel($pass){
  global $path,$onlyimgdel;
  $delno = array(dummy);
  $delflag = FALSE;
  reset($_POST);
  while ($item = each($_POST)){
   if($item[1]=='delete'){array_push($delno,$item[0]);$delflag=TRUE;}
  }
  if($delflag){
    if(!$result=mysql_call("select * from ".SQLLOG."")){echo "sql失敗814<br>";}
    $find = FALSE;
    while($row=mysql_fetch_row($result)){
      list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,)=$row;
      if($onlyimgdel==on){
        if(array_search($no,$delno)){//画像だけ削除
          $delfile = $path.$tim.$ext;	//削除ファイル
          if(is_file($delfile)) unlink($delfile);//削除
          if(is_file(THUMB_DIR.$tim.'s.jpg')) unlink(THUMB_DIR.$tim.'s.jpg');//削除
        }
      }else{
        if(array_search($no,$delno)){//削除の時は空に
          $find = TRUE;
          if(!mysql_call("delete from ".SQLLOG." where no=".$no)){echo "sql失敗827<br>";}
          $delfile = $path.$tim.$ext;	//削除ファイル
          if(is_file($delfile)) unlink($delfile);//削除
          if(is_file(THUMB_DIR.$tim.'s.jpg')) unlink(THUMB_DIR.$tim.'s.jpg');//削除
        }
      }
    }
    mysql_free_result($result);
    if($find){//ログ更新
    }
  }
  // 削除画面を表示
  echo "<input type=hidden name=mode value=admin>\n";
  echo "<input type=hidden name=admin value=del>\n";
  echo "<input type=hidden name=pass value=\"$pass\">\n";
  echo "<center><P>削除したい記事のチェックボックスにチェックを入れ、削除ボタンを押して下さい。\n";
  echo "<p><input type=submit value=\"削除する\">";
  echo "<input type=reset value=\"リセット\">";
  echo "[<input type=checkbox name=onlyimgdel value=on><!--checked-->画像だけ消す]";
  echo "<P><table border=1 cellspacing=0>\n";
  echo "<tr bgcolor=6080f6><th>削除</th><th>記事No</th><th>投稿日</th><th>題名</th>";
  echo "<th>投稿者</th><th>コメント</th><th>ホスト名</th><th>添付<br>(Bytes)</th><th>md5</th><th>resto</th><th>tim</th><th>time</th>";
  echo "</tr>\n";

  if(!$result=mysql_call("select * from ".SQLLOG." order by no desc")){echo "sql失敗864<br>";}
  $j=0;
  while($row=mysql_fetch_row($result)){
    $j++;
    $img_flag = FALSE;
    list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,$root,$resto)=$row;
    // フォーマット
    $now=ereg_replace('.{2}/(.*)$','\1',$now);
    $now=ereg_replace('\(.*\)',' ',$now);
    if(strlen($name) > 10) $name = substr($name,0,9).".";
    if(strlen($sub) > 10) $sub = substr($sub,0,9).".";
    if($email) $name="<a href=\"mailto:$email\">$name</a>";
    $com = str_replace("<br />"," ",$com);
    $com = htmlspecialchars($com);
    if(strlen($com) > 20) $com = substr($com,0,18) . ".";
    // 画像があるときはリンク
    if($ext && is_file($path.$tim.$ext)){
      $img_flag = TRUE;
      $clip = "<a href=\"".IMG_DIR.$tim.$ext."\" target=_blank>".$tim.$ext."</a><br>";
      $size = $fsize;
      $all += $size;			//合計計算
      $md5= substr($md5,0,10);
    }else{
      $clip = "";
      $size = 0;
      $md5= "";
    }
    $bg = ($j % 2) ? "d6d6f6" : "f6f6f6";//背景色

    echo "<tr bgcolor=$bg><th><input type=checkbox name=\"$no\" value=delete></th>";
    echo "<th>$no</th><td><small>$now</small></td><td>$sub</td>";
    echo "<td><b>$name</b></td><td><small>$com</small></td>";
    echo "<td>$host</td><td align=center>$clip($size)</td><td>$md5</td><td>$resto</td><td>$tim</td><td>$time</td>\n";
    echo "</tr>\n";
  }
  mysql_free_result($result);

  echo "</table><p><input type=submit value=\"削除する$msg\">";
  echo "<input type=reset value=\"リセット\"></form>";

  $all = (int)($all / 1024);
  echo "【 画像データ合計 : <b>$all</b> KB 】";
  die("</center></body></html>");
}

/*-----------Main-------------*/
switch($mode){
  case 'regist':
    regist($name,$email,$sub,$com,'',$pwd,$upfile,$upfile_name,$resto);
    break;
  case 'admin':
    valid($pass);
    if($admin=="del") admindel($pass);
    if($admin=="post"){
      echo "</form>";
      form($post,$res,1);
      echo $post;
      die("</body></html>");
    }
    break;
  case 'usrdel':
    usrdel($no,$pwd);
  default:
    if($res){
      updatelog($res);
    }else{
      updatelog();
      echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=".PHP_SELF2."\">";
    }
}

?>
