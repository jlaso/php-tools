<?php

   error_reporting( E_ALL & ~E_NOTICE & ~E_DEPRECATED );

   define ("_ROOT_", dirname(__FILE__));
   define ("_SERVER_", "http://".$_SERVER['SERVER_NAME'].'/'.basename(_ROOT_)."/");

   @define ("__DIR__",dirname(__FILE__));
   require_once __DIR__.'/config.php';


/**
 * Class DbTools
 *
 * @author jlaso <wld1373@gmail.com>
 * @revision 17.08.2014
 */
class DbTools
{

    /**
     * Connect with the DB_NAME and returns the object mysql to access
     *
     * @param string $dbname
     * @return mysqli
     */
    public static function connect($dbname = DB_NAME)
    {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, $dbname);
        } catch (Exception $e) {
            die ( "There is an error access to DB: ".$e->getMessage() );
        }

        return $conn;
    }

    /**
     * offline version 'dont requires db connection' of mysql_real_escape_string
     *
     * @param string $value
     * @return string
     */
    public static function my_real_escape_string($value)
    {
        $search = array("\x00", "\n", "\r", "\\", "'", "\"", "\x1a");
        $replace = array("\\x00", "\\n", "\\r", "\\\\" ,"\'", "\\\"", "\\\x1a");

        return str_replace($search, $replace, $value);
    }

    /**
     * filters $data removing malicious code
     *
     * @param string $data
     * @return string
     *
     */
    public static function filterData($data)
    {
        $data = trim(htmlentities(strip_tags($data)));

        if (get_magic_quotes_gpc()){
            $data = stripslashes($data);
        }
        $data = self::my_real_escape_string($data);

        return $data;
    }

    /**
     * returns an array with the databases of the MySQL server, DB_USER needs privileges
     *
     * @return array
     */
    public static function showDatabases()
    {
        $conn = DbTools::connect();
        $databases = array();
        $sql = "show databases";

        try {
            $result = $conn->query($sql);

            $num = $result->num_rows;
            if ($num) {
                for ($i=0; $i<$num; $i++) {
                    $databases[] = $result->fetch_assoc();
                }
                $result->free();
            }
            $conn->close();
        }catch (Exception $e) {
            $conn->close();
            die("Query failed: " . $e->getMessage());
        }

        return $databases;
    }

    /**
     * returns an array with the tables of $dbname
     *
     * @param string $dbname
     * @return array
     */
    public static function showTables($dbname)
    {

        $conn = DbTools::connect($dbname);
        $tables = array();
        $sql = "show tables";

        try {
            $result = $conn->query($sql);
            $num = $result->num_rows;
            if ($num) {
                for ($i=0; $i<$num; $i++) {
                    $tables[] = $result->fetch_assoc();
                }
                $result->free();
            }
            $conn->close();
        }catch (Exception $e) {
            $conn->close();
            die("Query failed: ".$e->getMessage());
        }

        return $tables;
    }

    /**
     * returns an array with description of each field of $table
     * @param string $dbname
     * @param string $table
     * @return array
     */
    public static function describeTable($dbname, $table)
    {
        $conn = DbTools::connect($dbname);
        $fields = array();
        $sql = "describe {$table}";

        try {
            $result = $conn->query($sql);
            $num = $result->num_rows;
            if ($num) {
                for ($i=0; $i<$num; $i++) {
                    $fields[] = $result->fetch_assoc();
                }
                $result->free();
            }
            $conn->close();
        }catch (Exception $e) {
            $conn->close();
            die("Query failed: ".$e->getMessage());
        }

        return $fields;
    }

    /**
     * returns the number of records of $table
     *
     * @param string $dbname
     * @param string $table
     * @return int
     */
    public static function numOfRecords($dbname, $table)
    {
        $conn = DbTools::connect($dbname);
        $num = 0;
        $sql = "select * from {$table}";

        try {
            $result = $conn->query($sql);
            $num = $conn->affected_rows;
            $conn->close();
        }catch (Exception $e) {
            $conn->close();
            die("Query failed: ".$e->getMessage());
        }

        return $num;
    }

    /**
     * returns pretty description of field l.ist
     * @param array $mixed
     * @param string $tab
     * @return string
     */
    public static function genFldLstFromDescribeTable($mixed, $tab="\t\t")
    {
        $result = "";
        foreach ($mixed as $elem){
            $field = $elem['Field'];
            $result .= (($result)? ",\n" : "")."{$tab}'{$field}' => null";
        }

        return $result;
    }

    /**
     * returns field list without id
     *
     * @param array $mixed
     * @param string $tab
     * @return string
     */
    public static function genFldLstWithoutID($mixed,$tab="\t\t")
    {
        $result = "";
        $i=1;
        foreach ($mixed as $elem){
            $field = $elem['Field'];
            if ($i++>1){
                $result .= (($result)? ",\n" : "")."{$tab}'{$field}' => null";
            }
        }

        return $result;
    }

    /**
     * returns field name list without id
     *
     * @param array $mixed
     * @param string $tab
     * @return string
     */
    public static function genFldNameLstWithoutID($mixed, $tab="\t\t")
    {
        $result = "";
        foreach ($mixed as $elem){
            $field = $elem['Field'];
            $result .= (($result)? ",\n" : $tab).$field;
        }
        return $result;
    }

    /**
     * returns a foreign list of fields for $table
     *
     * @param string $dbname
     * @param string $table
     * @return array
     */
    public static function describeForeigns($dbname, $table)
    {
        $sqlCreation = self::sqlCreation($dbname, $table);

        // find with regex CONSTRAINTS definitions
        $num = preg_match_all(
            "/CONSTRAINT\s*`(\w+)`\s*FOREIGN\sKEY\s*\(`(\w+)`\)\s*REFERENCES\s`(\w+)`\s*\(`(\w+)`\),?/",
            $sqlCreation,
            $matches,
            PREG_SET_ORDER
        );
        $fields = array();
        if ($num) {
            for ($i=0; $i<$num; $i++) {
                $fields[]= array (
                    'constraintName' => $matches[$i][1],
                    'foreignKey'     => $matches[$i][2],
                    'referencesDb'   => $matches[$i][3],
                    'referencesFld'  => $matches[$i][4]
                );
            }
        }

        return $fields;
    }

    /**
     * returns the SQL sentence needed to create a table
     *
     * @param string $dbname
     * @param string $table
     * @return string
     */
    public static function sqlCreation($dbname, $table)
    {
        $conn = DbTools::connect($dbname);
        $sql = "show create table {$table}";
        $sqlCreation = "";
        try {
            $result = $conn->query($sql);
            //$st=$result->fetch_assoc();
            if ($result) {
                $jj = $result->fetch_array();
                $sqlCreation = $jj[1];
            }
            $conn->close();
        }catch (Exception $e) {
            $conn->close();
            die("Query failed: ".$e->getMessage());
        }

        return $sqlCreation;
    }

}

