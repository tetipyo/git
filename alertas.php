<?php
header('content-type: application/json; charset=utf-8'); //en caso de json en vez de jsonp habría que habilitar CORS:
header("access-control-allow-origin: *");

require_once("Rest.inc.php");
class API extends REST {

	
	// const DB_SERVER = "5.9.124.212.";
	// const DB_USER = "criterion";
	// const DB_PASSWORD = "CriterioN.2k13JAM";
	// const DB = "criterion";
	
	const DB_SERVER = "localhost";
	const DB_USER = "luisv";
	const DB_PASSWORD = ".luis.1234";
	const DB = "criterion";
	
	private $lista_personas				= array();
	private $per_antecedentes 	 	= 0;
	private $lista_config					=array();
	private $persona						= array();
	private $antecedentes 				=array();
	private $llave								= array();
	private $fecha_alta 					= "";
	private $consultar	 				= "";
	private $array_alertas				= array();
	private $idUser_afiliado				= 0;
	private $tipo_Usuario					= 0;
	private $login							= "";
	private $afiliado						= "";
	private $idPersona						= 0;
	private $errorSolicitud				="Empresa no cuenta con permisos para obtener este reporte.";
	private $errorUsuarioContrasena			= "Usuario o contrasena invalido.";
	private $errorPostCi					= "Persona no encontrada.";
	private $errorInsert					= "Error interno, intenta mas tarde.";
	
	public function __construct(){
		parent::__construct();	
		$this->fecha_alta=date("Y-m-d");
		$this->dbConnect();		
		$this->Alertas();				
	}
	
	private function dbConnect(){
		$this->db = new mysqli(self::DB_SERVER,self::DB_USER,self::DB_PASSWORD,self::DB);
		if ($this->db->connect_errno) {
			$error = array('status' => "Problema interno, intenta mas tarde");
			$this->response($this->json($error), 400);
		}
	}
	
