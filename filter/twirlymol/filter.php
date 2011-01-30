<?php // $id$
////////////////////////////////////////////////////////////////////////
// TwirlyMol/Chemistry 3D plugin filter for embedding 3D chemical
// structures in Moodle
//
// Use the Moodle text editor to add terms similar to the following
//
// chem3d::aspirin::250
// generates a 250px x 250px structure of aspirin
//
// chem3d::P4O10::400
// generates a 400px x 400px structure of phosphorus (V) oxide
//
// twirlymol::Cl/C=C\Cl::300
// generates a 300px x 300px structure of cis-1,2-dichloroethene
//
//
// chem3d:: and twirlymol:: are synonyms that can be used interchangeably
//
//
// Uses the URL API of the NCI/CADD Chemical Identifier Resolver service
//
// http://cactus.nci.nih.gov/chemical/structure
//
// to generate an .sdf structure data file which is then rendered by
// TwirlyMol, a JavaScript (Dojo) based 3D chemical structure renderer
// written by Noel O'Boyle
//
// http://www.redbrick.dcu.ie/~noel/blog/molecproc/twirlymol.html
// http://github.com/baoilleach/twirlymol
//
// TwirlyMol uses the Dojo JavaScript Toolkit (bundled with this filter)
// http://www.dojotoolkit.org/
// 
// Can use a variety of search terms including
// * Chemical name (may not be specific)
// * SMILES 
// * InChI
// * InChIKeys
// 
// Details of the Chemical Structure Resolver service at: 
// http://cactus.nci.nih.gov/chemical/structure/documentation
// Markus Sitzmann 2009-2010
//
// Moodle filter written by Geoffrey Rowland, August 2010
// geoff dot rowland at yeovil dot ac dot uk
//
////////////////////////////////////////////////////////////////////////
  
class filter_twirlymol extends moodle_text_filter {
//  public function filter($text) {
    function filter($text, array $options = array()){
        global $CFG;
     
        // Just return if text does not contain any twirlymol:: tags

        if(strpos($text, "twirlymol::") === FALSE) {
            if(strpos($text, "chem3d::") === FALSE) {
            return $text;
            }
        }
        static $count = 0;
        if (!function_exists('twirlymol_replace')){
        function twirlymol_replace($matches){
        global $CFG;
        global $count;
        
            // Replace #, SMILES symbol for triple bond, with URL-safe code
            $matches[2]= str_replace('#','%23',$matches[2]);
 
            // Each twirlymol on a page is inserted in a div with a unique id
            $count++;
            $twirlyid = 'mol'.time().$count;
            //JavaScript could be streamlined but works!
            $load = '
<script type="text/javascript" src="'.$CFG->wwwroot.'/filter/twirlymol/js/dojo-release-1.5.0/dojo/dojo.js"></script>
<script type="text/javascript">
(function() {
  	if (document.getElementById("twirlydojo") == null) {
  		var e = document.createElement("script");
  		e.type = "text/javascript";
   	e.src= "'.$CFG->wwwroot.'filter/twirlymol/js/dojo-release-1.5.0/dojo/dojo.js";
  		e.id = "twirlydojo";
  		document.getElementsByTagName("head")[0].appendChild(e);	
  	};
   var _interval = setInterval(function() {
   	if (embed_'.$twirlyid.'()) {
   	   clearInterval(_interval);
 	   }
 	}, 50);
})()
            </script>';           
            //echo $count.'<br />';
            // Including time-stamp ensures twirlyid is unique and avoids caching issues?
            //
            // $matches[0] is the complete match
            // $matches[1] is the match for the first subpattern enclosed in '(...)'
            // and so on.
            //
            // Nested divs allow border to provide empty placeholder if client JavaScript or TwirlyMol is not available
            // Also, makes layout consistent with the chem2d filter
            //
            // Build filter replace string

            $cactus =          'http://cactus.nci.nih.gov/chemical/structure/';
            // retrieves 3D chemical structure file from Chemical Identifier Resolver service. Makes newlines explicit
            // Suppress errors from file_get_contents() with @. Perhaps need better error handling and feedback to end-user?
            // HTML 404 (Not found) status message ?
            // HTML 500 (Server error) status message?.
            $sdf =             '';
            $sdf =             @file_get_contents($cactus.$matches[2].'/sdf?get3d=true');
            // if (strpos($http_response_header[0], "404")) {};
            // if (strpos($http_response_header[0], "500")) {};
            $sdf =             str_replace("\n", "\\n", $sdf);
            $divwidth =        '<div style="width:'.$matches[3].'px">';
            $divborder =       '<div style="width:'.$matches[3].'px; height: '.$matches[3].'px; border: solid 1px lightgray; background-color:white">';
            $noscriptmessage = get_string('noscriptmessage', 'filter_twirlymol');
            $noscript =        '<noscript><div style="margin: 1px; background-color: lightyellow">'.$noscriptmessage.'</div></noscript>';
            $divtwirl =        '<div id="'.$twirlyid.'" style="width:'.$matches[3].'px; height:'.$matches[3].'px">';
            $divend =          '</div>';
            // $divright =     '<div style="text-align: right">';
            $divleft =         '<div style="text-align: left">';
            $structure =       get_string('structure', 'filter_twirlymol');
            $viewstructure =   get_string('viewstructure', 'filter_twirlymol');
            $control =         '<a title= "'.$viewstructure.'" target="twirlymol" href="'.$cactus.$matches[2].'/sdf?get3d=true">'.$structure.'</a>';
            //
            // This should probably be ported to .js script file(s), but it works as is. Seems to manage potential multiple calls to Dojo
            $javascript =      '
<script type="text/javascript">
//<![CDATA[
function embed_'.$twirlyid.'() {
try {
var sdf = "'.$sdf.'";
//alert(sdf);
var mol = parseSD(sdf);
twirlyMol("'.$twirlyid.'", mol.atoms, mol.bonds, mol.elements);
return true
}
catch(error) {
return false
}
return false
}
//]]>
</script>
<script type="text/javascript" src="'.$CFG->wwwroot.'/filter/twirlymol/js/twirlymol.js"></script>
';      
            $replace = $load.$divwidth.$divborder.$noscript.$divtwirl.$divend.$divend.$divleft.$control.$divend.$divend.$javascript;
            return $replace;
        }
        }
        $pattern = '/(twirlymol::|chem3d::)(.+)::(\d{1,3})/';
        $text = preg_replace_callback($pattern, 'twirlymol_replace', $text);
        return $text;
    }
}
?>