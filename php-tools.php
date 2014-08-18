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

class HtmlTools {
    /**
     *
     * @param string $jq
     *
     * contenido de la cabecera de cada archivo html hasta la etiqueta de cierre </head>
     * si se especifica $jq se cargan los javascript genéricos más el código de $jq
     * hay que poner esto al  principio de cada archivo php de este sitio web
     *  <?php include 'includes/cargarConfig.php'; ?>
     *  <?php include 'includes/funciones.php'; ?>
     *  <?php echo headContent(); ?>
     */
    public static function headContent($jq = null) {  // indicar en $jq si hay que cargar jquery
        ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" >
    <head>
    <link rel="image_src" href="img/logo.gif" />
    <meta property="og:description" content="mi página" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta property="og:title" content="titulo" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="url" />
    <meta property="og:image" content="logo.gif" />
    <meta property="og:site_name" content="titulo" />
    <meta name="Robots" content="all" />
    <link rel="icon" href="favicon.ico" type="image/x-icon" />
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />

    <title>mi p&aacute;gina</title>

    <link href="<?php echo _SERVER_ ?>css/estilos.css" rel="stylesheet" type="text/css" />
    <!--[if IE]>
    <link href="<?php echo _SERVER_ ?>css/ie.css" rel="stylesheet" type="text/css" />
    <![endif]-->
    <?php if (isset($jq)): ?>
        <link media="screen" rel="stylesheet" href="<?php echo _SERVER_ ?>css/colorbox.css" />
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="<?php echo _SERVER_ ?>js/jquery.colorbox-min.js"></script>
        <?php echo $jq ?>
    <?php endif; ?>
    </head>
    <?php
    }

    public static function reloadJavascript($retorna = false){
        $html = "<script type='text/javascript'>window.location.reload();</script>";
        if ($retorna)  return $html; else print $html;
    }

    public static function locationJavascript($url = "",$retorna = false){
        if (!$url)  $url = $_REQUEST['PHP_SELF'];
        $html = "<script type='text/javascript'>window.location.href='{$url}';</script>";
        if ($retorna)  return $html; else print $html;
    }

    public static function genSelectFromArray ( array $opcs = null ) {
        $opcsDefault = array (
            "selected"=> "",
            "name"    => "",
            "id"      => "",
            "multiple"=> FALSE,
            "optgroup"=> "",
            "size"    => 1,
            "data"    => array(),
            "fldkey"  => "id",
            "fldname" => "nombre",
            "select"  => true
        );
        $opciones = array_merge($opcsDefault, (array) $opcs);
        
        $id       = ($opciones['id'])? "id='".$opciones['id']."'" : "";
        $name     = ($opciones['name'])? "name='".$opciones['name']."'" : "";
        $multiple = ($opciones['multiple'])? "MULTIPLE" : "";
        $optgroup = ($opciones['optgroup'])? "<optgroup label='".$opciones['optgroup']."'>" : "";
        $selected = $opciones['selected'];
        $size     = "size='".intval($opciones['size'])."'";
        $fldkey   = $opciones["fldkey"];
        $fldname  = $opciones["fldname"];
        
        // listado de registros
        $U = $opciones["data"];

        if ($opciones['select']) 
            $html  = "<SELECT {$id} {$name} {$multiple} {$size} >";
        $html .= $optgroup;
        foreach ( $U as $elem ) {
           $desc  = $elem[$fldname]; 
           $key   = $elem[$fldkey];
           $html .= "<OPTION VALUE='{$key}'".(($desc == $selected)? " SELECTED >" : ">").$desc."</OPTION>";
        }        
        $html .= ($optgroup)? "</optgroup>" : "";
        if ($opciones['select']) 
            $html .= "</SELECT>";
        
        return $html;                       
    }
    
    public static function plantilla ( $archivo, array $opcs = null ) {
        if ($archivo) {
            $file = file_get_contents($archivo);
            if ($opcs) {
                $search = array();
                $replace= array();
                foreach ($opcs as $key=>$value) {
                    $search[]=$key;
                    $replace[]=$value;
                }
                $result = str_replace($search, $replace, $file);
            }
            return $result;
        }else{
            return "";
        }
    }
    
    public static function plural ($dato){
        $minusc = strtolower($dato);
        if (substr($minus, -1) != "s") {
            if (preg_match("/.*(a|e|i|o|u)s$/", $minusc)) {
                return $dato."s";
            }else {
                return $dato."es";
            }
        }else return $dato."os";
    }
    
    public static function capitalizar($tabla){
        return strtoupper($tabla[0]).strtolower(substr($tabla, 1));
    }
    