/**
 * Class HtmlTools
 *
 * @author jlaso <wld1373@gmail.com>
 * @revision 17.08.2014
 */
class HtmlTools
{
    /**
     * content of a html file until </head> label
     * if specified load generic javascript plus $jq code
     * to use put this at the first of each PHP file that generates html code:
     *  <?php require_once 'php-tools.php'; ?>
     *  <?php echo HtmlTools::headContent(); ?>
     *
     * @param string $jq
     */
    public static function headContent($jq = null) 
    {
        $server = _SERVER_;
        
        echo <<<EOD
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" >
    <head>
        <link rel="image_src" href="img/logo.gif" />
        <meta property="og:description" content="my page" />
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta property="og:title" content="title" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="url" />
        <meta property="og:image" content="logo.gif" />
        <meta property="og:site_name" content="title" />
        <meta name="Robots" content="all" />
        <link rel="icon" href="favicon.ico" type="image/x-icon" />
        <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
    
        <title>my page</title>
    
        <link href="{$server}/css/styles.css" rel="stylesheet" type="text/css" />
        <!--[if IE]>
        <link href="{$server}/css/ie.css" rel="stylesheet" type="text/css" />
        <![endif]-->
EOD;
        if(isset($jq)){
            echo <<<EOD
            <link media="screen" rel="stylesheet" href="{$server}/css/colorbox.css" />
            <script type="text/javascript" src="{$server}/js/jquery.min.js"></script>
            <script type="text/javascript" src="{$server}/js/jquery.colorbox-min.js"></script>
EOD;
        echo $jq;
        }            
        echo <<<EOD
    </head>    
EOD;
    }
    
