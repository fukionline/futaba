<?
#futaba.php��mysql�ŁB
#��肩���ł��B
#���̂܂܂ł������܂����A���p�ɂ͑ς��Ȃ��ł��傤�B
#�������̂��́��T���l�C���̗L���A�摜�����A�摜�������������Ƀ��O�ւ̔��f�A�݂�Ȃō폜�{�^��

extract($_POST,EXTR_SKIP);
extract($_GET,EXTR_SKIP);
extract($_COOKIE,EXTR_SKIP);
$upfile_name=$_FILES["upfile"]["name"];
$upfile=$_FILES["upfile"]["tmp_name"];

define(SQLLOG, 'imglog');		//���O�t�@�C����(�e�[�u����)
define(IMG_DIR, 'src/');		//�摜�ۑ��f�B���N�g���Bfutaba.php���猩��
define(THUMB_DIR,'thumb/');		//�T���l�C���ۑ��f�B���N�g��
define(TITLE, '���̉摜�f���͂l���r�p�k���g���Ă��܂��B');		//�^�C�g���i<title>��TOP�j
define(HOME,  '../');			//�u�z�[���v�ւ̃����N
define(MAX_KB, '100');			//���e�e�ʐ��� KB�iphp�̐ݒ�ɂ��2M�܂�
define(MAX_W,  '100');			//���e�T�C�Y���i����ȏ��width���k��
define(MAX_H,  '100');			//���e�T�C�Y����
define(PAGE_DEF, '10');			//��y�[�W�ɕ\������L��
define(LOG_MAX,  '200');		//���O�ő�s��
define(ADMIN_PASS, '�f���̊Ǘ��p�X���[�h�������ɏ���');	//�Ǘ��҃p�X
define(RE_COL, '789922');               //�����t�������̐F
define(PHP_SELF, 'futaba.php');	//���̃X�N���v�g��
define(PHP_SELF2, 'futaba.htm');	//������t�@�C����
define(PHP_EXT, '.htm');		//1�y�[�W�ȍ~�̊g���q
define(RENZOKU, '15');			//�A�����e�b��
define(RENZOKU2, '30');		//�摜�A�����e�b��
define(MAX_RES, '10');		//����sage���X��
define(USE_THUMB, 1);		//�T���l�C������� ����:1 ���Ȃ�:0
define(PROXY_CHECK, 1);		//proxy�̏����݂𐧌����� y:1 n:0
define(DISP_ID, 0);		//ID��\������ ����:2 ����:1 ���Ȃ�:0
define(BR_CHECK, 15);		//���s��}������s�� ���Ȃ�:0

$path = realpath("./").'/'.IMG_DIR;
ignore_user_abort(TRUE);
$badstring = array("dummy_string","dummy_string2"); //���₷�镶����
$badfile = array("dummy","dummy2"); //���₷��t�@�C����md5

$badip = array("addr1\\.dummy\\.com","addr2\\.dummy\\.com"); //���₷��z�X�g
$addinfo='<LI>MySQL�e�X�g���イ�B���̂��������܂��B<LI>�\�[�X��<a href="futaba.php.txt">���̂ւ�</a>�B';

if(!$con=mysql_connect("localhost","mysql","�r�p�k�̐ڑ��p�X���[�h�������ɏ���")){
  echo "�ڑ����s";                #��mysql�͂����ł�DB�̃��[�U��
  exit;
}

$db_id=mysql_select_db("bbs",$con);  #bbs�͂����ł�DB�̖��O
  if(!$db_id){echo "mysql_select_db���s<br>";}

if (!table_exist(SQLLOG)) {
  echo (SQLLOG."�e�[�u�����쐬���܂�<br>\n");
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
  if(!$result){echo "�e�[�u���쐬���s<br>";}
}

