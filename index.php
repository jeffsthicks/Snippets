<?php
    // Retrieve the URL variables (using PHP).
    $tagName = $_GET['tag'];    
    if (isset($tagName)==False)
    {$tagName="main";}
    $tagName = str_replace(":","_",$tagName);

    $tagLocation="./tags/".$tagName.".tex";
    $f = fopen($tagLocation, "r");
    while (($line = fgets($f))[0] == "%"){
        if (preg_match('/parent:"(.*)"/',$line,$matches)==1){
            $parent=$matches[1];
            $uparent=str_replace(":","_",$parent);
            $ctagName = str_replace("_",":",$tagName);
            header("Location: $uparent#$ctagName");
            die();
        }
    }
?>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="code/styles.css">

<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3.0.1/es5/tex-mml-chtml.js"></script>
  </head>
<body>
<?php

    include("code/preambles/mathpreamble.php");
    include("code/preambles/referenceArray.php");
    include("code/preambles/tagArray.php");
    $tagName=strval($tagName);


    $sectionCounter=Array(0,0,0);
    $footnoteCounter=0;
    $theoremCounter=0;
    $labelArray = array();
    $sectionDepth=-1;
    $referenceKeys= array(); 
    $tableOfContents=array();
    $pageTitle="";



function texToHtml($line) {
    global $referenceKeys, $bibArray, $footnoteCounter;
    if (preg_match("/\\\\input{(.*)\}/",$line,$matches)==1){
        $tagName=str_replace(".tex","",$matches[1]);
        if (file_exists("./tags/".$tagName.".tex")){
        return texReader($tagName);}
        else{
            return "<p><b> could not find file $tagName </b></p>";
        }
    }
    
    if ($line == "/n"){return "</p><p>";}
    $line = preg_replace("/\\$([^\\$]*)\\$/","\($1\)",$line);
    $line = preg_replace("/\%.*/","",$line);
    $line = preg_replace("/\\\\begin\{itemize\}/","<ul>",$line);
    $line = preg_replace("/\\\\end\{itemize\}/","</ul>",$line);
    $line = preg_replace("/\\\\begin\{figure\}/","",$line);
    $line = preg_replace("/\\\\end\{figure\}/","",$line);
    $line = preg_replace("/\\\\begin\{subfigure\}\{.*\}/","",$line);
    $line = preg_replace("/\\\\end\{subfigure\}/","",$line);
    $line = preg_replace("/\\\\begin\{definition\}/","",$line);
    $line = preg_replace("/\\\\end\{definition\}/","",$line);
    $line = preg_replace("/\\\\begin\{exposition\}/","",$line);
    $line = preg_replace("/\\\\end\{exposition\}/","",$line);
    $line = preg_replace("/\\\\begin\{proposition\}/","",$line);
    $line = preg_replace("/\\\\end\{proposition\}/","",$line);
    $line = preg_replace("/\\\\begin\{conjecture\}/","",$line);
    $line = preg_replace("/\\\\end\{conjecture\}/","",$line);
    $line = preg_replace("/\\\\begin\{proof\}/","",$line);
    $line = preg_replace("/\\\\end\{proof\}/","",$line);
    $line = preg_replace("/\\\\begin\{lemma\}/","",$line);
    $line = preg_replace("/\\\\end\{lemma\}/","",$line);
    $line = preg_replace("/\\\\begin\{article\}/","",$line);
    $line = preg_replace("/\\\\end\{article\}/","",$line);
    $line = preg_replace("/\\\\begin\{remark\}/","",$line);
    $line = preg_replace("/\\\\end\{remark\}/","",$line);
    $line = preg_replace("/\\\\begin\{corollary\}/","",$line);
    $line = preg_replace("/\\\\end\{corollary\}/","",$line);
    $line = preg_replace("/\\\\begin\{exercise\}/","",$line);
    $line = preg_replace("/\\\\end\{exercise\}/","",$line);
    $line = preg_replace("/\\\\begin\{example\}/","",$line);
    $line = preg_replace("/\\\\end\{example\}/","",$line);
    $line = preg_replace("/\\\\begin\{theorem\}/","",$line);
    $line = preg_replace("/\\\\end\{theorem\}/","",$line);
    $line = preg_replace("/\\\\begin\{construction\}/","",$line);
    $line = preg_replace("/\\\\end\{construction\}/","",$line);
    $line = preg_replace("/\\\\begin\{enumerate\}/","<ol>",$line);
    $line = preg_replace("/\\\\end\{enumerate\}/","</ol>",$line);
    $line = preg_replace("/\\\\begin\{description\}/","<ul>",$line);
    $line = preg_replace("/\\\\end\{description\}/","</ul>",$line);
    $line = preg_replace("/\\\\begin\{application\}/","",$line);
    $line = preg_replace("/\\\\end\{application\}/","",$line);
    $line = preg_replace("/\\\\item/","<li>",$line);
    $line = preg_replace("/\\\\emph\{([^\}]*)\}/","<em>$1</em>",$line);
    $line = preg_replace("/\\\\footnote\{([^\}]*)\}/",'<span title = "$1"><sup>'.$footnoteCounter.'</sup>   </span>',$line);
    $line = preg_replace("/\\\\intertext\{(.*)..$/","\\\\end{align*}$1\\\\begin{align*}",$line);
    $line = preg_replace("/\\\\caption.*/","",$line);
    $line = preg_replace("/\\\\snip\{([^\}]*)\}\{(...).([^\}]*)\}/",'<a href="$2_$3">$1</a>',$line);
    $line = preg_replace("/\\\\href\{([^\}]*)\}\{([^\}]*)\}/",'<a href="$1">$2</a>',$line);
    
    if (preg_match_all("/.ite\{([^\}]*)\}/",$line,$matches)!=0){
        foreach ($matches[1] as $citationKey){
            if (in_array($citationKey,$referenceKeys)){
            }
            else{
           $referenceKeys[]=$citationKey;
            }
            $keyText=$bibArray['#'.$citationKey];
            $line = preg_replace("/\\\\.ite\{$citationKey\}/","[<a href=#$citationKey>$keyText</a>]",$line);
        }

    }
    
    $line = preg_replace("/\\\\label\{([^\}]*)\}/","",$line);
    $line = preg_replace("/\\\\subsection\*\{([^\}]*)\}/","<h3>$1</h3>",$line);
    $line = preg_replace("/\\\\section\{[^\}]*\}/","",$line);
    $line = preg_replace("/\\\\centering/","",$line);
    return $line;
}