    /**
     * @param bool $return
     * @return string
     */
    public static function reloadJavascript($return = false)
    {
        $html = "<script type='text/javascript'>window.location.reload();</script>";
        if ($return){
            return $html;
        }else{
            print $html;
        }
    }
    
    /**
     * @param string $url
     * @param bool $return
     * @return string
     */
    public static function locationJavascript($url = "",$return = false)
    {
        if (!$url)  {
            $url = $_REQUEST['PHP_SELF'];
        }
        $html = "<script type='text/javascript'>window.location.href='{$url}';</script>";
        if ($return) {
            return $html;
        } else {
            print $html;
        }
    }
    
    /**
     * @param array $opts
     * @return string
     */
    public static function genSelectFromArray ( $opts = array() )
    {
        $defaultOptions = array (
            "selected"=> "",
            "name"    => "",
            "id"      => "",
            "multiple"=> FALSE,
            "optgroup"=> "",
            "size"    => 1,
            "data"    => array(),
            "fldkey"  => "id",
            "fldname" => "name",
            "select"  => true
        );
        $options = array_merge($defaultOptions, $opts);
    
        $id       = ($options['id'])? "id='".$options['id']."'" : "";
        $name     = ($options['name'])? "name='".$options['name']."'" : "";
        $multiple = ($options['multiple'])? "MULTIPLE" : "";
        $optgroup = ($options['optgroup'])? "<optgroup label='".$options['optgroup']."'>" : "";
        $selected = $options['selected'];
        $size     = "size='".intval($options['size'])."'";
        $fldkey   = $options["fldkey"];
        $fldname  = $options["fldname"];
    
        // listado de registros
        $U = $options["data"];
    
        if ($options['select'])
            $html  = "<SELECT {$id} {$name} {$multiple} {$size} >";
        $html .= $optgroup;
        foreach ( $U as $elem ) {
            $desc  = $elem[$fldname];
            $key   = $elem[$fldkey];
            $html .= "<OPTION VALUE='{$key}'".(($desc == $selected)? " SELECTED >" : ">").$desc."</OPTION>";
        }
        $html .= ($optgroup)? "</optgroup>" : "";
        if ($options['select'])
            $html .= "</SELECT>";
    
        return $html;
    }
    
    /**
     * @param string $fileName
     * @param array $opts
     * @return mixed|string
     */
    public static function template($fileName, $opts = array())
    {
        $result = "";
        if ($fileName) {
            $file = file_get_contents($fileName);
            if ($opts) {
                $search = array();
                $replace= array();
                foreach ($opts as $key=>$value) {
                    $search[]=$key;
                    $replace[]=$value;
                }
                $result = str_replace($search, $replace, $file);
            }
        }
    
        return $result;
    }
    
    /**
     * spanish pluralization of a word
     *
     * @param string $data
     * @return string
     */
    public static function plural($data)
    {
        $lowercase = strtolower($data);
        if (substr($lowercase, -1) != "s") {
            if (preg_match("/.*(a|e|i|o|u)s$/", $lowercase)) {
                return $data."s";
            }else {
                return $data."es";
            }
        }else return $data."os";
    }
    
    /**
     * @param string $data
     * @return string
     */
    public static function capitalize($data)
    {
        return strtoupper($data[0]).strtolower(substr($data, 1));
    }
    
    /**
     * $arr must to have two dimensions: first numeric and second associative
     *
     * @param array $arr
     * @param null $id
     * @return string
     */
    public static function genTableFromMixed ($arr = array(), $id = null)
    {
        $id = (isset($id)) ? "id='{$id}'" : "";
        $html = "<table {$id}>\n\t<thead>\n";
        foreach ($arr[0] as $key=>$value) {
            $html .= "\t\t<td>{$key}</td>\n";
        }
        $html .= "\t</thead>\n";
        foreach ($arr as $subarray) {
            $html .= "\t<tr>\n";
            foreach ($subarray as $key=>$value) {
                $html .= "\t\t<td>{$value}</td>\n";
            }
            $html .= "\t</tr>\n";
        }
        $html .= "</table>\n";
    
        return $html;
    }

}