    // el array tiene que ser bidimensional, primera dimensión numérica, segunda
    // asociativa
    public static function genTableFromMixed (array $arr, $id=null){
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

class SesionTools {
    /**
     *
     * @global mysql $db
     *
     * comprueba si el usuario está logado
     * esta función no está 100% testada
     */
    function estaLogado() {
        session_start();

        global $db;

        // comprobar user agent
        if (isset($_SESSION['HTTP_USER_AGENT'])) {
            if ($_SESSION['HTTP_USER_AGENT'] != md5($_SERVER['HTTP_USER_AGENT'])) {
                logout();
                exit;
            }
        }

        // clave de sesión

        /* If session not set, check for cookies set by Remember me */
        if (!isset($_SESSION['usr_email']) && !isset($_SESSION['usr_fechaalta']) )
        {
                if(isset($_COOKIE['usr_email']) && isset($_COOKIE['usr_fechaalta'])){
                /* we double check cookie expiry time against stored in database */
                $cookie_usr_email  = filtrarDatos($_COOKIE['usr_email']);
                $q_clavesesion = mysql_query("select `clavesesion`,`ultimoacceso` from `users` where `email` ='$cookie_user_email'")
                    or die(mysql_error());
                list($clavesesion,$ultimoacceso) = mysql_fetch_row($q_clavesesion);
                // comprobar si la cookie ha expirado
                if( (time() - time($ultimoacceso)) > 60*60*24*APP_COOKIE_EXPIRA) {
                        logout();
                }

                /* Security check with untrusted cookies - dont trust value stored in cookie.
                /* We also do authentication check of the `ckey` stored in cookie matches that stored in database during login*/

                 if( !empty($clavesesion) && esEmail($_COOKIE['usr_email']) && $_COOKIE['usr_clavesesion'] == sha1($clavesesion)  ) {
                          session_regenerate_id(); //against session fixation attacks.

                          $_SESSION['usr_email'] = $_COOKIE['usr_email'];
                          $_SESSION['usr_fechaalta'] = $_COOKIE['usr_fechaalta'];
                          $_SESSION['usr_administrador'] = $_COOKIE['usr_administrador'];
                          $_SESSION['HTTP_USER_AGENT'] = md5($_SERVER['HTTP_USER_AGENT']);

                   } else {
                          logout();
                   }

          } else {
              // volvemos a la página de login
              header("Location: login.php");
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
function getYpost ($postAcc = null){
    global $get;
    global $data;

    foreach($_GET as $key => $value) {
	$get[$key] = filtrarDatos($value);
    }

    if (isset($_POST['accion']) && ($_POST['accion']==$postAcc)){
        foreach($_POST as $key => $value) {
                   $data[$key] = filtrarDatos($value);
                }
        return TRUE;
    }else {
        return FALSE;
    }
}


}

class MyTools {

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
public static function esEmail($email){
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


//*********************** calendario

class Calendar {

   private $dia;
   private $mes;
   private $anyo;
   private $meses;
   private $myselfphp;

   public function __construct($myself,$anyo=0,$mes=0,$dia=0){
       $this->meses = $this->meses();
       $this->myselfphp = (empty($myself))?$_SERVER['PHP_SELF']:$myself;
       $this->anyo = ($anyo)?intval($anyo):date("Y");
       $this->mes  = ($mes)?intval($mes):date("m");
       $this->dia  = ($dia)?intval($dia):date("d");
   }

   public function setMes($mes=0)   { $this->mes  = ($mes)?$mes:date("m"); }
   public function getMes()         { return $this->mes; }
   public function setAnyo($anyo=0) { $this->anyo = ($anyo)?$anyo:date("Y");}
   public function getAnyo()        { return $this->anyo; }
   public function setDia($dia=0)   { $this->dia  = ($dia)?intval($dia):date("d");}
   public function getDia()         { return $this->dia;}
   
   public function getGetJsClause($deltaMes=0,$deltaAnyo=0,$print = true) {
       $anyo = $deltaAnyo + $this->anyo;
       $mes  = $deltaMes  + $this->mes;
       if ($mes<1) {
           $mes = 12;
           $anyo--;
       }
       if ($mes>12) {
           $mes = 1;
           $anyo++;
       }
       //if ($deltaMes || $deltaAnyo) $result .= '?mes='.$mes.'&anyo='.$anyo;
       $result = "getCalendar( {$this->dia},{$mes},{$anyo});";
       if ($print) print $result; else    return  $result;
   }

   public function getGetJsClause2($mes,$anyo) {
       if ($mes===0) $mes = $this->getMes();
       if ($anyo===0) $anyo=$this->getAnyo();
       $result = "getCalendar( 1,{$mes},{$anyo});";
       return  $result;
   }

   public function getAsAAAA_MM_DD($dia=-1){ 
       if ($dia===0) return "";
       if ($dia===-1) $dia = $this->dia;
       return sprintf("%04d-%02d-%02d",$this->anyo,$this->mes,$dia);        
   }
   
   public function getAsAAAAMMDD($dia=-1){ 
       if ($dia===0) return "";
       if ($dia===-1) $dia = $this->dia;
       return sprintf("%04d%02d%02d",$this->anyo,$this->mes,$dia);        
   }   
   
   public function getAsDate($dd=0) { return date("Y-m-d",  mktime(0, 0, 0, $this->mes, ($dd)?$dd:$this->dia , $this->anyo)); }

   public function meses(){
       $r = array("",  // primero en blanco para empezar en el índice 1
                  "Enero"  ,"Febrero"  ,"Marzo",
                  "Abril"  ,"Mayo"     ,"Junio",
                  "Julio"  ,"Agosto"   ,"Setiembre",
                  "Octubre","Noviembre","Diciembre"
                 );
       return $r;
   }

   /*
    * para generar el selector de mes
    */
   public function selectMes(){
       $html = "<select id='mes' onchange='".$this->getGetJsClause2( 'this.value', 0 )."'>";
       for ($i=1;$i<13;$i++){
           $html .=  "<option name='{$i}' value='{$i}'";
           if ($this->mes==$i) $html.=" selected ";
           $html .= ">".$this->meses[$i]."</option>";
       }
       $html .= "</select>";
       return $html;
   }

   /*
    * para generar el selector de año
    */
   public function selectAnyo($desde=1970,$hasta=2100){
       $html = "<select id='anyo' onchange='".$this->getGetJsClause2( 0, 'this.value' )."'>";
       for ($i=$desde;$i<=$hasta;$i++){
           $html .=  "<option name='{$i}' value='{$i}'";
           if ($this->anyo==$i) $html.=" selected ";
           $html .= ">".$i."</option>";
       }
       $html .= "</select>";
       return $html;
   }
   public function ajustFechaRequest(){
       $this->anyo = (isset($_REQUEST['anyo']) && intval($_REQUEST['anyo'])) ? intval($_REQUEST['anyo']) : date("Y");   // año con 4 cifras
       $this->mes  = (isset($_REQUEST['mes'])  && intval($_REQUEST['mes'] )) ? intval($_REQUEST['mes'] ) : date("m");
       /*if ($this->mes<1) {   // así hago más sencilla la llamada, mes=0 => año anterior, mes=13 => año siguiente
           $this->mes = 12;
           $this->anyo--;
       }
       if ($this->mes>12) {
           $this->mes = 1;
           $this->anyo++;
       }*/
       $this->dia  = (isset($_REQUEST["dia"])  && intval($_REQUEST['dia'])) ? intval($_REQUEST["dia"])  :  date("d");
   }

   public function primerDia(){
       $dias = array(7,1,2,3,4,5,6);  // orden de los días (1º Domingo)
       return $dias[date("w", mktime(0, 0, 0, $this->mes, 1, $this->anyo)) ];
   }

   public function ultimoDia(){
       return intval( date("t",mktime(0, 0, 0, $this->mes, 1, $this->anyo)) );
   }
   
   // devuelve el número de día del lunes de esa semana
   public function dia_lunes_semana () {      
    // ojo, primer día 0=domingo
    $diasemana = date("w", mktime(0, 0, 0, $this->mes, $this->dia, $this->anyo));
    return $dia - $diasemana + 1;       
   }
   
   public static function fromDateSpanish($str,$date=false){
       if (!$str) return '';
       // dd/mm/YYYY HH:MM
       $dia = intval(substr($str,0,2));
       $mes = intval(substr($str,3,2)); 
       $anyo= intval(substr($str,6,4)); 
       $hora= intval(substr($str,11,2)); 
       $min = intval(substr($str,14,2)); 
       $ret = mktime($hora, $min, 0, $mes, $dia, $anyo);
       if ($date) return $ret;
             else return date("Y-m-d H:i", $ret);
   }
   
   public static function fromDateEnglish($str,$date=false){
       $fecha = DateTime::createFromFormat("Y-m-d H:i:s", $str);
       if (!$fecha) return null;
       try {
           $ret = $fecha->format("d/m/Y H:i");
       }catch(Exception $e){
           print "Error: ".$e->getMessage();
           $ret = null;
       }
       return $ret;
   }
}

class strTools {
    
    public static function gen($car,$long){
        $ret = "";
        for ($i=0;$i<$long;$i++) $ret.=$car;
        return $ret;
    }
    // mancha una cadena desde $start hasta $long con el caracter $car
    public static function mancha($str,$car,$start,$long){
        $tot = strlen($str);
        $s = strTools::gen($car,$long);
        $a = substr($str, 0, $start-1);
        $b = substr($str, start+$long, $tot);
        return $a.$s.$b;
    }
}

?>