/* �L������ */
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
    if(!$find) error("�Y���L�����݂���܂���");
  }
  if($resno){
    if(!$treeline=mysql_call("select * from ".SQLLOG." where root>0 and no=".$resno." order by root desc")){echo "sql���s4<br>";}
  }else{
    if(!$treeline=mysql_call("select * from ".SQLLOG." where root>0 order by root desc")){echo "sql���s4<br>";}
  }

  //�Ō�̏������ݔԍ�
  if(!$result=mysql_call("select max(no) from ".SQLLOG)){echo "sql���s96<br>";}
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

    // URL�ƃ��[���Ƀ����N
    if($email) $name = "<a href=\"mailto:$email\">$name</a>";
    $com = auto_link($com);
    $com = eregi_replace("(^|>)(&gt;[^<]*)", "\\1<font color=".RE_COL.">\\2</font>", $com);
    // �摜�t�@�C����
    $img = $path.$tim.$ext;
    $src = IMG_DIR.$tim.$ext;
    // <img�^�O�쐬
    $imgsrc = "";
    if($ext){
      $size = $fsize;//alt�ɃT�C�Y�\��
      if($w && $h){//�T�C�Y�����鎞
        if(@is_file(THUMB_DIR.$tim.'s.jpg')){
          $imgsrc = "<small>�T���l�C����\�����Ă��܂�.�N���b�N����ƌ��̃T�C�Y��\�����܂�.</small><br><a href=\"".$src."\" target=_blank><img src=".THUMB_DIR.$tim.'s.jpg'.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }else{
          $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }
      }else{//����ȊO
        $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left hspace=20 alt=\"".$size." B\"></a>";
      }
      $dat.="�摜�^�C�g���F<a href=\"$src\" target=_blank>$tim$ext</a>-($size B)<br>$imgsrc";
    }
    // ���C���쐬
    $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
    $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
    if(!$resno) $dat.="[<a href=".PHP_SELF."?res=$no>�ԐM</a>]";
    $dat.="\n<blockquote>$com</blockquote>";

     // ���낻�������B
     if($lastno-LOG_MAX*0.95>$no){
      $dat.="<font color=\"#f00000\"><b>���̃X���͌Â��̂ŁA�������������܂��B</b></font><br>\n";
     }

    if(!$resline=mysql_call("select * from ".SQLLOG." where resto=".$no." order by no")){echo "sql���s5<br>";}
    $countres=mysql_num_rows($resline);
#      $dat.=$no.' ';//res�\��

    if(!$resno){
     $s=$countres - 10;
     if($s<0){$s=0;}
     elseif($s>0){
      $dat.="<font color=\"#707070\">���X".
             $s."���ȗ��B�S�ēǂނɂ͕ԐM�{�^���������Ă��������B</font><br>\n";
     }
    }else{$s=0;}

    while($resrow=mysql_fetch_row($resline)){ //res�̃��[�v
      if($s>0){$s--;continue;}
      list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,)=$resrow;
      if(!$no){break;}

      // URL�ƃ��[���Ƀ����N
      if($email) $name = "<a href=\"mailto:$email\">$name</a>";
      $com = auto_link($com);
      $com = eregi_replace("(^|>)(&gt;[^<]*)", "\\1<font color=".RE_COL.">\\2</font>", $com);
      // ���C���쐬
      $dat.="<table border=0><tr><td nowrap align=right valign=top>&gt;&gt;</td><td bgcolor=#F0E0D6>\n";
      $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
      $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
      $dat.="<blockquote>$com</blockquote>";
      $dat.="</td></tr></table>\n";
    }
    $dat.="<br clear=left><hr>\n";
    clearstatcache();//�t�@�C����stat���N���A
    mysql_free_result($resline);
    $p++;
    if($resno){break;} //res����tree1�s����
  }