/**
 * Class SessionTools
 */
class SessionTools 
{
    /**
     *
     * check if user is loged
     * 
     * @global mysql $db
     */
    function isLoged() 
    {
        @session_start();

        global $db;

        // check user agent
        if (isset($_SESSION['HTTP_USER_AGENT'])) {
            if ($_SESSION['HTTP_USER_AGENT'] != md5($_SERVER['HTTP_USER_AGENT'])) {
                self::logout();
                exit;
            }
        }

        /* If session not set, check for cookies set by Remember me */
        if (!isset($_SESSION['usr_email']) && !isset($_SESSION['created_at']) )
        {
                if(isset($_COOKIE['usr_email']) && isset($_COOKIE['created_at'])){
                /* we double check cookie expiry time against stored in database */
                $cookie_user_email  = DbTools::filterData($_COOKIE['usr_email']);
                $sessionKeyQry = mysql_query("select `session_key`,`last_access` from `users` where `email` ='{$cookie_user_email}'")
                    or die(mysql_error());
                list($sessionkey,$lastaccess) = mysql_fetch_row($sessionKeyQry);
                // check if cookie has expired
                if( (time() - time($lastaccess)) > 60*60*24*APP_COOKIE_EXPIRA) {
                    self::logout();
                }

                /* Security check with untrusted cookies - dont trust value stored in cookie.
                /* We also do authentication check of the `ckey` stored in cookie matches that stored in database during login*/

                 if( !empty($sessionkey) && MyTools::isEmail($_COOKIE['usr_email']) && $_COOKIE['usr_session_key'] == sha1($sessionkey)  ) {
                          session_regenerate_id(); //against session fixation attacks.

                          $_SESSION['usr_email'] = $_COOKIE['usr_email'];
                          $_SESSION['created_at'] = $_COOKIE['created_at'];
                          $_SESSION['usr_administrator'] = $_COOKIE['usr_administrator'];
                          $_SESSION['HTTP_USER_AGENT'] = md5($_SERVER['HTTP_USER_AGENT']);

                   } else {
                          self::logout();
                   }

          } else {
              // return to homepage
              header("Location: /");
              exit();
          }
        }
    }

    /**
     *
     * @global array $get
     * @global array $data
     * @param string $postAcc ,  valor de la variable POST accion
     * @return boolean
     *
     * pasa todos los $_POST a $data y todos los $_GET a  $get  y si se le pasa
     * una acción en $postAcc devuelve TRUE si $_POST["accion"]==$postAcc
     */
    function getAndPost ($postAcc = null)
    {
        global $get;
        global $data;
    
        foreach($_GET as $key => $value) {
            $get[$key] = DbTools::filterData($value);
        }
    
        if (isset($_POST['accion']) && ($_POST['accion']==$postAcc)){
            foreach($_POST as $key => $value) {
               $data[$key] = DbTools::filterData($value);
            }
            return TRUE;
        }else {
            return FALSE;
        }
    }

    public static function logout()
    {
        unset($_SESSION['usr_email']);
        // return to homepage
        header("Location: /");
        exit();
    }

}

class MyTools 
{

    /**
     *
     * @param string $url
     * @return string
     *
     * devuelve una cadena en la que se han sustituido los espacios por guiones bajos
     *
     */
    public static function EncodeURL($url) {
        $new = strtolower(ereg_replace(' ','_',$url));
        return($new);
    }
    
    /**
     *
     * @param string $url
     * @return string
     *
     * devuelve una cadena en la que se restablecen los guiones bajos a espacios
     */
    public static function DecodeURL($url){
        $new = ucwords(ereg_replace('_',' ',$url));
        return($new);
    }
    
    /**
     *
     * @param string $str
     * @param int $len
     * @return string
     *
     * si la cadena pasada como argumento excede de la longitud, se corta añadiendo ...
     */
    public static function puntosSuspensivos($str, $len) {
        if (strlen($str) < $len)
            return $str;
    
        $str = substr($str,0,$len);
        if ($spc_pos == strrpos($str," "))
                $str = substr($str,0,$spc_pos);
    
        return $str . "...";
    }	
    
