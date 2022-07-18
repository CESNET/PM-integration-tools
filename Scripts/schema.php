<?php 

if($argc < 2) {
    print "Usage: schema [-f|-h] source
 
";
    exit;
}

$rest = $argc - 1;
$options = getopt("fh", array(), $rest);
$source = $argv[$rest];

$json = "";
if(empty($options)) {
    $json = file_get_contents($source);
} else {
    foreach(array_keys($options) as $opt) {
        switch($opt) {
            case 'h':
                $http_opts = ['http' => [
                    'method' => 'GET',
                    'header' => "Content-Type: application/json\r\n".
                        "Authorization: Basic ".base64_encode("$user:$pass")."\r\n"
                ]];
                $context = stream_context_create($http_opts);
                $json = file_get_contents($source, false, $context);
                break;
            
            case 'f':
            default:
                $json = file_get_contents($source);
                break;
        }
    }
}

$data = json_decode($json);

$definitions = [];
foreach($data as $attrdef) {
    $definitions[$attrdef->entity][] = $attrdef;
}

foreach($definitions as $entity => $defs) {
    $attrNames = array();
    print "<xsd:ComplexType name='".mapEntityName($entity)."Type' />\n";
    print "    <xsd:sequence>\n";
    foreach($defs as $def) {
	   $attrNamespace = $def->namespace;
       $attrName = mapAttributeName($def->friendlyName, $attrNamespace);
       $attrType = mapAttributeType($def->type);
	   $attrTypeOri = $def->type;
       $attrIsMultiValued = isMultiValued($def->type);
       if(array_key_exists($attrName, $attrNames)) {
           print "        <xsd:element name='virt_$attrName' type='$attrType' minOccurs='0' ". ($attrIsMultiValued ? "maxOccurs='unbounded'" : "maxOccurs='1'") ." /> <!-- $attrNamespace:$attrName, $attrTypeOri -->\n";
       } else {
           print "        <xsd:element name='$attrName' type='$attrType' minOccurs='0' ". ($attrIsMultiValued ? "maxOccurs='unbounded'" : "maxOccurs='1'") ." /> <!-- $attrNamespace:$attrName, $attrTypeOri -->\n";
           $attrNames[$attrName] = $def;
       }
    }
    print "    </xsd:sequence>\n";
    print "</xsd:ComplexType>\n\n";
}

exit;

function mapEntityName($name) {
    static $nameMap = [
        'entityless' => 'Global',
        'facility' => 'Facility',
        'user' => 'User',
        'group' => 'Group',
        'group_resource' => 'GroupResource',
        'host' => 'Host',
        'member' => 'VoMember',
        'ues' => 'UserExtSource',
        'vo' => 'VirtualOrganization',
        'member_resource' => 'MemberResource',
        'resource' => 'Resource',
        'user_facility' => 'UserFacility',
        'member_group' => 'MemberGroup'
    ];
    
    $result = array_key_exists($name, $nameMap) ? $nameMap[$name] : $name;   
    return $result;
}


function mapAttributeName($name, $namespace) {
    $name = str_replace(":", "_", $name);
    $parts = explode(':', $namespace);
    $kind = $parts[4];
    return $name;
}

function mapAttributeType($type) {
    static $typeMap = [
	'java.lang.String' => 'xsd:string',
	'java.lang.Integer' => 'xsd:int',
	'java.lang.BigInteger' => 'xsd:integer',
	'java.lang.Long' => 'xsd:long',
	'java.lang.Boolean' => 'xsd:boolean',
        'java.util.ArrayList' => 'xsd:string',
	'java.util.LinkedHashMap' => 'xsd:string'
    ];
    
    $result = array_key_exists($type, $typeMap) ? $typeMap[$type] : $type;
    return $result;
}

function isMultiValued($type) {
    switch($type) {
	case 'java.util.ArrayList':
	case 'java.util.LinkedHashMap':
		return true;

	default:
		return false;
    }
}