	private function Alertas(){
		
		if($this->get_request_method() != "POST"){
			$this->response('',406);
		}
		
		$login 				= 	urldecode($this->_request['login']);		
		$password 			=  	urldecode($this->_request['password']);
		$llave			= urldecode($this->_request['consulta']);
		$ci						=  	urldecode($this->_request['ci']);
		$tipo					=	urldecode($this->_request['tipo']);
		$fdesde 		= urldecode($this->_request['desde']);
		$fhasta 		= urldecode($this->_request['hasta']);

		if(!$this->ValidUser($login,$password)){
			$this->error($this->errorUsuarioContrasena);
		}
		
		if(!$this->conf_creditos($this->afiliado, $tipo)){//ver si el tipo tiene habilitado alertas en ese tipo
			$this->error($this->errorSolicitud);
		}
		
		if($fdesde !="" && $fhasta != ""){//ajustar fechas
		
			$fdesde = $this->format_fecha($fdesde);
			$fhasta =  $this->format_fecha($fhasta);
		
		}else{//sino hay fechas buscar antecedentes desde ayer....
			
			$fhasta = date('Y-m-d');
			$fdesde = strtotime ( '-1 day' , strtotime($fhasta));
			$fdesde = date ( 'Y-m-d' , $fdesde );
	
		}
		
		
		
		$personas = array();		
		$result = array();
		
		if(strtoupper($tipo) == "O"){
			$this->conf_persona_credito($llave, $ci, $this->afiliado);
		}elseif(strtoupper($tipo) =="L"){
			$this->config_persona_laboral($llave, $ci, $this->afiliado);
		}else{
			$this->error("Error en la peticion: Tipo de consulta desconocida.");
		}
		
		
		$result['tipo']=$tipo;
		$i =0;
		
		foreach($this->lista_personas as $per){//BUCLE BUSCAR LOS ANT. CORRESPONDIENTES A LA PERSONA.
					
			if($this->lista_config["alerta_demanda"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Demandas";	
				$this->check_demandas($per["id_persona"],$per["fecha_alerta_d"],"D",  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_morosidad"] == "1"){
					$this->array_alertas[sizeof($this->array_alertas)] = "Morosidad";	
					$this->check_morosidades($per["id_persona"],$per["fecha_alerta_m"],"",  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_morosidad_p"] == "1"){
					$this->array_alertas[sizeof($this->array_alertas)] = "Morosidad Propia";
					$this->check_morosidades($this->afiliado,$per["alerta_morosidades_p"],"P",  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_penal"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Penal";	
				$this->check_penales($per["id_persona"],$per["fecha_alerta_p"],  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_quiebra"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Quiebra";	
				$this->check_convoquiebras($per["id_persona"],$per["fecha_alerta_q"],"Q",  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_convocatoria"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Convocatorias";	
				$this->check_convoquiebras($per["id_persona"],$per["fecha_alerta_c"],"C",  $fdesde, $fhasta);
			}
			if($this->lista_config["alerta_inhabilitacion"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Inhabilitacion Bancaria";
				$this->check_inhabilitacion_cuentas($per["id_persona"],$per["fecha_alerta_in"],  $fdesde, $fhasta);				
			}
			if($this->lista_config["alerta_inhibicion"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Inhibicion";	
				$this->check_demandas($per["id_persona"],$per["fecha_alerta_i"],"I",  $fdesde, $fhasta);
			}
			
			if($this->lista_config["alerta_remates"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Remates";	
				$this->check_remates($per["id_persona"],$per["fecha_alerta_r"],  $fdesde, $fhasta);
			}
			
			if($this->lista_config["alerta_fallecidos"] == "1"){
				$this->array_alertas[sizeof($this->array_alertas)] = "Fallecidos";
				$this->check_fallecidos($per["id_persona"]);	
			}
			
			$cantidad = sizeof($this->antecedentes);
			
			if($cantidad > 0){
				//BUSCAR DATOS DE LA Persona
				$this->GetPersona($per["id_persona"]);
				
				//$result["personas"][$i]["nombres"] = $per["nombres"]." ".$per["apellidos"].";".$per["id_persona"];
				$result["personas"][$i]["nombres"] = $this->persona["nombres"]." ".$this->persona["apellidos"];
				$result["personas"][$i]["documento"] = $this->persona["CI"];
				$result["personas"][$i]["procesos"] = $this->antecedentes;
				$result["personas"][$i]["cant_procesos"] = $cantidad;
		
			}
			
			$i = $i + 1;
			//vaciar arrays
			unset($this->antecedentes);
			$this->antecedentes = array();
			
			unset($this->persona);
			$this->persona = array();
		}
		
		if($i == 0){
			$result['ret_code'] = "No existe niguna persona con nuevos antecedentes, en este rango de fecha.";
		}else{
			$result['ret_code']="0";
		}
		
		$result["monitoreados"] = $i;
		$result["rango_fecha"] = $fdesde." - ".$fhasta;				
		$result["total_procesos"] = $this->per_antecedentes + $cant;
		$this->response($this->json($result), 200);

	}
	
	private function ValidUser($login,$password){
		$sql="SELECT id_usuario,tipo_usuario,afiliado,id_persona FROM usuarios_afiliados WHERE usuario='$login' AND password='".md5($password)."' AND habilitado = 'SI' LIMIT 1";
		$result = $this->db->query($sql);
		if ($result!== FALSE && $result->num_rows > 0) {
			$row = $result->fetch_array(MYSQLI_NUM);
			$this->idUser_afiliado=$row[0];
			$this->tipo_Usuario=$row[1];
			$this->afiliado=$row[2];
			$this->idPersona=$row[3];
			$this->login=$login;
		}else{
			return false;
		}
		return true;
	}
	
	private function conf_creditos($afiliado, $tipo){
		
		
		$sql = "SELECT * FROM `alertas`	WHERE `tipo_alerta` = '$tipo'  and informante = '$afiliado'";  // Seleccionar todos los registros de alertas laborales.
		$result = $this->db->query($sql);
		$cant = $result->num_rows;
		
		if ($result!== FALSE && $result->num_rows > 0) {
			while($row = $result->fetch_array(MYSQLI_ASSOC)){
				$this->lista_config = $row;
			}
			return true;
		}else{
			return false;
		}	
		
	}
	
	private function conf_persona_credito($llave, $ci, $informante){
		
		if($llave =="todos"){
			$add="";
		}elseif($llave== "persona"){
			$add =" and o.id_persona ='$ci'";
		}
		if($llave !=""){
			$sql = "SELECT DISTINCT o.id_persona,  o.informante, o.eliminado, o.fecha_alerta_d, 
					 o.fecha_alerta_p, o.fecha_alerta_m, o.fecha_alerta_q, o.fecha_alerta_in, 
					 o.fecha_alerta_i, o.fecha_alerta_c, o.fecha_alerta_r, o.alerta_morosidades_p,
					 o.alerta_fallecido
					 FROM op_credito o JOIN personas p
					 ON o.id_persona = p.ruc WHERE o.informante = '".$informante."' AND o.eliminado != 'SI'".$add;
			
			$result = $this->db->query($sql);
			$cant = $result->num_rows;
			
			if ($result!== FALSE && $result->num_rows > 0) {		 
				while($row = $result->fetch_array(MYSQLI_ASSOC)){
					$this->lista_personas[sizeof($this->lista_personas)] = $row;
				}
			}else{
				$this->error("Esta persona no se encuentra en el listado de OP CREDITOS");
			}
		}else{
			$this->error("Falta un parametro en la peticion: CONSULTA");
		}
	}
	
	private function config_persona_laboral($llave, $ci, $empresa){
		if($llave !=""){
		
			if($llave =="todos"){
				$add="";
			}elseif($llave== "persona"){
				$add =" AND l.id_persona ='$ci' ";
			}

			$sql = "SELECT DISTINCT l.id_persona,  l.ruc_empleador, l.eliminado, l.fecha_alerta_d, 
					l.fecha_alerta_p, l.fecha_alerta_m, l.fecha_alerta_q, l.fecha_alerta_in, 
					l.fecha_alerta_i, l.fecha_alerta_c, l.fecha_alerta_r
					FROM laborales l join personas p
					on l.id_persona = p.ruc
					WHERE l.eliminado != 'SI'
					AND l.ruc_empleador = '".$empresa."'
					$add
					AND fecha_salida ='0000-00-00';";
					
			//echo $sql;
			$result = $this->db->query($sql);
			$cant = $result->num_rows;
			
			if ($result!== FALSE && $result->num_rows > 0) {		 
				while($row = $result->fetch_array(MYSQLI_ASSOC)){
					$this->lista_personas[sizeof($this->lista_personas)] = $row;
				}
			}else{
				$this->error("Esta persona no se encuentra en la lista de EMPLEADOS");
			}
		}else{
			$this->error("Falta un parametro en la peticion: CONSULTA");
		}		
	}
	
	
	//EMPIEZA VERIFICAR ANTECEDENTES
	
	private function check_demandas($var,$ultima_alerta,$tipo_dato, $fec_d, $fec_h) {  // verifica la existencia o no de agluna demanda nueva para la persona.
		//echo "buscando demandas para $var mas nuevas que $ultima_alerta<br />\n";  // Si existe, retorna la fecha de la mas nueva
		 $sql1 = "SELECT demandante, fecha_oficio, id_persona, tipo_dato
				  FROM judiciales 
				  WHERE id_persona = '$var' 
				  AND fecha_alta between '$fec_d' and '$fec_h'
				  AND tipo_dato = '$tipo_dato'
				  AND eliminado != 'SI' 
				  ORDER BY fecha_oficio DESC";
		
		 $result = $this->db->query($sql1);
		 $cant = $result->num_rows;
		 $texto ="";
		 if ($result!== FALSE && $result->num_rows > 0) {
			 
			 if($tipo_dato =="D"){
				if($cant == 1){
					$texto ="Demanda";
				}elseif($cant == 0 || $cant > 1 ){
					$texto ="Demandas";
				} 
			 }elseif($tipo_dato =="I" ){
				if($cant == 1){
					$texto ="Inhibicion";
				}elseif($cant != 1){
					$texto ="Inhibiciones";
				} 
			 }	
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		 }
	}
	
	function check_convoquiebras($ci,$ultima_alerta,$tipo_d, $fec_d, $fec_h) {  // verifica la existencia o no de algún remate judicial nuevo para la persona.
    $sql1 = "SELECT id_persona, tipo_dato,IF(juez REGEXP '^[0-9]+$', (SELECT juzgado FROM juzgados WHERE id_juzgado = juez), juez) AS nom_juzgado,
                        sindico, DATE_FORMAT(fecha_oficio,'%d/%m/%Y') AS fecha_oficio, medio
                        FROM quiebra_convoc
                        WHERE id_persona = '$ci'
						AND fecha_alta between '$fec_d' and '$fec_h'
                        AND `tipo_dato` = '$tipo_d' 
                        AND eliminado = 'NO' OR eliminado =''
                        ORDER BY quiebra_convoc.fecha_oficio DESC";
		
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		$texto ="";
		 
		 if ($result!== FALSE && $result->num_rows > 0) {
			 if($tipo_d=="C"){
				if($cant == 1){
					$texto ="Convocatoria";
				}elseif($cant == 0 || $cant > 1 ){
					$texto ="Convocatorias";
				} 
			 }elseif($tipo_d =="Q"){
				if($cant == 1){
					$texto ="Quiebra";
				}elseif($cant == 0 || $cant > 1 ){
					$texto ="Quiebras";
				} 
			 }
			 $this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			 $this->per_antecedentes = $this->per_antecedentes + $cant;
			 return true;
		 }			 
	}
	
	
	function check_penales($var,$ultima_alerta, $fec_d, $fec_h) {  // verifica la existencia o no de alguna demanda nueva para la persona.
     // Si existe, retorna la fecha de la mas nueva
     $sql1 = "SELECT ci_nro, fecha_alta, caratula, fecha_hecho
              FROM penales 
              WHERE ci_nro = '$var' 
			 AND fecha_alta between '$fec_d' and '$fec_h'
              and eliminado !='SI' 
              ORDER BY fecha_hecho DESC";
			  
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		$texto ="";
		 
		 if ($result!== FALSE && $result->num_rows > 0) {
			if($cant == 1){
				$texto ="Ant. Penal";
			}elseif($cant == 0 || $cant > 1 ){
				$texto ="Ant. Penales";
			} 
			
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		}	  
	}
	
	function check_morosidades($var,$ultima_alerta, $tipo_m, $fec_d, $fec_h) {  // verifica la existencia o no de alguna morosidad nueva para la persona.
     // echo "buscando MOROSIDADES para $var mas nuevas que $ultima_alerta ------------ <br />\n";  // Si existe, retorna la fecha de la mas nueva
     $sql1 = "SELECT id_persona, fecha_alta, monto 
              FROM `morosidades` 
              WHERE `id_persona` = '$var' 
              AND fecha_alta between '$fec_d' and '$fec_h'
              and eliminado !='SI'
              ORDER BY fecha_alta DESC";
		
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		$texto ="";
		
		if ($result!== FALSE && $result->num_rows > 0) {
			if($tipo_m == ""){
				if($cant == 1){
					$texto ="Morosidad";
				}elseif($cant == 0 || $cant > 1 ){
					$texto ="Morosidades";
				} 
			}else{
				if($cant == 1){
					$texto ="Morosidad Propia";
				}elseif($cant == 0 || $cant > 1 ){
					$texto ="Morosidades Propias";
				} 
			}
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		}	  	  
	}		  
	
	function check_remates($var,$ultima_alerta, $fec_d, $fec_h) {  // verifica la existencia o no de algún remate judicial nuevo para la persona.
     $sql1 = "SELECT id_persona, fecha_alta, fecha_remate, objeto_remate, fecha_remate
              FROM `remates_judiciales` 
              WHERE `id_persona` = '$var' 
              AND fecha_alta between '$fec_d' and '$fec_h'
              ORDER BY fecha_remate DESC";
		
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		$texto ="Remates";

		if ($result!== FALSE && $result->num_rows > 0) {
			if($cant == 1){
				$texto ="Remate";
			} 
			
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		}
	}			  
	
	function check_inhabilitacion_cuentas($var,$ultima_alerta, $fec_d, $fec_h) {  // verifica la existencia o no de alguna inhabilitación de cuenta
	
     $sql1 = "SELECT id_persona, fecha_alta, fecha_publicacion, banco
              FROM `inhabilitacion_cuentas` 
              WHERE `id_persona` = '$var' 
              AND fecha_alta between '$fec_d' and '$fec_h'
			  and eliminado !='SI'
              ORDER BY fecha_publicacion DESC";
		
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		$texto ="Inhabilitaciones Bancarias";
		
		if ($result!== FALSE && $result->num_rows > 0) {
			if($cant == 1){
				$texto ="Inhabilitacion Bancaria";
			} 
			
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		}	  
	}		  
	function check_fallecidos($id_persona){
    
    $query_fallecido = "SELECT ruc FROM personas
			WHERE ruc = '$id_persona'
			AND fallecido = 'S'";
		$result = $this->db->query($sql1);
		$cant = $result->num_rows;
		
		if ($result!== FALSE && $result->num_rows > 0) {
			$texto ="Persona Fallecida.";
	
			$this->antecedentes[sizeof($this->antecedentes)]=$cant." ".$texto;
			$this->per_antecedentes = $this->per_antecedentes + $cant;
			return true;
		}	  				
	}
	
	private function format_fecha($fecha){
	
		if(strpos($fecha,"/") !== false){
			$fecha = str_replace("/", "-", $fecha);
		}
		
		$fec = new DateTime($fecha);
		$fec = date_format($fec, 'Y-m-d');
				
		return $fec;
	}
	
	private function GetPersona($ci){
		
	$sql= "SELECT nombres, apellidos, ruc
                        FROM personas
                        WHERE ruc = '$ci'
                        AND (eliminado = 'N' OR eliminado = '' OR eliminado IS NULL)";

		$result = $this->db->query($sql);

		if ($result!== FALSE && $result->num_rows > 0) {
			$row = $result->fetch_array(MYSQLI_NUM);
			$this->persona['nombres']=utf8_encode($row[0]);
			$this->persona['apellidos']=utf8_encode($row[1]);
			$this->persona['CI']=$row[2];
			//print_r($row);
		}else{
			return false;
		}
		return true;
	}
	
	//LOS DE ABAJO NO TOCAR
	private function error($msg){
		$error = array('ret_code' => $msg);
		$this->response($this->json($error), 400);
	}
	
	private function json($data){
		if(is_array($data)){
			return json_encode($data);
		}
	}
}
$api = new API;
?>