    /**
     *
     * @param string $email
     * @return boolean
     *
     * comprueba mediante expresiones regulares si una cadena  parece un email
     */
    public static function isEmail($email)
    {
        return preg_match('/^\S+@[\w\d.-]{2,}\.[\w]{2,6}$/iU', $email) ? TRUE : FALSE;
    }
    
    /**
     *
     * @param string $id
     * @return boolean
     *
     * comprueba si la cadena pasada parece un ID
     */
    public static function esID($id){
        return preg_match('/^[a-z\d_]{5,20}$/i', $id) ? TRUE : FALSE;
     }	
    
    /**
    *
    * @param string $url
    * @return boolean
    *
    * comprueba si la cadena pasada 'parece' una URL
    */
    public static function esURL($url) {
        return preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $url) ? TRUE : FALSE;
    } 
    
    /**
     *
     * @param string $x
     * @param string $y
     * @return boolean
     *
     * comprueba si la contraseña y repita contraseña coinciden y son mayores de 4
     */
    public static function checkPwd($x,$y) {
        if (empty($x) || empty($y) )            return false;
        if (strlen($x) < 4 || strlen($y) < 4)   return false;
        if (strcmp($x,$y) != 0)                 return false;
        return true;
    }
    
    /**
     *
     * @param int $length
     * @return string
     *
     * genera una contraseña aleatoria de la longitud indicada (por defecto 7)
     *
     */
    public static function genPassword($length = 7, $repeticiones = false){
      $password = "";
      $possible = "0123456789bBcCdDfFgGhHjJkKmMnNpPqQrRsStTvVwWxXyYzZ$#?+"; //sin vocales para evitar palabras que suenen
    
      if (!$repeticiones && ($length>count_chars($posible))) return -1;
    
      $i = 0; 
        
      while ($i < $length) { 
        $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);     
        if ($repeticiones || !strstr($password, $char)) {
          $password .= $char;
          $i++;
        }
      }
    
      return $password;
    }
    
    /**
     *
     * @param int $length
     * @return string
     *
     * genera una key aleatoria de la longitud indicada (por defecto 7)
     * a diferencia del password, la key puede contener vocales
     */
    public static function genKey($length = 7, $repeticiones = false){
      $password = "";
      $possible = "0123456789abcdefghijkmnopqrstuvwxyz";
    
      if (!$repeticiones && ($length>count_chars($posible))) return -1;
    
      $i = 0; 
      while ($i < $length) { 
        $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
        if ($repeticiones || !strstr($password, $char)) {
          $password .= $char;
          $i++;
        }
      }
      return $password;
    }
    
    /**
     *
     * @param string $email
     * @return string
     *
     * sustituye la arroba de un email por un guión bajo
     *
     */
    public static function slugEmail ($email) {
        return str_replace("@", "_", $email);
    }
    
    /**
     *
     * @param string $email
     * @return string
     *
     * convierte el guión bajo en una arroba
     */
    public static function deslugEmail ($email) {
        return str_replace("_", "@", $email);
    }


}

class DirTools {
    /**
     *
     * @param string $dirname
     * @param boolean $solofotos
     * @return array
     *
     * inspecciona el directorio pasado como argumento y almacena todos sus archivos
     * (o sólo las fotos) en un array que luego se devuelve
     *
     */
    public static function dirToArray ($dirname, $solofotos=1) {
        $a = array();
        $directorio=opendir($dirname);
        while ($archivo = readdir($directorio)) {
           if (!(($archivo=='.') || ($archivo=='..'))) {
               if (@filetype($dirname."/".$archivo)=="file") {
               if ($solofotos) {
                  $ext=extensionArchivo($archivo);
                  if (in_array($ext, array('jpg','gif','png')))
                     $a[]=$archivo;
               }else{
                  $a[]=$archivo;               
               }
           }
        }
        }
        closedir($directorio);
        sort($a);
        return $a;
    }
    