$dat.='<table align=right><tr><td nowrap align=center>
<input type=hidden name=mode value=usrdel>�y�L���폜�z[<input type=checkbox name=onlyimgdel value=on>�摜��������]<br>
�폜�L�[<input type=password name=pwd size=8 maxlength=8 value="">
<input type=submit value="�폜"></form></td></tr></table>
<script language="JavaScript"><!--
l();
//--></script>';

    if(!$resno){ //res���͕\�����Ȃ�
      $prev = $st - PAGE_DEF;
      $next = $st + PAGE_DEF;
    // ���y�[�W����
      $dat.="<table align=left border=1><tr>";
      if($prev >= 0){
        if($prev==0){
          $dat.="<form action=\"".PHP_SELF2."\" method=get><td>";
        }else{
          $dat.="<form action=\"".$prev/PAGE_DEF.PHP_EXT."\" method=get><td>";
        }
        $dat.="<input type=submit value=\"�O�̃y�[�W\">";
        $dat.="</td></form>";
      }else{$dat.="<td>�ŏ��̃y�[�W</td>";}

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
        $dat.="<input type=submit value=\"���̃y�[�W\">";
        $dat.="</td></form>";
      }else{$dat.="<td>�Ō�̃y�[�W</td>";}
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

/* �w�b�_ */
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
[<a href="'.HOME.'" target="_top">�z�[��</a>]
[<a href="'.PHP_SELF.'?mode=admin">�Ǘ��p</a>]
<p align=center>
<font color="#800000" size=5>
<b><SPAN>'.TITLE.'</SPAN></b></font>
<hr width="90%" size=1>
';
}
/* ���e�t�H�[�� */
function form(&$dat,$resno,$admin=""){
  global $addinfo;
  $maxbyte = MAX_KB * 1024;
  $no=$resno;
  if($resno){
    $msg .= "[<a href=\"".PHP_SELF2."\">�f���ɖ߂�</a>]\n";
    $msg .= "<table width='100%'><tr><th bgcolor=#e04000>\n";
    $msg .= "<font color=#FFFFFF>���X���M���[�h</font>\n";
    $msg .= "</th></tr></table>\n";
  }
  if($admin){
    $hidden = "<input type=hidden name=admin value=\"".ADMIN_PASS."\">";
    $msg = "<h4>�^�O�������܂�</h4>";
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
<tr><td bgcolor=#eeaa88><b>���Ȃ܂�</b></td><td><input type=text name=name size="28"></td></tr>
<tr><td bgcolor=#eeaa88><b>E-mail</b></td><td><input type=text name=email size="28"></td></tr>
<tr><td bgcolor=#eeaa88><b>��@�@��</b></td><td><input type=text name=sub size="35">
<input type=submit value="���M����"></td></tr>
<tr><td bgcolor=#eeaa88><b>�R�����g</b></td><td><textarea name=com cols="48" rows="4" wrap=soft></textarea></td></tr>
';
if(!$resno){
$dat.='<tr><td bgcolor=#eeaa88><b>�Y�tFile</b></td>
<td><input type=file name=upfile size="35">
[<label><input type=checkbox name=textonly value=on>�摜�Ȃ�</label>]</td></tr>
';}
$dat.='<tr><td bgcolor=#eeaa88><b>�폜�L�[</b></td><td><input type=password name=pwd size=8 maxlength=8 value=""><small>(�L���̍폜�p�B�p������8�����ȓ�)</small></td></tr>
<script language="JavaScript"><!--
l();
//--></script>
<tr><td colspan=2>
<small>
<LI>�Y�t�\�t�@�C���FGIF, JPG, PNG �u���E�U�ɂ���Ă͐���ɓY�t�ł��Ȃ����Ƃ�����܂��B
<LI>�ő哊�e�f�[�^�ʂ� '.MAX_KB.' KB �܂łł��Bsage�@�\�t���B
<LI>�摜�͉� '.MAX_W.'�s�N�Z���A�c '.MAX_H.'�s�N�Z���𒴂���Ək���\������܂��B
'.$addinfo.'</small></td></tr></table></form></center><hr>';
}

/* �t�b�^ */
function foot(&$dat){
  $dat.='
<center>
<small><!-- GazouBBS v3.0 --><!-- �ӂ��Ή�0.8 -->
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
        <center><font color=red size=5><b>$mes<br><br><a href=".PHP_SELF2.">�����[�h</a></b></font></center>
        <br><br><hr size=1>";
  die("</body></html>");
}
/* �I�[�g�����N */
function auto_link($proto){
  $proto = ereg_replace("(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)","<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",$proto);
  return $proto;
}

function  proxy_connect($port) {
  $fp = @fsockopen ($_SERVER["REMOTE_ADDR"], $port,$a,$b,2);
  if(!$fp){return 0;}else{return 1;}
}
/* �L���������� */
function regist($name,$email,$sub,$com,$url,$pwd,$upfile,$upfile_name,$resto){
  global $path,$badstring,$badfile,$badip,$pwdc,$textonly;

  // ����
  $time = time();
  $tim = $time.substr(microtime(),2,3);

  // �A�b�v���[�h����
  if($upfile&&file_exists($upfile)){
    $dest = $path.$tim.'.tmp';
    move_uploaded_file($upfile, $dest);
    //���ŃG���[�Ȃ火�ɕύX
    //copy($upfile, $dest);
    $upfile_name = CleanStr($upfile_name);
    if(!is_file($dest)) error("�A�b�v���[�h�Ɏ��s���܂���<br>�T�[�o���T�|�[�g���Ă��Ȃ��\��������܂�",$dest);
    $size = getimagesize($dest);
    if(!is_array($size)) error("�A�b�v���[�h�Ɏ��s���܂���<br>�摜�t�@�C���ȊO�͎󂯕t���܂���",$dest);
    $md5 = md5_of_file($dest);
    foreach($badfile as $value){if(ereg("^$value",$md5)){
      error("�A�b�v���[�h�Ɏ��s���܂���<br>�����摜������܂���",$dest); //����摜
    }}
    chmod($dest,0666);
    $W = $size[0];
    $H = $size[1];
    $fsize = filesize($dest);
    if($fsize>MAX_KB * 1024) error("�A�b�v���[�h�Ɏ��s���܂���<br>�T�C�Y���傫�����܂�<br>".MAX_KB."K�o�C�g�܂�",$dest);
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

    // �摜�\���k��
    if($W > MAX_W || $H > MAX_H){
      $W2 = MAX_W / $W;
      $H2 = MAX_H / $H;
      ($W2 < $H2) ? $key = $W2 : $key = $H2;
      $W = ceil($W * $key);
      $H = ceil($H * $key);
    }
    $mes = "�摜 $upfile_name �̃A�b�v���[�h���������܂���<br><br>";
  }

  if($_FILES["upfile"]["error"]==2){
    error("�A�b�v���[�h�Ɏ��s���܂���<br>�摜�T�C�Y���傫�����܂�<br>".MAX_KB."K�o�C�g�܂�",$dest);
  }
  if($upfile_name&&$_FILES["upfile"]["size"]==0){
    error("�A�b�v���[�h�Ɏ��s���܂���<br>�摜�T�C�Y���傫�����邩�A<br>�܂��͉摜������܂���B",$dest);
  }

  //�Ō�̏������ݔԍ�
  if(!$result=mysql_call("select max(no) from ".SQLLOG)){echo "sql���s387<br>";}
  $row=mysql_fetch_array($result);
  $lastno=(int)$row[0];
  mysql_free_result($result);

  // ���O�s���I�[�o�[
  if(!$result=mysql_call("select no,ext,tim from ".SQLLOG." where no<=".($lastno-LOG_MAX))){echo "sql���s393<br>";}
  else{
    while($resrow=mysql_fetch_row($result)){
      list($dno,$dext,$dtim)=$resrow;
      if(!mysql_call("delete from ".SQLLOG." where no=".$dno)){echo "sql���s396<br>";}
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
    if(!$result = mysql_call("select * from ".SQLLOG." where root>0 and no=$resto")){echo "sql���s403<br>";}
    else{
      $find = mysql_fetch_row($result);
      mysql_free_result($result);
    }
    if(!$find) error("�X���b�h������܂���",$dest);
  }

  foreach($badstring as $value){if(ereg($value,$com)||ereg($value,$sub)||ereg($value,$name)||ereg($value,$email)){
  error("���₳��܂���(str)",$dest);};}
  if($_SERVER["REQUEST_METHOD"] != "POST") error("�s���ȓ��e�����Ȃ��ŉ�����(post)",$dest);
  // �t�H�[�����e���`�F�b�N
  if(!$name||ereg("^[ |�@|]*$",$name)) $name="";
  if(!$com||ereg("^[ |�@|\t]*$",$com)) $com="";
  if(!$sub||ereg("^[ |�@|]*$",$sub))   $sub=""; 

  if(!$resto&&!$textonly&&!is_file($dest)) error("�摜������܂���",$dest);
  if(!$com&&!is_file($dest)) error("���������ĉ�����",$dest);

  $name=ereg_replace("�Ǘ�","\"�Ǘ�\"",$name);
  $name=ereg_replace("�폜","\"�폜\"",$name);

  if(strlen($com) > 1000) error("�{�����������܂����I",$dest);
  if(strlen($name) > 100) error("�{�����������܂����I",$dest);
  if(strlen($email) > 100) error("�{�����������܂����I",$dest);
  if(strlen($sub) > 100) error("�{�����������܂����I",$dest);
  if(strlen($resto) > 10) error("�ُ�ł�",$dest);
  if(strlen($url) > 10) error("�ُ�ł�",$dest);

  //�z�X�g�擾
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);

  foreach($badip as $value){ //����host
   if(eregi("$value$",$host)){
    error("���₳��܂���(host)",$dest);
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
      error("�d�q�q�n�q�I�@���J�o�q�n�w�x�K�����I�I(80)",$dest);
    } elseif(proxy_connect('8080') == 1){
      error("�d�q�q�n�q�I�@���J�o�q�n�w�x�K�����I�I(8080)",$dest);
    }
  }

  // No.�ƃp�X�Ǝ��Ԃ�URL�t�H�[�}�b�g
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
  $youbi = array('��','��','��','��','��','��','�y');
  $yd = $youbi[gmdate("w", $time+9*60*60)] ;
  $now = gmdate("y/m/d",$time+9*60*60)."(".(string)$yd.")".gmdate("H:i",$time+9*60*60);
  if(DISP_ID){
    if($email&&DISP_ID==1){
      $now .= " ID:???";
    }else{
      $now.=" ID:".substr(crypt(md5($_SERVER["REMOTE_ADDR"].'id�̎�'.gmdate("Ymd", $time+9*60*60)),'id'),-8);
    }
  }
  //�e�L�X�g���`
  $email= CleanStr($email);  $email=ereg_replace("[\r\n]","",$email);
  $sub  = CleanStr($sub);    $sub  =ereg_replace("[\r\n]","",$sub);
  $url  = CleanStr($url);    $url  =ereg_replace("[\r\n]","",$url);
  $resto= CleanStr($resto);  $resto=ereg_replace("[\r\n]","",$resto);
  $com  = CleanStr($com);
  // ���s�����̓���B 
  $com = str_replace( "\r\n",  "\n", $com); 
  $com = str_replace( "\r",  "\n", $com);
  // �A�������s����s
  $com = ereg_replace("\n((�@| )*\n){3,}","\n",$com);
  if(!BR_CHECK || substr_count($com,"\n")<BR_CHECK){
    $com = nl2br($com);		//���s�����̑O��<br>��������
  }
  $com = str_replace("\n",  "", $com);	//\n�𕶎��񂩂�����B

  $name=ereg_replace("��","��",$name);
  $name=ereg_replace("[\r\n]","",$name);
  $names=$name;
  $name = CleanStr($name);
  if(ereg("(#|��)(.*)",$names,$regs)){
    $cap = $regs[2];
    $cap=strtr($cap,"&amp;", "&");
    $cap=strtr($cap,"&#44;", ",");
    $name=ereg_replace("(#|��)(.*)","",$name);
    $salt=substr($cap."H.",1,2);
    $salt=ereg_replace("[^\.-z]",".",$salt);
    $salt=strtr($salt,":;<=>?@[\\]^_`","ABCDEFGabcdef"); 
    $name.=" </b>��".substr(crypt($cap,$salt),-10)."<b>";
  }

  if(!$name) $name="������";
  if(!$com) $com="�{���Ȃ�";
  if(!$sub) $sub="����"; 

  // ��d���e�`�F�b�N
  $query="select time from ".SQLLOG." where com='".mysql_escape_string($com)."' ".
         "and host='".mysql_escape_string($host)."' ".
         "and no>".($lastno-20);  //�R�����g������
  if(!$result=mysql_call($query)){echo "sql���s510<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&!$upfile_name)error("�A�����e�͂������΂炭���Ԃ�u���Ă��炨�肢�v���܂�",$dest);

  $query="select time from ".SQLLOG." where time>".($time - RENZOKU)." ".
         "and host='".mysql_escape_string($host)."' ";  //�O�̓��e����Z����
  if(!$result=mysql_call($query)){echo "sql���s577<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&!$upfile_name)error("�A�����e�͂������΂炭���Ԃ�u���Ă��炨�肢�v���܂�",$dest);

  // �A�b�v���[�h����
  if($dest&&file_exists($dest)){

  $query="select time from ".SQLLOG." where time>".($time - RENZOKU2)." ".
         "and host='".mysql_escape_string($host)."' ";  //�O�̓��e����Z����
  if(!$result=mysql_call($query)){echo "sql���s586<br>";}
  $row=mysql_fetch_array($result);
  mysql_free_result($result);
  if($row&&$upfile_name)error("�摜�A�����e�͂������΂炭���Ԃ�u���Ă��炨�肢�v���܂�",$dest);

  //�摜�d���`�F�b�N
    $result = mysql_call("select tim,ext,md5 from ".SQLLOG." where md5='".$md5."'");
    if($result){
      list($timp,$extp,$md5p) = mysql_fetch_row($result);
      mysql_free_result($result);
#      if($timp&&file_exists($path.$timp.$extp)){ #}
      if($timp){
        error("�A�b�v���[�h�Ɏ��s���܂���<br>�����摜������܂�",$dest);
      }
    }
  }

  $restoqu=(int)$resto;
  if($resto){ //res,root�����̋��
    $rootqu="0";
    if(!$resline=mysql_call("select * from ".SQLLOG." where resto=".$resto)){echo "sql���s581<br>";}
    $countres=mysql_num_rows($resline);
    mysql_free_result($resline);
    if(!stristr($email,'sage') && $countres < MAX_RES){
      $query="update ".SQLLOG." set root=now() where no=$resto"; //res�Ȃ�age����
      if(!$result=mysql_call($query)){echo "sql���s527<br>";}
    }
  }else{$rootqu="now()";} //root�Ȃ猻����������
  
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
  if(!$result=mysql_call($query)){echo "sql���s2�o�^<br>";}  //post�L���̓o�^

    //�N�b�L�[�ۑ�
  setcookie ("pwdc", $c_pass,time()+7*24*3600);  /* 1�T�ԂŊ����؂� */
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
      setcookie ("namec", $c_name,time()+7*24*3600);  /* 1�T�ԂŊ����؂� */
    }
  }

  if($dest&&file_exists($dest)){
    rename($dest,$path.$tim.$ext);
    if(USE_THUMB){thumb($path,$tim,$ext);}
  }
  updatelog();

  echo "<html><head><META HTTP-EQUIV=\"refresh\" content=\"1;URL=".PHP_SELF2."\"></head>";
  echo "<body>$mes ��ʂ�؂�ւ��܂�</body></html>";
}

//�T���l�C���쐬
function thumb($path,$tim,$ext){
  if(!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG"))return;
  $fname=$path.$tim.$ext;
  $thumb_dir = THUMB_DIR;     //�T���l�C���ۑ��f�B���N�g��
  $width     = MAX_W;            //�o�͉摜��
  $height    = MAX_H;            //�o�͉摜����
  // �摜�̕��ƍ����ƃ^�C�v���擾
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
  // ���T�C�Y
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
  // �o�͉摜�i�T���l�C���j�̃C���[�W���쐬
  if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
    $im_out = ImageCreateTrueColor($out_w, $out_h);
  }else{$im_out = ImageCreate($out_w, $out_h);}
  // ���摜���c���Ƃ� �R�s�[���܂��B
  ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
  // �T���l�C���摜��ۑ�
  ImageJPEG($im_out, $thumb_dir.$tim.'s.jpg',60);
  chmod($thumb_dir.$tim.'s.jpg',0666);
  // �쐬�����C���[�W��j��
  ImageDestroy($im_in);
  ImageDestroy($im_out);
}
//gd�̃o�[�W�����𒲂ׂ�
function get_gd_ver(){
  if(function_exists("gd_info")){
    $gdver=gd_info();
    $phpinfo=$gdver["GD Version"];
  }else{ //php4.3.0�����p
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
//�t�@�C��md5�v�Z php4.2.0�����p
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
/* �e�L�X�g���` */
function CleanStr($str){
  global $admin;
  $str = trim($str);//�擪�Ɩ����̋󔒏���
  if (get_magic_quotes_gpc()) {//�����폜
    $str = stripslashes($str);
  }
  if($admin!=ADMIN_PASS){//�Ǘ��҂̓^�O�\
    $str = htmlspecialchars($str);//�^�O���֎~
    $str = str_replace("&amp;", "&", $str);//���ꕶ��
  }
  return str_replace(",", "&#44;", $str);//�J���}��ϊ�
}

//�e�[�u�������邩�ǂ��� 0=�Ȃ� 1=����
function table_exist($table){
  $result = mysql_call("show tables like '$table'");
  if(!$result){return 0;}
  $a = mysql_fetch_row($result);
  mysql_free_result($result);
  return $a;
}

/* ���[�U�[�폜 */
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
    if(!$result=mysql_call("select no,ext,tim,pwd,host from ".SQLLOG." where no=".$delno[$i])){echo "sql���s727<br>";}
    else{
      while($resrow=mysql_fetch_row($result)){
        list($dno,$dext,$dtim,$dpass,$dhost)=$resrow;
        if(substr(md5($pwd),2,8) == $dpass || substr(md5($pwdc),2,8) == $dpass ||
            $dhost == $host || ADMIN_PASS==$pwd){
          $flag = TRUE;
          $delfile = $path.$dtim.$dext;	//�폜�t�@�C��
          if(!$onlyimgdel){
            if(!mysql_call("delete from ".SQLLOG." where no=".$dno)){echo "sql���s736<br>";} //���ɏ����Ă邩��
          }
          if(is_file($delfile)) unlink($delfile);//�폜
          if(is_file(THUMB_DIR.$dtim.'s.jpg')) unlink(THUMB_DIR.$dtim.'s.jpg');//�폜
        }
      }
      mysql_free_result($result);
    }
  }
  if(!$flag) error("�Y���L����������Ȃ����p�X���[�h���Ԉ���Ă��܂�");
}

/* �p�X�F�� */
function valid($pass){
  if($pass && $pass != ADMIN_PASS) error("�p�X���[�h���Ⴂ�܂�");

  head($dat);
  echo $dat;
  echo "[<a href=\"".PHP_SELF2."\">�f���ɖ߂�</a>]\n";
  echo "[<a href=\"".PHP_SELF."\">���O���X�V����</a>]\n";
  echo "<table width='100%'><tr><th bgcolor=#E08000>\n";
  echo "<font color=#FFFFFF>�Ǘ����[�h</font>\n";
  echo "</th></tr></table>\n";
  echo "<p><form action=\"".PHP_SELF."\" method=POST>\n";
  // ���O�C���t�H�[��
  if(!$pass){
    echo "<center><input type=radio name=admin value=del checked>�L���폜 ";
    echo "<input type=radio name=admin value=post>�Ǘ��l���e<p>";
    echo "<input type=hidden name=mode value=admin>\n";
    echo "<input type=password name=pass size=8>";
    echo "<input type=submit value=\" �F�� \"></form></center>\n";
    die("</body></html>");
  }
}

/* �Ǘ��ҍ폜 */
function admindel($pass){
  global $path,$onlyimgdel;
  $delno = array(dummy);
  $delflag = FALSE;
  reset($_POST);
  while ($item = each($_POST)){
   if($item[1]=='delete'){array_push($delno,$item[0]);$delflag=TRUE;}
  }
  if($delflag){
    if(!$result=mysql_call("select * from ".SQLLOG."")){echo "sql���s814<br>";}
    $find = FALSE;
    while($row=mysql_fetch_row($result)){
      list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,)=$row;
      if($onlyimgdel==on){
        if(array_search($no,$delno)){//�摜�����폜
          $delfile = $path.$tim.$ext;	//�폜�t�@�C��
          if(is_file($delfile)) unlink($delfile);//�폜
          if(is_file(THUMB_DIR.$tim.'s.jpg')) unlink(THUMB_DIR.$tim.'s.jpg');//�폜
        }
      }else{
        if(array_search($no,$delno)){//�폜�̎��͋��
          $find = TRUE;
          if(!mysql_call("delete from ".SQLLOG." where no=".$no)){echo "sql���s827<br>";}
          $delfile = $path.$tim.$ext;	//�폜�t�@�C��
          if(is_file($delfile)) unlink($delfile);//�폜
          if(is_file(THUMB_DIR.$tim.'s.jpg')) unlink(THUMB_DIR.$tim.'s.jpg');//�폜
        }
      }
    }
    mysql_free_result($result);
    if($find){//���O�X�V
    }
  }
  // �폜��ʂ�\��
  echo "<input type=hidden name=mode value=admin>\n";
  echo "<input type=hidden name=admin value=del>\n";
  echo "<input type=hidden name=pass value=\"$pass\">\n";
  echo "<center><P>�폜�������L���̃`�F�b�N�{�b�N�X�Ƀ`�F�b�N�����A�폜�{�^���������ĉ������B\n";
  echo "<p><input type=submit value=\"�폜����\">";
  echo "<input type=reset value=\"���Z�b�g\">";
  echo "[<input type=checkbox name=onlyimgdel value=on><!--checked-->�摜��������]";
  echo "<P><table border=1 cellspacing=0>\n";
  echo "<tr bgcolor=6080f6><th>�폜</th><th>�L��No</th><th>���e��</th><th>�薼</th>";
  echo "<th>���e��</th><th>�R�����g</th><th>�z�X�g��</th><th>�Y�t<br>(Bytes)</th><th>md5</th><th>resto</th><th>tim</th><th>time</th>";
  echo "</tr>\n";

  if(!$result=mysql_call("select * from ".SQLLOG." order by no desc")){echo "sql���s864<br>";}
  $j=0;
  while($row=mysql_fetch_row($result)){
    $j++;
    $img_flag = FALSE;
    list($no,$now,$name,$email,$sub,$com,$host,$pwd,$ext,$w,$h,$tim,$time,$md5,$fsize,$root,$resto)=$row;
    // �t�H�[�}�b�g
    $now=ereg_replace('.{2}/(.*)$','\1',$now);
    $now=ereg_replace('\(.*\)',' ',$now);
    if(strlen($name) > 10) $name = substr($name,0,9).".";
    if(strlen($sub) > 10) $sub = substr($sub,0,9).".";
    if($email) $name="<a href=\"mailto:$email\">$name</a>";
    $com = str_replace("<br />"," ",$com);
    $com = htmlspecialchars($com);
    if(strlen($com) > 20) $com = substr($com,0,18) . ".";
    // �摜������Ƃ��̓����N
    if($ext && is_file($path.$tim.$ext)){
      $img_flag = TRUE;
      $clip = "<a href=\"".IMG_DIR.$tim.$ext."\" target=_blank>".$tim.$ext."</a><br>";
      $size = $fsize;
      $all += $size;			//���v�v�Z
      $md5= substr($md5,0,10);
    }else{
      $clip = "";
      $size = 0;
      $md5= "";
    }
    $bg = ($j % 2) ? "d6d6f6" : "f6f6f6";//�w�i�F

    echo "<tr bgcolor=$bg><th><input type=checkbox name=\"$no\" value=delete></th>";
    echo "<th>$no</th><td><small>$now</small></td><td>$sub</td>";
    echo "<td><b>$name</b></td><td><small>$com</small></td>";
    echo "<td>$host</td><td align=center>$clip($size)</td><td>$md5</td><td>$resto</td><td>$tim</td><td>$time</td>\n";
    echo "</tr>\n";
  }
  mysql_free_result($result);

  echo "</table><p><input type=submit value=\"�폜����$msg\">";
  echo "<input type=reset value=\"���Z�b�g\"></form>";

  $all = (int)($all / 1024);
  echo "�y �摜�f�[�^���v : <b>$all</b> KB �z";
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