function texReader($tagName) {
    global $sectionCounter,$referenceKeys, $theoremCounter, $labelArray,$sectionDepth,$tableOfContents,$pageTitle, $bibArray;
    $sectionDepth++;
    $tagLocation="./tags/".$tagName.".tex";
    $f = fopen($tagLocation, "r");


    {# Build up the metadata for this entry
        while (($line = fgets($f))[0] == "%"){
            if (preg_match('/name:"(.*)"/',$line,$matches)==1){
                $name=$matches[1];
                $name = preg_replace("/\\$([^\\$]*)\\$/","\($1\)",$name);}
            if (preg_match('/type:"(.*)"/',$line,$matches)==1){
                $type=$matches[1];
            }
            if (preg_match('/label:"(.*)"/',$line,$matches)==1){
                $label=$matches[1];
            }
            if (preg_match('/caption:"(.*)"/',$line,$matches)==1){
                $caption=$matches[1];
                $caption = preg_replace("/\\$([^\\$]*)\\$/","\($1\)",$caption);
            }
            if (preg_match('/source:"(.*)"/',$line,$matches)==1){
                $sourceTag=$matches[1];
                if (in_array($sourceTag,$referenceKeys)){
                    
                }
                else{
                $referenceKeys[]=$sourceTag;
                }
                $keyText=$bibArray['#'.$sourceTag];
            }
            
            if (preg_match('/sourceDetail:"(.*)"/',$line,$matches)==1){
                $sourceDetail=$matches[1];
            }
        }
        if (isset($name)==False)
            {$name="NONAME";}
        if (isset($type)==False)
            {$type="NOTYPE";}
        if (isset($label)==False)
            {$label="NOLABEL";}
        if (isset($caption)==False)
            {$caption="WARNING:NOCAPTION";}
        if (isset($sourceTag)==False)
            {$source="";}
        else{
            if (isset($sourceDetail)==False){$source="<a href=#$sourceTag>[$keyText]</a>";}
            else{$source="<a href=#$sourceTag>[$sourceDetail of $keyText</a>]";}
        }
        
    }

    $bodyText="";
    $thisIndex="";


    if (in_array($type,["article","exposition","construction"])){
        $sectionCounter[$sectionDepth]=$sectionCounter[$sectionDepth]+1;
        $sectionCounter[$sectionDepth+1]=0;
        $sectionCounter[$sectionDepth+2]=0;
        $theoremCounter=0;
        $thisIndex=$sectionCounter[1];
        for ($i=2; $i<$sectionDepth+1; $i++){
            $thisIndex="$thisIndex.$sectionCounter[$i]";}
        if($sectionDepth>0){
        $headerSize=$sectionDepth+2;
        $envOpen="<span class='anchor' id='$label'\></span>\n<h$headerSize ' index='$thisIndex'> $thisIndex: $name </h$headerSize>";
        $envClose="";
        $tableOfContents[]=array("depth"=>$sectionDepth, "item"=>"<li> <a  href=#$label> $thisIndex:$name </a> </li>");}
        else{$envOpen="";
            $pageTitle=$name;
            $envClose="";}
        
    }
    else if (in_array($type,["theorem","definition","proposition","lemma","example","exercise","application"])){
        $theoremCounter = $theoremCounter +1;
        $thisIndex="$type $sectionCounter[1].$sectionCounter[2].$theoremCounter";
        $envOpen="<span class='anchor' id='$label'\>\n</span><mathEnvironment class=$type index='$thisIndex'>\n<h2 class=$type>$thisIndex $source</h2>  ";
        $envClose="</mathEnvironment>";
        if( $type=="exercise" ){
            $solName = "sol".substr($tagName,3);
            $solFileName="./tags/$solName.tex";
            if(file_exists($solFileName)){
                $envClose="\n <p><a href='index.php?tag=$solName'>Click here</a> to view solution.\n</p> </mathEnvironnment>\n";
            }
        }
    }
    else if (in_array($type,["figure"])){
        $theoremCounter = $theoremCounter +1;
        $thisIndex="$type $sectionCounter[1].$sectionCounter[2].$theoremCounter";
        $envOpen="<span class='anchor' id='$label'\></span>\n<figure class=$type  index='$thisIndex'>\n  ";
        $envClose="<figcaption>$thisIndex:$caption</figcaption></figure>";
    }
    else{
        $envOpen="";
        $envClose="";
    }
    $labelArray[$label]=$thisIndex;
    while ((($line = fgets($f)) !== false) and (in_array($type,["figure","diagram"])== false))
    {
            $bodyText=$bodyText.texToHtml($line);
    }
    if (in_array($type,["figure","diagram"]))
    {
        $svgFilename=substr($tagName,4);
        $bodyText="<img src= 'tags/{$type}s/$svgFilename.svg'/>";
    }
    fclose($f);

    
    $bodyText= $envOpen.$bodyText.$envClose;
    $sectionDepth--;
    return $bodyText;}
    $bodyText= texReader($tagName);
        foreach($labelArray as $label=>$index){
        $regExp="/\\\\cref\{$label\}/";
        $bodyText=preg_replace($regExp,"<a href='#$label'>$index</a>",$bodyText);
        $ulabel=str_replace(":","_",$label);
        $bodyText = str_replace($ulabel,"#$label",$bodyText);
        }
        
        foreach($tagArray as $label=>$name){
            $regExp="/\\\\cref\{$label\}/";
            $ulabel=str_replace(":","_",$label);
            $bodyText=preg_replace($regExp,"<a href='$ulabel'>($name)</a>",$bodyText);
            }
        $regExp="/\\\\cref\{(.*)\}/";
        $bodyText=preg_replace($regExp,"<b>Missing Label ($1)!</b>",$bodyText);


?>

<?php
    echo("<title>SympSnip: $pageTitle</title>\n<header>\n<h1> $pageTitle </h1>\n</header>\n");

?>


    <div class="menu">
    <div class="title">SECTIONS</div>
<?php
    $currentDepth=0;
    foreach($tableOfContents as $item){
        if ($item["depth"]>$currentDepth){echo("<ul>");}
        elseif($item["depth"]<$currentDepth){echo("</ul>");}
        echo($item["item"]);
        $currentDepth=$item["depth"];
    }
    echo("<a href='./downloadSnippet.php?tag=$tagName'>Download  .tex </a>");
?>
    </ul>
  </div>

<!Build Table of Contents >

<article>
   <?php    
   echo($bodyText); 

    sort($referenceKeys);
    if (count($referenceKeys) !== 0) {
        echo("<h1> References </h1> \n <table>\n");
        foreach($referenceKeys as $key){
            $keyText=$bibArray['#'.$key];
            echo("<tr id=\"$key\"> <td>[$keyText]</td><td>$bibArray[$key]</td></tr>\n");
        }
        echo("</table>");
   }
   
?>

</article>