    /**
     *
     * @param string $base
     * @param string $opcion1
     * @param string $opcion2
     * @return array
     *
     * devuelve un array que contiene como primer elemento la base + opcion1 y
     * como segundo la base + opcion2
     * este útil se gasta para las carpetas base y la de miniaturas
     *
     */
    public static function opcionBase ( $base, $opcion1, $opcion2 ){
        return array ($base.$opcion1, $base.$opcion2);
    }
    
    
    /**
     *
     * @param array $a
     * @return string
     *
     * funciona como array_rand($a); devuelve un elemento del array al azar
     * utilizado para elegir una foto de un directorio al azar para presentarla
     *
     */
    public static function randomElemArray($a) {
        $elem = rand(0, sizeof($a)-1);
        return $a[$elem];
    }

}


/**
 * Class Calendar
 */

class Calendar 
{

   private $day;
   private $month;
   private $year;
   private $monthes;
   private $myselfphp;

   public function __construct($myself,$year=0,$month=0,$day=0){
       $this->months = $this->months();
       $this->myselfphp = (empty($myself))?$_SERVER['PHP_SELF']:$myself;
       $this->year = ($year)?intval($year):date("Y");
       $this->month  = ($month)?intval($month):date("m");
       $this->day  = ($day)?intval($day):date("d");
   }

   public function setMonth($month=0)   { $this->month  = ($month)?$month:date("m"); }
   public function getMonth()         { return $this->month; }
   public function setYear($year=0) { $this->year = ($year)?$year:date("Y");}
   public function getYear()        { return $this->year; }

    /**
     * @param int $day
     */
    public function setDia($day=0)
    {
        $this->day = ($day)?intval($day):date("d");
    }

    /**
     * @return int
     */
     public function getDia()
     {
         return $this->day;
     }

    /**
     * @param int $deltaMonth
     * @param int $deltaYear
     * @param bool $print
     * @return string
     */
    public function getGetJsClause($deltaMonth=0,$deltaYear=0,$print = true)
   {
       $year = $deltaYear + $this->year;
       $month  = $deltaMonth  + $this->month;
       if ($month<1) {
           $month = 12;
           $year--;
       }
       if ($month>12) {
           $month = 1;
           $year++;
       }
       //if ($deltaMonth || $deltaYear) $result .= '?month='.$month.'&year='.$year;
       $result = "getCalendar( {$this->day},{$month},{$year});";
       if ($print) print $result; else    return  $result;
   }

   public function getGetJsClause2($month,$year) {
       if ($month===0) $month = $this->getMonth();
       if ($year===0) $year=$this->getYear();
       $result = "getCalendar( 1,{$month},{$year});";
       return  $result;
   }

    /**
     * @param int $day
     * @return string
     */
     public function getAsYYYY_MM_DD($day=-1)
     { 
         if ($day===0) {
             return "";
         }
         if ($day===-1) {
             $day = $this->day;
         }
         
         return sprintf("%04d-%02d-%02d",$this->year,$this->month,$day);        
     }

    /**
     * @param $day
     * @return string
     */
    public function getAsYYYYMMDD($day=-1)
   { 
       if ($day===0) return "";
       if ($day===-1) $day = $this->day;
       return sprintf("%04d%02d%02d",$this->year,$this->month,$day);        
   }   
   
   public function getAsDate($dd=0) { return date("Y-m-d",  mktime(0, 0, 0, $this->month, ($dd)?$dd:$this->day , $this->year)); }

    /**
     * @param string $lang
     * @return array
     */
    public function months( $lang = "es")
   {
       switch($lang){
           case "es":
               $r = array(
                   "", // first blank to start with index 1
                   "Enero",
                   "Febrero",
                   "Marzo",
                   "Abril",
                   "Mayo",
                   "Junio",
                   "Julio",
                   "Agosto",
                   "Setiembre",
                   "Octubre",
                   "Noviembre",
                   "Diciembre"
               );
               break;
           
           case "en":
           default:
               $r = array(
                   "", // first blank to start with index 1
                   "January",
                   "February",
                   "Mar",
                   "April",
                   "May",
                   "Juny",
                   "July",
                   "August",
                   "Sepitember",
                   "October",
                   "November",
                   "December"
               );
               break;
       }
       return $r;
   }

