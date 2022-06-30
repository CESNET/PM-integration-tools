<?php 

if($argc < 2) {
    print "Usage: mappings [-f|-h] source [object]
 
";
    exit;
}

$rest = $argc - 1;
$options = getopt("fh", array(), $rest);
$nopts = $argc - $rest;
$source = $argv[$rest];
if($nopts > 1) {
    $objname = $argv[$rest+1];
}

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
    if(!empty($objname) && $objname != $entity) {
	continue;
    }
    switch($entity) {

	case "group":
		$discriminator = "Group/";
		break;

	case "vo":
		$discriminator = "VirtualOrganization/";
		break;

	case "member":
		$discriminator = "voMember/";
		break;

	case "member_group":
		$discriminator = "groupMember/";
		break;

	default:
		$discriminator = "";
		break;
    }
    print "<!-- $entity -->\n ";
    print "<objectType>\n";
    foreach($defs as $def) {
	$attrNamespace = $def->namespace;
	$attrPrefix = mapPrefix($def->namespace);
        $attrName = mapAttributeName($def->friendlyName);
        $attrType = mapAttributeType($def->type);
        $attrIsMultiValued = isMultiValued($def->type);
	print "    <attribute>\n";
	print "        <ref>ri:$attrPrefix$attrName</ref>\n";
	print "        <displayName>$def->friendlyName</displayName>\n";
	print "        <description>$def->description</description>\n";
	print "        <tolerant>true</tolerant>\n";
	print "        <exclusiveStrong>false</exclusiveStrong>\n";
	print "        <inbound>\n";
	print "            <name>$attrName mapping</name>\n";
	print "            <authoritative>true</authoritative>\n";
	print "            <exclusive>false</exclusive>\n";
	print "            <strength>strong</strength>\n";
	if(isMap($def->type)) {
	    print "            <expression>\n";
	    print "                <script>\n";
	    print "                   <code>\n";
	    print "                       input.getLang().toString()\n";
	    print "                   </code>\n";
	    print "                </script>\n";
            print "            </expression>\n";
            print "            <condition>\n";
	    print "                <script>\n";
	    print "                   <code>\n";
	    print "                       input != null\n";
	    print "                   </code>\n";
	    print "                </script>\n";
            print "            </condition>\n";
	}
	print "            <target>\n";
	print "               <path>\$focus/extension/$discriminator$attrName</path>\n";
	print "            </target>\n";
	print "        </inbound>\n";
	print "    </attribute>\n";
    }
    print "</objectType>\n\n";
}

exit;


function mapAttributeName($name) {
    $name = str_replace(":", "_", $name);
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

function isMap($type) {
    return $type == 'java.util.LinkedHashMap';
}

function mapPrefix($name) {
	$parts = explode(':', $name);
	return $parts[2] . "_" . $parts[4] . "_";
}