   /**
    * to generate month select
    */
   public function selectMonth()
   {
       $html = "<select id='month' onchange='".$this->getGetJsClause2( 'this.value', 0 )."'>";
       for ($i=1;$i<13;$i++){
           $html .=  "<option name='{$i}' value='{$i}'";
           if ($this->month==$i) $html.=" selected ";
           $html .= ">".$this->months[$i]."</option>";
       }
       $html .= "</select>";
       return $html;
   }

   /*
    * para generar el selector de año
    */
   public function selectYear($from=1970, $to=2100)
   {
       $html = "<select id='year' onchange='".$this->getGetJsClause2( 0, 'this.value' )."'>";
       for ($i=$from;$i<=$to;$i++){
           $html .=  "<option name='{$i}' value='{$i}'";
           if ($this->year==$i) $html.=" selected ";
           $html .= ">".$i."</option>";
       }
       $html .= "</select>";
       return $html;
   }

    /**
     * 
     */
    public function adjustRequestDate()
    {
       $this->year = (isset($_REQUEST['year']) && intval($_REQUEST['year'])) ? intval($_REQUEST['year']) : date("Y");   
       $this->month  = (isset($_REQUEST['month'])  && intval($_REQUEST['month'] )) ? intval($_REQUEST['month'] ) : date("m");
       /*if ($this->month<1) {  
           $this->month = 12;
           $this->year--;
       }
       if ($this->month>12) {
           $this->month = 1;
           $this->year++;
       }*/
       $this->day  = (isset($_REQUEST["day"])  && intval($_REQUEST['day'])) ? intval($_REQUEST["day"])  :  date("d");
    }

    /**
     * return first day
     * 
     * @return mixed
     */
     public function firstDay()
     {
         $days = array(7,1,2,3,4,5,6);  // day sort (1st is sunday)
         
         return $days[date("w", mktime(0, 0, 0, $this->month, 1, $this->year)) ];
     }

    /**
     * return last day of month
     * 
     * @return int
     */
    public function lastDay()
    {
        return intval( date("t",mktime(0, 0, 0, $this->month, 1, $this->year)) );
    }
   
    /**
     * return number of day of the monday in this week
     * 
     * @return int
     */
    public function mondayWeek() 
    {      
        // 0=sunday
        $dayofweek = date("w", mktime(0, 0, 0, $this->month, $this->day, $this->year));
        return $this->day - $dayofweek + 1;       
    }

    /**
     * @param string $str
     * @param bool $date
     * @return int|string
     */
    public static function fromSpanishDate($str, $date=false)
   {
       if (!$str) {
           return '';
       }
       // dd/mm/YYYY HH:MM
       $day   = intval(substr($str, 0, 2));
       $month = intval(substr($str, 3, 2));
       $year  = intval(substr($str, 6, 4));
       $hour  = intval(substr($str, 11, 2));
       $min   = intval(substr($str, 14, 2));
       $ret   = mktime($hour, $min, 0, $month, $day, $year);
       if ($date) {
           return $ret;
       }else{
           return date("Y-m-d H:i", $ret);
       }
   }

    /**
     * @param string $str
     * @return null|string
     */
    public static function fromEnglishDate($str)
   {
       $date = DateTime::createFromFormat("Y-m-d H:i:s", $str);
       if (!$date) {
           return null;
       }
       try {
           $ret = $date->format("d/m/Y H:i");
       }catch(Exception $e){
           print "Error: ".$e->getMessage();
           $ret = null;
       }
       return $ret;
   }
}

/**
 * Class strTools
 */
class strTools 
{
    /**
     * @param $char
     * @param $long
     * @return string
     */
    public static function gen($char, $long)
    {
        $ret = "";
        for ($i=0;$i<$long;$i++) {
            $ret .= $char;
        }
        
        return $ret;
    }
    
    /**
     * dirt a string from $start to $start+$long-1 with the $char passed
     */
    public static function dirt($str, $char, $start, $long)
    {
        $tot = strlen($str);
        $s = self::gen($char,$long);
        $a = substr($str, 0, $start-1);
        $b = substr($str, $start+$long, $tot);
        
        return $a.$s.$b;
    }
}

?>
